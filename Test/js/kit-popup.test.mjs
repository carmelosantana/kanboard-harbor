import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const src = readFileSync(new URL('../../Assets/js/kit.js', import.meta.url), 'utf8');

function harness() {
  function target() {
    const listeners = new Map();
    return {
      addEventListener(type, listener) {
        if (!listeners.has(type)) listeners.set(type, new Set());
        listeners.get(type).add(listener);
      },
      removeEventListener(type, listener) { listeners.get(type)?.delete(listener); },
      emit(type, event) {
        for (const listener of [...(listeners.get(type) ?? [])]) listener(event);
      },
    };
  }

  const document = { ...target(), activeElement: null };
  const frames = new Map();
  let frameId = 0;
  let now = 0;
  const window = {
    ...target(),
    innerWidth: 300,
    innerHeight: 200,
    requestAnimationFrame(fn) { frames.set(++frameId, fn); return frameId; },
    cancelAnimationFrame(id) { frames.delete(id); },
  };

  function element(children = [], rect = { left: 0, top: 0, bottom: 20, width: 50, height: 20 }) {
    const attrs = new Map();
    const values = new Map();
    return {
      ...target(),
      attrs,
      values,
      hidden: false,
      tabIndex: 0,
      textContent: '',
      setAttribute(name, value) { attrs.set(name, value); },
      getAttribute(name) { return attrs.get(name) ?? null; },
      removeAttribute(name) { attrs.delete(name); },
      hasAttribute(name) { return attrs.has(name); },
      contains(node) { return node === this || children.some(child => child.contains(node)); },
      querySelectorAll() { return children; },
      getBoundingClientRect() { return rect; },
      getClientRects() { return [rect]; },
      style: { setProperty(name, value) { values.set(name, value); } },
      focus() {
        document.activeElement = this;
        document.emit('focusin', { target: this });
      },
    };
  }

  function key(target, key, options) {
    const event = { key, target: document.activeElement, preventDefault() {}, stopPropagation() {}, ...options };
    target.emit('keydown', event);
  }

  new Function('window', 'document', 'Date', src)(window, document, { now: () => now });
  return {
    Kit: window.Kit, document, element, key,
    advance(ms) { now += ms; },
    frame() {
      const pending = [...frames.values()];
      frames.clear();
      pending.forEach(fn => fn());
    },
  };
}

test('roving wraps, skips disabled items, and supports Home, End and typeahead', () => {
  const h = harness();
  const items = ['Archive', 'Disabled', 'Save', 'Send'].map(label => {
    const item = h.element();
    item.textContent = label;
    return item;
  });
  items[1].setAttribute('aria-disabled', 'true');
  const list = h.element(items);
  const cleanup = h.Kit.roving(list);

  items[0].focus();
  h.key(list, 'ArrowDown');
  assert.equal(h.document.activeElement, items[2]);
  assert.deepEqual(items.map(item => item.tabIndex), [-1, -1, 0, -1]);
  h.key(list, 'ArrowUp');
  assert.equal(h.document.activeElement, items[0]);
  h.key(list, 'ArrowUp');
  assert.equal(h.document.activeElement, items[3]);
  h.key(list, 'Home');
  assert.equal(h.document.activeElement, items[0]);
  h.key(list, 'End');
  assert.equal(h.document.activeElement, items[3]);
  h.key(list, 'd');
  assert.equal(h.document.activeElement, items[3]);
  h.advance(600);
  h.key(list, 's');
  assert.equal(h.document.activeElement, items[2]);
  h.key(list, 'e');
  h.key(list, 'n');
  assert.equal(h.document.activeElement, items[3]);
  cleanup();
  h.key(list, 'Home');
  assert.equal(h.document.activeElement, items[3]);
});

