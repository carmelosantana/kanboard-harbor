import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const core = readFileSync(new URL('../../Assets/js/kit.js', import.meta.url), 'utf8');

function harness() {
  function target() {
    const listeners = new Map();
    return {
      addEventListener(type, listener) {
        if (!listeners.has(type)) listeners.set(type, new Set());
        listeners.get(type).add(listener);
      },
      removeEventListener(type, listener) { listeners.get(type)?.delete(listener); },
      emit(type, options = {}) {
        const event = {
          target: this,
          preventDefault() { this.defaultPrevented = true; },
          stopPropagation() {},
          ...options,
        };
        for (const listener of [...(listeners.get(type) ?? [])]) listener(event);
        return event;
      },
    };
  }

  const document = { ...target(), activeElement: null, documentElement: { lang: 'en' }, querySelectorAll: () => [] };

  function element(tagName = 'DIV', children = []) {
    const attrs = new Map();
    const el = {
      ...target(),
      tagName,
      children,
      dataset: {},
      tabIndex: 0,
      hidden: false,
      disabled: false,
      clientWidth: 100,
      get scrollWidth() { return Math.max(this.clientWidth, this.children.filter(child => !child.hidden).length * 30); },
      setAttribute(name, value) { attrs.set(name, String(value)); },
      getAttribute(name) { return attrs.get(name) ?? null; },
      hasAttribute(name) { return attrs.has(name); },
      removeAttribute(name) { attrs.delete(name); },
      append(...nodes) { this.children.push(...nodes); },
      replaceChildren(...nodes) { this.children = nodes; },
      contains(node) { return node === this || this.children.some(child => child.contains(node)); },
      closest(selector) { return this.matches(selector) ? this : null; },
      matches(selector) {
        return selector.split(',').some(part => {
          const attribute = part.trim().match(/^\[data-([\w-]+)\]$/);
          if (attribute) {
            const name = attribute[1].replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
            return name in this.dataset;
          }
          return part.trim().split('[')[0].toUpperCase() === tagName;
        });
      },
      querySelectorAll(selector) {
        return this.children.flatMap(child => [
          ...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector),
        ]);
      },
      querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; },
      getBoundingClientRect() { return { left: 0, top: 0, bottom: 20, width: 50, height: 20 }; },
      getClientRects() { return this.hidden ? [] : [this.getBoundingClientRect()]; },
      style: { setProperty() {} },
      focus() { document.activeElement = this; },
    };
    return el;
  }

  document.createElement = tag => element(tag.toUpperCase());
  let id = 0;
  let now = 0;
  const frames = new Map();
  const timers = new Map();
  const navigations = [];
  const window = {
    ...target(),
    innerWidth: 900,
    innerHeight: 700,
    requestAnimationFrame(fn) { frames.set(++id, fn); return id; },
    cancelAnimationFrame(frame) { frames.delete(frame); },
    setTimeout(fn, delay) { timers.set(++id, { fn, at: now + delay }); return id; },
    clearTimeout(timer) { timers.delete(timer); },
    location: {
      href: 'https://example.test/audit?project_id=94#results',
      assign(url) { navigations.push(new URL(url)); },
    },
  };

  new Function('window', 'document', core)(window, document);
  return {
    Kit: window.Kit, document, element, navigations,
    loadNav() {
      const nav = readFileSync(new URL('../../Assets/js/kit-nav.js', import.meta.url), 'utf8');
      new Function('window', 'document', nav)(window, document);
    },
    frame() {
      const pending = [...frames.values()];
      frames.clear();
      pending.forEach(fn => fn());
    },
    advance(ms) {
      now += ms;
      for (const [timer, { fn, at }] of timers) {
        if (at > now) continue;
        timers.delete(timer);
        fn();
      }
    },
  };
}

