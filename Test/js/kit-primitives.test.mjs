import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const src = readFileSync(new URL('../../Assets/js/kit.js', import.meta.url), 'utf8');

function harness() {
  const listeners = new Map();
  const timers = new Map();
  const writes = [];
  let time = 0;
  const sheet = {
    open: false,
    showModal() { this.open = true; },
  };
  const document = {
    readyState: 'complete',
    querySelectorAll: () => [],
    querySelector(selector) {
      assert.equal(selector, '[data-slot="shortcut-sheet"]');
      return sheet;
    },
    addEventListener(type, listener, capture) {
      if (!listeners.has(type)) listeners.set(type, []);
      listeners.get(type).push({ listener, capture });
    },
  };
  const window = {
    navigator: { clipboard: { async writeText(value) { writes.push(value); } } },
    setTimeout(fn, delay) {
      const id = Symbol();
      timers.set(id, { fn, due: time + delay });
      return id;
    },
    clearTimeout(id) { timers.delete(id); },
  };
  new Function('window', 'document', src)(window, document);

  return {
    sheet, writes, window,
    advance(ms) {
      time += ms;
      for (const [id, { fn, due }] of timers) {
        if (time < due) continue;
        timers.delete(id);
        fn();
      }
    },
    emit(type, attrs) {
      const event = {
        preventDefault() { this.defaultPrevented = true; },
        stopPropagation() { this.stopped = true; },
        ...attrs,
      };
      const handlers = [...(listeners.get(type) ?? [])].sort((a, b) => Number(!!b.capture) - Number(!!a.capture));
      return Promise.all(handlers.map(({ listener }) => listener(event))).then(() => event);
    },
  };
}

test('a delegated copy button writes its value and restores its label after two seconds', async () => {
  const h = harness();
  const button = { dataset: { copy: 'ssh example.test' }, textContent: 'Copy' };
  const child = { closest: selector => selector === '[data-slot="copy-button"]' ? button : null };

  await h.emit('click', { target: child });
  assert.deepEqual(h.writes, ['ssh example.test']);
  assert.equal(button.textContent, 'Copied');
  h.advance(1999);
  assert.equal(button.textContent, 'Copied');
  h.advance(1);
  assert.equal(button.textContent, 'Copy');
});

test('copy feedback restarts after another success and rejection leaves the label alone', async () => {
  const h = harness();
  const button = {
    dataset: { copy: '', copiedLabel: 'Copié' },
    textContent: 'Copier',
    closest: selector => selector === '[data-slot="copy-button"]' ? button : null,
  };
  await h.emit('click', { target: button });
  h.advance(1500);
  await h.emit('click', { target: button });
  assert.deepEqual(h.writes, ['', '']);
  h.advance(500);
  assert.equal(button.textContent, 'Copié');
  h.advance(1500);
  assert.equal(button.textContent, 'Copier');

  h.window.navigator.clipboard.writeText = async () => { throw new Error('Clipboard permission denied'); };
  await h.emit('click', { target: button });
  assert.equal(button.textContent, 'Copier');
});

test('question mark opens the sheet and consumes the core shortcut', async () => {
  const h = harness();
  const event = await h.emit('keydown', { key: '?', target: { closest: () => null } });
  assert.equal(h.sheet.open, true);
  assert.equal(event.defaultPrevented, true);
  assert.equal(event.stopped, true);
});

test('question mark is left alone in an input, textarea or editable element', async () => {
  for (const tag of ['input', 'textarea', 'contenteditable']) {
    const h = harness();
    const target = {
      isContentEditable: tag === 'contenteditable',
      closest: selector => selector.split(/,\s*/).includes(tag) ? target : null,
    };
    const event = await h.emit('keydown', { key: '?', target });
    assert.equal(h.sheet.open, false, tag);
    assert.equal(event.defaultPrevented, undefined, tag);
    assert.equal(event.stopped, undefined, tag);
  }
});
