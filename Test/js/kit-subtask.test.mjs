import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { setImmediate as nextTurn } from 'node:timers/promises';

const src = readFileSync(new URL('../../Assets/js/kit.js', import.meta.url), 'utf8');

const actions = [
  { id: 'subtask.done', label: 'Mark done', disabled: false, states: ['todo', 'doing'], url: '/done', csrf_token: 'done-token' },
  { id: 'subtask.start', label: 'Start working', disabled: true, states: ['todo'], url: '/doing', csrf_token: 'doing-token' },
  { id: 'subtask.todo', label: 'Back to todo', disabled: false, states: ['doing', 'done'], url: '/todo', csrf_token: 'todo-token' },
];

function harness() {
  function captureEvent() {
    return {
      prevented: false,
      stopped: false,
      preventDefault() { this.prevented = true; },
      stopImmediatePropagation() { this.stopped = true; },
    };
  }

  function element(tag, selector, classes = []) {
    const attrs = new Map();
    const listeners = new Map();
    const classNames = new Set(classes);
    return {
      tagName: tag.toUpperCase(),
      classList: {
        add(...names) { names.forEach(name => classNames.add(name)); },
        remove(...names) { names.forEach(name => classNames.delete(name)); },
        contains(name) { return classNames.has(name); },
      },
      dataset: {},
      children: [],
      style: { setProperty() {} },
      append(...nodes) {
        this.children.push(...nodes);
        nodes.forEach(node => { if (typeof node === 'object') node.parentNode = this; });
      },
      replaceChildren(...nodes) { this.children = []; this.append(...nodes); },
      remove() {
        const siblings = this.parentNode.children;
        siblings.splice(siblings.indexOf(this), 1);
      },
      setAttribute(name, value) { attrs.set(name, value); },
      getAttribute(name) { return attrs.get(name) ?? null; },
      removeAttribute(name) { attrs.delete(name); },
      hasAttribute(name) { return attrs.has(name); },
      closest(query) {
        return query === selector ? this : this.parentNode?.closest(query);
      },
      querySelectorAll(query) {
        return this.children.filter(node => typeof node === 'object').flatMap(child => [
          ...(child.closest(query) === child || (query === '[role="menuitem"]' && child.getAttribute('role') === 'menuitem')
            ? [child] : []),
          ...child.querySelectorAll(query),
        ]);
      },
      querySelector(query) { return this.querySelectorAll(query)[0] ?? null; },
      getBoundingClientRect() { return { left: 5, top: 5, bottom: 25, width: 100, height: 20 }; },
      getClientRects() { return [{}]; },
      contains(node) { return node === this || this.children.some(child => child.contains?.(node)); },
      focus() {},
      addEventListener(name, listener) {
        if (!listeners.has(name)) listeners.set(name, new Set());
        listeners.get(name).add(listener);
      },
      removeEventListener(name, listener) { listeners.get(name)?.delete(listener); },
      emit(name, event) {
        for (const listener of [...(listeners.get(name) ?? [])]) {
          listener(event);
          if (event?.stopped) break;
        }
      },
    };
  }

  const body = element('body');
  const row = element('tr', '.subtasks-table tr[data-subtask-id]');
  const check = element('a', '.js-subtask-toggle-status');
  const checkIcon = element('i', 'i', ['fa-gears']);
  const trigger = element('button', '[data-slot="subtask-actions-trigger"]');
  trigger.hidden = true;
  Object.assign(trigger.dataset, {
    doneTitle: 'Subtask completed', startTimer: 'Start timer', stopTimer: 'Stop timer',
  });
  const metadata = element('div', '[data-slot="subtask-row"]');
  Object.assign(metadata.dataset, { state: 'doing', actions: 'subtask.done subtask.todo' });
  const template = element('template', 'template');
  template.content = { textContent: JSON.stringify(actions) };
  const timerContainer = element('span');
  const timer = element('a', '.js-subtask-toggle-timer');
  timer.href = 'http://kanboard.test/?timer=stop';
  const timerIcon = element('i', 'i', ['fa-pause']);
  timer.append(timerIcon, 'Stop timer');
  timerContainer.append(timer, ' (elapsed)');
  check.append(checkIcon);
  metadata.append(template);
  row.append(check, trigger, metadata, timerContainer);
  body.append(row);

  const document = Object.assign(element('document'), {
    body,
    readyState: 'complete',
    querySelectorAll: query => body.querySelectorAll(query),
    querySelector: query => body.querySelectorAll(query)[0],
    createElement: tag => {
      const el = element(tag);
      if (tag === 'div') {
        const baseClosest = el.closest;
        el.closest = (query) => el.getAttribute('data-slot') && query === '[data-slot="' + el.getAttribute('data-slot') + '"]'
          ? el : baseClosest.call(el, query);
      }
      return el;
    },
  });
  const requests = [];
  const window = {
    innerWidth: 1024,
    innerHeight: 768,
    requestAnimationFrame() { return 0; },
    cancelAnimationFrame() {},
    addEventListener() {},
    removeEventListener() {},
    fetch(url, options) {
      return new Promise(resolve => requests.push({ url, options, resolve }));
    },
  };

  new Function('window', 'document', 'setTimeout', 'clearTimeout', src)(window, document, () => 0, () => {});
  function open(target, type = 'click') {
    const event = Object.assign(captureEvent(), { target });
    document.emit(type, event);
    return {
      event,
      items: body.children.at(-1).querySelectorAll('[role="menuitem"]'),
    };
  }

  return { row, check, checkIcon, trigger, metadata, timer, timerIcon, timerContainer, requests, open, body };
}

