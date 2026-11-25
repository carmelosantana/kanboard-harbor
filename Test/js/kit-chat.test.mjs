import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

function loadChat(threads = []) {
  const observers = [];
  class MutationObserver {
    constructor(callback) { this.callback = callback; }
    observe(target, options) { observers.push({ target, options, callback: this.callback }); }
  }

  const document = Object.assign(new EventTarget(), {
    readyState: 'loading',
    querySelectorAll: selector => selector === '[data-slot="chat-thread"]' ? threads : [],
  });
  const window = { Kit: {}, MutationObserver };
  const source = readFileSync(new URL('../../Assets/js/kit-chat.js', import.meta.url), 'utf8');
  new Function('window', 'document', source)(window, document);
  document.dispatchEvent(new Event('DOMContentLoaded'));

  function append(target, height) {
    target.scrollHeight += height;
    for (const observer of observers) {
      if (observer.target === target && observer.options.childList) {
        observer.callback([{ type: 'childList', target, addedNodes: [{}] }]);
      }
    }
  }

  return { document, Kit: window.Kit, append };
}

test('chat threads follow new messages within 80 pixels of the bottom and resume after scrolling back', () => {
  const thread = Object.assign(new EventTarget(), { scrollHeight: 600, clientHeight: 200 });
  let scrollTop = 400;
  Object.defineProperty(thread, 'scrollTop', {
    get: () => scrollTop,
    set: value => { scrollTop = Math.min(value, thread.scrollHeight - thread.clientHeight); },
  });
  const { Kit, append } = loadChat([thread]);
  Kit.chatThread(thread);

  function scrollTo(top) {
    thread.scrollTop = top;
    thread.dispatchEvent(new Event('scroll'));
  }

  scrollTo(320);
  append(thread, 200);
  assert.equal(thread.scrollTop, 600);

  scrollTo(519);
  append(thread, 200);
  assert.equal(thread.scrollTop, 519);

  scrollTo(800);
  append(thread, 200);
  assert.equal(thread.scrollTop, 1000);
});

test('Enter submits the composer and Shift+Enter keeps native newline behavior', () => {
  const { document } = loadChat();
  let submissions = 0;
  const form = {
    matches: selector => selector === '[data-slot="composer"]',
    requestSubmit: () => { submissions++; },
  };
  const textarea = { matches: selector => selector === 'textarea', form };

  function enter(modifiers = {}, target = textarea) {
    const event = Object.assign(new Event('keydown', { cancelable: true }), { key: 'Enter', ...modifiers });
    Object.defineProperty(event, 'target', { value: target });
    document.dispatchEvent(event);
    return event.defaultPrevented;
  }

  assert.equal(enter({ shiftKey: true }), false);
  assert.equal(enter({ isComposing: true }), false);
  assert.equal(enter({}, { ...textarea, form: null }), false);
  assert.equal(submissions, 0);
  assert.equal(enter(), true);
  assert.equal(submissions, 1);
});
