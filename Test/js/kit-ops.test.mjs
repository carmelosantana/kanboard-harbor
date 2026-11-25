import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

function loadOps() {
  const document = Object.assign(new EventTarget(), {
    hidden: false,
    readyState: 'complete',
    querySelectorAll: () => [],
  });
  const sources = [];
  const observers = new Set();
  const requests = [];
  const window = {
    Kit: {},
    EventSource: class extends EventTarget {
      constructor(url) {
        super();
        this.url = url;
        this.closed = false;
        sources.push(this);
      }
      close() { this.closed = true; }
    },
    MutationObserver: class {
      constructor(callback) { this.callback = callback; }
      observe() { observers.add(this); }
      disconnect() { observers.delete(this); }
    },
    setInterval,
    clearInterval,
    fetch: async (url, options) => {
      requests.push({ url, ...options });
      return { ok: true, text: async () => '<strong>Updated</strong>' };
    },
  };
  const source = readFileSync(new URL('../../Assets/js/kit-ops.js', import.meta.url), 'utf8');
  new Function('window', 'document', source)(window, document);

  return {
    Kit: window.Kit, sources, requests, document, window,
    remove(el) {
      el.isConnected = false;
      for (const observer of observers) observer.callback();
    },
  };
}

function fixture(selectors, dataset = {}) {
  return {
    dataset,
    isConnected: true,
    querySelector: selector => selectors[selector],
  };
}

function logFixture() {
  const body = Object.assign(new EventTarget(), {
    textContent: '',
    scrollHeight: 200,
    clientHeight: 100,
    scrollTop: 100,
    append(text) {
      this.textContent += text;
      this.scrollHeight += 20;
    },
  });
  let scrollTop = body.scrollTop;
  Object.defineProperty(body, 'scrollTop', {
    get: () => scrollTop,
    set: value => { scrollTop = Math.min(value, body.scrollHeight - body.clientHeight); },
  });
  const count = { dataset: { logCount: '%d lines' } };
  const button = () => Object.assign(new EventTarget(), { setAttribute(name, value) { this[name] = value; } });
  const follow = button();
  const pause = button();
  const jump = button();
  const el = fixture({
    '[role="log"]': body,
    '[data-log-count]': count,
    '[data-log-follow]': follow,
    '[data-log-pause]': pause,
    '[data-log-jump]': jump,
  }, { sseUrl: '/logs' });
  return { el, body, count, follow, pause, jump };
}

test('streams plain text and counts actual lines through a native reconnect', () => {
  const { Kit, sources } = loadOps();
  const { el, body, count } = logFixture();
  Kit.logViewer(el);
  Kit.logViewer(el);
  const [source] = sources;
  assert.equal(source.url, '/logs');
  source.dispatchEvent(new MessageEvent('message', { data: '\x1b[31merror\x1b[0m\nworking\n' }));
  source.dispatchEvent(new Event('error'));
  assert.equal(source.closed, false);
  assert.equal(sources.length, 1);
  source.dispatchEvent(new MessageEvent('message', { data: '\x1b]0;title\x07<done>' }));
  assert.equal(body.textContent, 'error\nworking\n<done>\n');
  assert.equal(count.textContent, '3 lines');
});

test('scrolling up or pressing Pause holds the position until following resumes', () => {
  const { Kit, sources } = loadOps();
  const { el, body, follow, pause, jump } = logFixture();
  Kit.logViewer(el);
  const append = () => sources[0].dispatchEvent(new MessageEvent('message', { data: 'new line' }));

  body.scrollTop = 80;
  body.dispatchEvent(new Event('scroll'));
  assert.equal(jump.hidden, false);
  assert.equal(pause['aria-pressed'], 'true');
  append();
  assert.equal(body.scrollTop, 80);
  jump.dispatchEvent(new Event('click'));
  assert.equal(jump.hidden, true);
  assert.equal(body.scrollTop, 120);
  append();
  assert.equal(body.scrollTop, 140);
  pause.dispatchEvent(new Event('click'));
  append();
  assert.equal(body.scrollTop, 140);
  follow.dispatchEvent(new Event('click'));
  assert.equal(body.scrollTop, 160);
});