test('checkbox, right-click and overflow open the same menu with inapplicable states disabled', () => {
  const h = harness();
  assert.equal(h.trigger.hidden, false);
  for (const [target, type] of [[h.check, 'click'], [h.row, 'contextmenu'], [h.trigger, 'click']]) {
    const { event, items } = h.open(target, type);
    assert.equal(event.prevented, true);
    assert.equal(event.stopped, true);
    assert.deepEqual(items.map(item => item.textContent), ['Mark done', 'Start working', 'Back to todo']);
    assert.deepEqual(items.map(item => item.disabled), [false, true, false]);
  }
  h.open(h.check).items[1].emit('click');
  assert.equal(h.requests.length, 0);
});

test('a pending row ignores a second choice and updates its status and timer once', async () => {
  const h = harness();
  const [done] = h.open(h.check).items;
  done.emit('click');
  done.emit('click');
  assert.equal(h.requests.length, 1);
  assert.equal(h.row.dataset.state, 'pending');
  assert.equal(h.row.getAttribute('aria-busy'), 'true');
  assert.equal(h.metadata.dataset.actions, '');
  assert.equal(h.requests[0].options.method, 'POST');
  assert.equal(h.requests[0].options.body.get('csrf_token'), 'done-token');

  h.requests[0].resolve({ ok: true, json: async () => ({ status: 'done', timer: false }) });
  await nextTurn();
  assert.equal(h.row.dataset.state, 'done');
  assert.equal(h.row.getAttribute('aria-busy'), null);
  assert.equal(h.metadata.dataset.actions, 'subtask.todo');
  assert.equal(h.checkIcon.classList.contains('fa-check-square-o'), true);
  assert.equal(h.check.title, 'Subtask completed');
  assert.equal(h.timer.children[1], 'Start timer');
  assert.equal(new URL(h.timer.href).searchParams.get('timer'), 'start');
  assert.equal(h.timerIcon.classList.contains('fa-play-circle-o'), true);
  assert.deepEqual(h.timerContainer.children, [h.timer]);
});

test('a failed request restores the row and displays the error toast', async () => {
  const h = harness();
  h.open(h.check).items[0].emit('click');
  h.requests[0].resolve({ ok: false });
  await nextTurn();
  assert.equal(h.row.dataset.state, 'doing');
  assert.equal(h.row.getAttribute('aria-busy'), null);
  assert.equal(h.metadata.dataset.actions, 'subtask.done subtask.todo');
  assert.equal(h.body.children.at(-1).children[0].children[0].textContent, "Couldn't update the subtask. Try again.");
});

test('Undo posts the previous explicit status even if its menu item is now disabled', async () => {
  const h = harness();
  h.open(h.check).items[0].emit('click');
  h.requests[0].resolve({ ok: true, json: async () => ({ status: 'done', timer: false }) });
  await nextTurn();

  const toast = h.body.children.at(-1).children[0];
  assert.equal(toast.children[0].textContent, 'Subtask marked done.');
  assert.equal(toast.children[1].textContent, 'Undo');
  toast.children[1].emit('click');
  assert.equal(h.requests.length, 2);
  assert.equal(h.requests[1].url, '/doing');
  assert.equal(h.requests[1].options.body.get('csrf_token'), 'doing-token');
  h.requests[1].resolve({ ok: true, json: async () => ({ status: 'doing', timer: true }) });
  await nextTurn();
  assert.equal(h.row.dataset.state, 'doing');
});
