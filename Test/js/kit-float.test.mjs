import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

function element(attributes = {}) {
  const target = new EventTarget();
  const attrs = new Map(Object.entries(attributes));
  const change = name => target.dispatchEvent(new Event(`attribute:${name}`));
  return Object.assign(target, {
    getAttribute: name => attrs.get(name) ?? null,
    hasAttribute: name => attrs.has(name),
    setAttribute(name, value) { attrs.set(name, value); change(name); },
    removeAttribute(name) { if (attrs.delete(name)) change(name); },
  });
}

function emit(target, type, trusted = true, fields = {}) {
  const event = new Event(type, { cancelable: true });
  Object.defineProperty(event, 'isTrusted', { value: trusted });
  Object.assign(event, fields);
  target.dispatchEvent(event);
  return event;
}

function loadKit() {
  class MutationObserver {
    constructor(callback) { this.callback = callback; }
    observe(target, { attributeFilter: [name] }) {
      this.target = target;
      this.type = `attribute:${name}`;
      target.addEventListener(this.type, this.callback);
    }
    disconnect() { this.target.removeEventListener(this.type, this.callback); }
  }
  const window = { Kit: {}, setTimeout, clearTimeout, MutationObserver };
  const source = readFileSync(new URL('../../Assets/js/kit-float.js', import.meta.url), 'utf8');
  new Function('window', 'document', source)(window, { readyState: 'loading', addEventListener() {} });
  return window.Kit;
}

function confirmFixture() {
  const input = element();
  const button = Object.assign(element(), { disabled: true });
  const form = element();
  const root = element({ 'data-phrase': 'delete project' });
  root.querySelector = selector => selector === 'form' ? form : null;
  form.querySelector = selector => ({ '[name="confirmation"]': input, 'button[type="submit"]': button })[selector];
  loadKit().typedConfirm(root);
  return { input, button, form };
}

test('typed confirm requires an exact match from the input', () => {
  const { input, button } = confirmFixture();
  assert.equal(button.disabled, true);
  for (const [value, disabled] of [
    ['delete project', false],
    ['Delete project', true],
    ['delete project ', true],
    [' delete project', true],
    ['', true],
  ]) {
    input.value = value;
    emit(input, 'input');
    assert.equal(button.disabled, disabled, JSON.stringify(value));
  }
});

test('typed confirm rejects synthetic input, clicks and direct submit events', () => {
  const { input, button, form } = confirmFixture();
  input.value = 'delete project';
  emit(input, 'input', false);
  assert.equal(button.disabled, true);

  emit(input, 'input');
  assert.equal(button.disabled, false);
  assert.equal(emit(button, 'click', false).defaultPrevented, true);
  assert.equal(emit(form, 'submit', true, { submitter: button }).defaultPrevented, true);
  assert.equal(emit(button, 'click').defaultPrevented, false);
  assert.equal(emit(form, 'submit', true, { submitter: button }).defaultPrevented, false);
  assert.equal(emit(form, 'submit', true, { submitter: button }).defaultPrevented, true);
});

test('controls idle on playback after three seconds of inactivity and wake on interaction', t => {
  t.mock.timers.enable({ apis: ['setTimeout'] });
  const stage = element({ 'data-playing': '' });
  const cleanup = loadKit().floatControls(stage);
  const state = () => stage.getAttribute('data-state');

  assert.equal(state(), 'active');
  t.mock.timers.tick(2500);
  emit(stage, 'pointermove');
  t.mock.timers.tick(2999);
  assert.equal(state(), 'active');
  t.mock.timers.tick(1);
  assert.equal(state(), 'idle');

  emit(stage, 'keydown');
  assert.equal(state(), 'active');
  t.mock.timers.tick(3000);
  assert.equal(state(), 'idle');
  emit(stage, 'focusin');
  assert.equal(state(), 'active');
  cleanup();
});

test('controls stay visible when playback is paused', t => {
  t.mock.timers.enable({ apis: ['setTimeout'] });
  const stage = element();
  loadKit().floatControls(stage);
  emit(stage, 'pointermove');
  t.mock.timers.tick(10000);
  assert.equal(stage.getAttribute('data-state'), 'active');

  stage.setAttribute('data-playing', '');
  t.mock.timers.tick(3000);
  assert.equal(stage.getAttribute('data-state'), 'idle');
  stage.removeAttribute('data-playing');
  assert.equal(stage.getAttribute('data-state'), 'active');
});