test('breadcrumb overflow keeps the first and last levels and expands the middle in place', () => {
  const h = harness();
  h.loadNav();
  const links = Array.from({ length: 4 }, () => h.element('A'));
  const items = links.map(link => h.element('LI', [link]));
  const toggle = h.element('BUTTON');
  toggle.dataset.breadcrumbToggle = '';
  toggle.textContent = '…';
  const overflow = h.element('LI', [toggle]);
  overflow.hidden = true;
  const list = h.element('OL', [items[0], overflow, ...items.slice(1)]);
  const nav = h.element('NAV', [list]);
  list.clientWidth = 150;

  h.Kit.breadcrumbs(nav);
  assert.ok(items.every(item => !item.hidden));
  list.clientWidth = 100;
  h.Kit.breadcrumbs(nav);
  assert.deepEqual(items.map(item => item.hidden), [false, true, true, false]);
  assert.equal(overflow.hidden, false);
  assert.equal(toggle.textContent, '…');

  toggle.click = () => toggle.emit('click');
  toggle.click();
  assert.deepEqual(items.map(item => item.hidden), [false, false, false, false]);
  assert.equal(overflow.hidden, true);
  assert.equal(h.document.activeElement, links[1]);
});

test('Custom uses the popup engine, navigates days across months, closes on Escape and applies ISO dates', () => {
  const h = harness();
  h.loadNav();
  const custom = h.element('BUTTON');
  custom.dataset.rangeCustom = '';
  const calendar = h.element();
  calendar.dataset.rangeMonths = '';
  const actions = ['rangePrevious', 'rangeNext', 'rangeCancel', 'rangeApply'].map(name => {
    const button = h.element('BUTTON');
    button.dataset[name] = '';
    return button;
  });
  const panel = h.element('DIV', [...actions.slice(0, 2), calendar, ...actions.slice(2)]);
  panel.dataset.rangePanel = '';
  panel.hidden = true;
  const root = h.element('DIV', [custom, panel]);
  root.dataset.from = '2026-11-30T00:00:00Z';
  root.dataset.to = '2026-12-03T23:59:59Z';

  h.Kit.dateRange(root);
  custom.emit('click');
  assert.equal(panel.getAttribute('data-popup-preset'), 'popover');
  assert.equal(panel.getAttribute('data-state'), 'open');
  assert.deepEqual(calendar.querySelectorAll('h3').map(title => title.textContent), ['November 2026', 'December 2026']);

  const day = () => h.document.activeElement;
  assert.equal(day().dataset.rangeDay, '2026-11-30');
  const right = calendar.emit('keydown', { key: 'ArrowRight', target: day() });
  assert.equal(right.defaultPrevented, true);
  assert.equal(day().dataset.rangeDay, '2026-12-01');

  h.document.emit('keydown', { key: 'Escape', target: day() });
  assert.equal(panel.getAttribute('data-state'), 'closed');
  assert.equal(h.document.activeElement, custom);
  h.frame();
  assert.equal(panel.hidden, true);
  assert.deepEqual(h.navigations, []);

  custom.emit('click');
  calendar.emit('click', { target: day() });
  calendar.emit('keydown', { key: 'ArrowRight', target: day() });
  calendar.emit('click', { target: day() });
  actions[3].emit('click');
  assert.equal(h.navigations.length, 1);
  const [{ searchParams, hash }] = h.navigations;
  assert.equal(searchParams.get('from'), '2026-11-30T00:00:00.000Z');
  assert.equal(searchParams.get('to'), '2026-12-01T23:59:59.999Z');
  assert.equal(searchParams.get('project_id'), '94');
  assert.equal(hash, '#results');
});

test('deferred skeleton has no flash before 300 ms and hides again on resolve or rejection', async () => {
  const h = harness();
  const el = h.element();
  let resolve;
  const fast = h.Kit.skeleton(el, new Promise(done => { resolve = done; }));
  h.advance(299);
  assert.equal(el.getAttribute('data-skeleton'), 'waiting');
  resolve('content');
  assert.equal(await fast, 'content');
  h.advance(2);
  assert.equal(el.getAttribute('data-skeleton'), 'settled');

  let reject;
  const slow = h.Kit.skeleton(el, new Promise((_, fail) => { reject = fail; }));
  h.advance(299);
  assert.equal(el.getAttribute('data-skeleton'), 'waiting');
  h.advance(2);
  assert.equal(el.getAttribute('data-skeleton'), 'visible');
  reject(new Error('load failed'));
  await assert.rejects(slow, /load failed/);
  assert.equal(el.getAttribute('data-skeleton'), 'settled');
});
