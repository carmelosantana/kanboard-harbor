import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const src = readFileSync(new URL('../../Assets/js/kit.js', import.meta.url), 'utf8');

function harness() {
  function element(tag) {
    const listeners = new Map();
    return {
      tag,
      children: [],
      attrs: new Map(),
      append(child) {
        this.children.push(child);
        child.parent = this;
      },
      remove() {
        const siblings = this.parent.children;
        siblings.splice(siblings.indexOf(this), 1);
      },
      setAttribute(name, value) { this.attrs.set(name, value); },
      contains(target) { return target === this || this.children.some(child => child.contains(target)); },
      addEventListener(type, listener) { listeners.set(type, listener); },
      emit(type, event = {}) { listeners.get(type)?.(event); },
    };
  }

  let now = 0;
  let nextTimer = 0;
  const timers = new Map();
  const window = {};
  const body = element('body');
  const document = {
    body,
    createElement: element,
    querySelector(selector) {
      assert.equal(selector, '[data-slot="toaster"]');
      return body.children.find(child => child.attrs.get('data-slot') === 'toaster');
    },
  };
  function schedule(fn, delay) {
    timers.set(++nextTimer, { fn, due: now + delay });
    return nextTimer;
  }
  function advance(ms) {
    const end = now + ms;
    while (true) {
      const next = [...timers].sort((a, b) => a[1].due - b[1].due)[0];
      if (!next || next[1].due > end) break;
      const [id, { fn, due }] = next;
      now = due;
      timers.delete(id);
      fn();
    }
    now = end;
  }

  new Function('window', 'document', 'setTimeout', 'clearTimeout', 'Date', src)(
    window, document, schedule, id => timers.delete(id), { now: () => now },
  );
  return { Kit: window.Kit, body, advance, timers };
}

test('toasts share one status region and Undo dismisses its toast and calls back once', () => {
  const h = harness();
  let calls = 0;
  h.Kit.toast('Changed.', { undo() { calls++; } });
  h.Kit.toast('Saved.');

  assert.equal(h.body.children.length, 1);
  const [region] = h.body.children;
  assert.equal(region.attrs.get('data-slot'), 'toaster');
  assert.equal(region.attrs.get('role'), 'status');
  const [first, second] = region.children;
  assert.equal(first.attrs.get('data-slot'), 'toast');
  assert.equal(first.children[0].textContent, 'Changed.');
  assert.equal(second.children[0].textContent, 'Saved.');
  const button = first.children.find(child => child.tag === 'button');
  assert.equal(button.type, 'button');
  assert.equal(button.textContent, 'Undo');

  button.emit('click');
  button.emit('click');
  assert.equal(calls, 1);
  assert.deepEqual(region.children, [second]);
  h.advance(5999);
  assert.deepEqual(region.children, [second]);
  h.advance(1);
  assert.equal(region.children.length, 0);
  assert.equal(h.timers.size, 0);
});

test('the six-second countdown pauses during focus and resumes with its remaining time', () => {
  const h = harness();
  h.Kit.toast('Changed.', { undo() {} });
  const [region] = h.body.children;
  const [toast] = region.children;
  h.advance(4000);
  toast.emit('focusin');
  h.advance(10000);
  assert.equal(region.children.length, 1);

  toast.emit('focusout', { relatedTarget: toast.children[1] });
  h.advance(10000);
  assert.equal(region.children.length, 1);

  toast.emit('focusout', { relatedTarget: null });
  h.advance(1999);
  assert.equal(region.children.length, 1);
  h.advance(1);
  assert.equal(region.children.length, 0);
});

test('hover keeps the countdown paused after focus leaves', () => {
  const h = harness();
  h.Kit.toast('Changed.', { undo() {} });
  const [region] = h.body.children;
  const [toast] = region.children;
  h.advance(5000);
  toast.emit('pointerenter');
  toast.emit('focusin');
  toast.emit('focusout', { relatedTarget: null });
  h.advance(6000);
  assert.equal(region.children.length, 1);

  toast.emit('pointerleave');
  h.advance(999);
  assert.equal(region.children.length, 1);
  h.advance(1);
  assert.equal(region.children.length, 0);
});