test('Esc closes the active popup and restores focus to its anchor', () => {
  const h = harness();
  const anchor = h.element();
  const item = h.element();
  const panel = h.element([item]);
  panel.hidden = true;
  h.Kit.popup(anchor, panel, { preset: 'menu' });

  assert.equal(panel.getAttribute('data-state'), 'open');
  assert.equal(panel.hasAttribute('data-starting-style'), true);
  assert.equal(h.document.activeElement, item);
  h.frame();
  assert.equal(panel.hasAttribute('data-starting-style'), false);

  h.key(h.document, 'Escape');
  assert.equal(panel.getAttribute('data-state'), 'closed');
  assert.equal(panel.hasAttribute('data-ending-style'), true);
  assert.equal(h.document.activeElement, anchor);
  h.frame();
  assert.equal(panel.hidden, true);
  assert.equal(panel.hasAttribute('data-ending-style'), false);
});

test('anchored placement flips above and fits the viewport when there is no room below', () => {
  const h = harness();
  const anchor = h.element([], { left: 280, top: 175, bottom: 195 });
  const panel = h.element();
  h.Kit.popup(anchor, panel, { preset: 'popover' });

  assert.equal(panel.values.get('--popup-x'), '242px');
  assert.equal(panel.values.get('--popup-y'), '147px');
  h.document.emit('click', { target: panel });
  h.document.emit('click', { target: anchor });
  assert.equal(panel.getAttribute('data-state'), 'open');

  h.document.emit('click', { target: h.element() });
  assert.equal(panel.getAttribute('data-state'), 'closed');
  assert.equal(h.document.activeElement, anchor);
});

test('dialog wraps Tab in both directions and keeps focus inside until it closes', () => {
  const h = harness();
  const anchor = h.element();
  const first = h.element();
  const last = h.element();
  const panel = h.element([first, last]);
  const popup = h.Kit.popup(anchor, panel, { preset: 'dialog' });

  assert.equal(panel.getAttribute('aria-modal'), 'true');
  assert.equal(h.document.activeElement, first);
  h.key(h.document, 'Tab', { shiftKey: true });
  assert.equal(h.document.activeElement, last);
  h.key(h.document, 'Tab');
  assert.equal(h.document.activeElement, first);
  anchor.focus();
  assert.equal(h.document.activeElement, first);

  popup.close();
  assert.equal(h.document.activeElement, anchor);
  popup.open();
  h.frame();
  assert.equal(panel.hidden, false);
  assert.equal(h.document.activeElement, first);
});

test('a tooltip opens without moving focus', () => {
  const h = harness();
  const anchor = h.element();
  const panel = h.element();
  anchor.focus();
  h.Kit.popup(anchor, panel, { preset: 'tooltip' });
  assert.equal(panel.getAttribute('role'), 'tooltip');
  assert.equal(h.document.activeElement, anchor);
  assert.equal(panel.getAttribute('data-state'), 'open');
});

test('a tooltip inside a dialog leaves the dialog focus trap active', () => {
  const h = harness();
  const first = h.element();
  const last = h.element();
  const tooltip = h.element();
  h.Kit.popup(h.element(), h.element([first, last]), { preset: 'dialog' });
  last.focus();
  h.Kit.popup(last, tooltip, { preset: 'tooltip' });

  h.key(h.document, 'Tab');
  assert.equal(h.document.activeElement, first);
  h.element().focus();
  assert.equal(h.document.activeElement, first);
  h.key(h.document, 'Escape');
  assert.equal(tooltip.getAttribute('data-state'), 'closed');
});

test('an open tooltip leaves outside click dismissal active for a menu', () => {
  const h = harness();
  const item = h.element();
  const menu = h.element([item]);
  const tooltip = h.element();
  h.Kit.popup(h.element(), menu, { preset: 'menu' });
  h.Kit.popup(item, tooltip, { preset: 'tooltip' });

  h.document.emit('click', { target: h.element() });
  assert.equal(menu.getAttribute('data-state'), 'closed');
  assert.equal(tooltip.getAttribute('data-state'), 'closed');
});