test('done and removal each close the stream and stop processing messages', () => {
  const { Kit, sources, remove } = loadOps();
  const first = logFixture();
  const second = logFixture();
  Kit.logViewer(first.el);
  Kit.logViewer(second.el);
  sources[0].dispatchEvent(new Event('done'));
  remove(second.el);
  for (const source of sources) {
    assert.equal(source.closed, true);
    source.dispatchEvent(new MessageEvent('message', { data: 'late delivery' }));
  }
  assert.equal(first.body.textContent, '');
  assert.equal(second.body.textContent, '');
});

test('approval uses the absolute deadline and disables the action at zero', (t) => {
  t.mock.timers.enable({ apis: ['Date', 'setInterval'], now: 100000 });
  const { Kit } = loadOps();
  const clock = { textContent: '5:00', dataset: { approvalCountdown: 'Expired' } };
  const attrs = new Map([['href', '/approve']]);
  const action = Object.assign(new EventTarget(), {
    setAttribute: (name, value) => attrs.set(name, value),
    removeAttribute: name => attrs.delete(name),
  });
  const el = fixture({
    '[data-approval-countdown]': clock,
    '[data-approval-action]': action,
  }, { expiresAt: '400' });
  Kit.approvalCountdown(el);
  assert.equal(clock.textContent, '5:00');
  const synthetic = new Event('click', { cancelable: true });
  action.dispatchEvent(synthetic);
  assert.equal(synthetic.defaultPrevented, true);

  t.mock.timers.setTime(399000);
  t.mock.timers.tick(0);
  assert.equal(clock.textContent, '0:01');
  assert.equal(attrs.get('href'), '/approve');

  t.mock.timers.tick(1000);
  assert.equal(clock.textContent, 'Expired');
  assert.equal(attrs.get('aria-disabled'), 'true');
  assert.equal(attrs.has('href'), false);
});

test('polling refreshes at 5 s intervals only while visible and stops on removal', async (t) => {
  t.mock.timers.enable({ apis: ['setInterval'] });
  const { Kit, requests, document, remove } = loadOps();
  const el = { isConnected: true, innerHTML: '' };
  const advance = async (ms) => {
    t.mock.timers.tick(ms);
    await Promise.resolve();
    await Promise.resolve();
  };
  function setHidden(hidden) {
    document.hidden = hidden;
    document.dispatchEvent(new Event('visibilitychange'));
  }

  Kit.poll(el, '/tiles', 5000);
  await advance(4999);
  assert.equal(requests.length, 0);
  await advance(1);
  assert.equal(requests.length, 1);
  assert.equal(requests[0].url, '/tiles');
  assert.equal(el.innerHTML, '<strong>Updated</strong>');

  setHidden(true);
  await advance(10000);
  assert.equal(requests.length, 1);
  setHidden(false);
  await advance(5000);
  assert.equal(requests.length, 2);

  remove(el);
  await advance(5000);
  assert.equal(requests.length, 2);
});

test('hiding the tab aborts an in-flight poll and discards its late response', async (t) => {
  t.mock.timers.enable({ apis: ['setInterval'] });
  const { Kit, document, window } = loadOps();
  const el = { isConnected: true, innerHTML: 'Original' };
  let signal;
  let finish;
  window.fetch = (url, options) => {
    signal = options.signal;
    return new Promise(resolve => { finish = resolve; });
  };
  Kit.poll(el, '/tiles', 5000);
  t.mock.timers.tick(5000);
  document.hidden = true;
  document.dispatchEvent(new Event('visibilitychange'));
  assert.equal(signal.aborted, true);
  finish({ ok: true, text: async () => 'Stale' });
  await Promise.resolve();
  await Promise.resolve();
  assert.equal(el.innerHTML, 'Original');
});
