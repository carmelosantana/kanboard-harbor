import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

function loadOps() {
  const document = Object.assign(new EventTarget(), {
    hidden: false,
    readyState: 'complete',
    querySelectorAll: () => [],
    createTextNode: textContent => ({ textContent }),
  });
  const sources = [];
  const observers = new Set();
  let observerConstructions = 0;
  const requests = [];
  const window = {
    Kit: {},
    EventSource: class extends EventTarget {
      static CONNECTING = 0;
      static CLOSED = 2;

      constructor(url) {
        super();
        this.url = url;
        this.closed = false;
        this.readyState = this.constructor.CONNECTING;
        sources.push(this);
      }
      close() { this.closed = true; }
    },
    MutationObserver: class {
      constructor(callback) {
        this.callback = callback;
        observerConstructions++;
      }
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
    get observerConstructions() { return observerConstructions; },
    get activeObservers() { return observers.size; },
    remove(el) {
      el.isConnected = false;
      for (const observer of observers) observer.callback([{ removedNodes: [el] }]);
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
    childNodes: [],
    scrollHeight: 200,
    clientHeight: 100,
    scrollTop: 100,
    append(node) {
      this.childNodes.push(node);
      this.scrollHeight += 20;
    },
    removeChild(node) {
      this.childNodes.splice(this.childNodes.indexOf(node), 1);
      this.scrollHeight -= 20;
    },
  });
  Object.defineProperties(body, {
    textContent: { get: () => body.childNodes.map(node => node.textContent ?? node).join('') },
    firstChild: { get: () => body.childNodes[0] },
  });
  let scrollTop = body.scrollTop;
  Object.defineProperty(body, 'scrollTop', {
    get: () => scrollTop,
    set: value => { scrollTop = Math.min(value, body.scrollHeight - body.clientHeight); },
  });
  const count = { dataset: { logCount: '%d lines' } };
  const button = () => Object.assign(new EventTarget(), {
    disabled: false,
    setAttribute(name, value) { this[name] = value; },
  });
  const follow = button();
  const pause = button();
  const jump = button();
  const status = { dataset: { logStatus: 'Disconnected' }, textContent: '' };
  const el = fixture({
    '[role="log"]': body,
    '[data-log-count]': count,
    '[data-log-follow]': follow,
    '[data-log-pause]': pause,
    '[data-log-jump]': jump,
    '[data-log-status]': status,
  }, { sseUrl: '/logs' });
  return { el, body, count, follow, pause, jump, status };
}

test('streams plain text and counts actual lines through a native reconnect', () => {
  const { Kit, sources } = loadOps();
  const { el, body, count, follow, pause, status } = logFixture();
  Kit.logViewer(el);
  Kit.logViewer(el);
  const [source] = sources;
  assert.equal(source.url, '/logs');
  source.dispatchEvent(new MessageEvent('message', { data: '\x1b[31merror\x1b[0m\nworking\n' }));
  source.dispatchEvent(new Event('error'));
  assert.equal(source.closed, false);
  assert.equal(status.textContent, '');
  assert.equal(follow.disabled, false);
  assert.equal(pause.disabled, false);
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

test('operations components share one removal observer and release it when idle', (t) => {
  t.mock.timers.enable({ apis: ['setInterval'] });
  const ops = loadOps();
  const elements = Array.from({ length: 9 }, (_, index) => {
    const { el } = logFixture();
    if (index % 2 === 0) ops.Kit.logViewer(el);
    else ops.Kit.poll(el, '/tiles');
    return el;
  });
  const action = new EventTarget();
  const approval = fixture({
    '[data-approval-countdown]': { textContent: '5:00', dataset: { approvalCountdown: 'Expired' } },
    '[data-approval-action]': action,
  }, { expiresAt: String(Math.ceil(Date.now() / 1000) + 300) });
  ops.Kit.approvalCountdown(approval);
  elements.push(approval);

  assert.equal(ops.observerConstructions, 1);
  ops.remove(elements.shift());
  assert.equal(ops.activeObservers, 1);
  elements.forEach(ops.remove);
  assert.equal(ops.activeObservers, 0);
});

test('retains the newest 5000 log lines and counts all received lines', () => {
  const { Kit, sources } = loadOps();
  const { el, body, count } = logFixture();
  Kit.logViewer(el);
  const append = data => sources[0].dispatchEvent(new MessageEvent('message', { data }));
  append(Array.from({ length: 4999 }, (_, index) => `line ${index + 1}`).join('\n'));
  for (let line = 5000; line <= 5005; line++) append(`line ${line}`);
  assert.equal(body.childNodes.length, 5000);
  assert.equal(body.firstChild.textContent, 'line 6\n');
  assert.equal(body.childNodes.at(-1).textContent, 'line 5005\n');
  assert.equal(count.textContent, '5005 lines');
});

test('trimming at the log cap keeps the same lines in view while paused', () => {
  const { Kit, sources } = loadOps();
  const { el, body, pause, jump } = logFixture();
  Kit.logViewer(el);
  const append = data => sources[0].dispatchEvent(new MessageEvent('message', { data }));
  append(Array.from({ length: 5000 }, (_, index) => `line ${index + 1}`).join('\n'));

  body.scrollTop = 203;
  body.dispatchEvent(new Event('scroll'));
  assert.equal(pause['aria-pressed'], 'true');

  const visibleLine = () => body.childNodes[Math.floor(body.scrollTop / 20)].textContent;
  const before = visibleLine();
  append('line 5001\nline 5002');

  assert.equal(body.scrollTop, 163);
  assert.equal(visibleLine(), before);
  assert.equal(jump.hidden, false);
});

test('a terminal EventSource error shows the disconnected status and disables the controls', () => {
  const { Kit, sources } = loadOps();
  const { el, body, follow, pause, jump, status } = logFixture();
  Kit.logViewer(el);
  pause.dispatchEvent(new Event('click'));
  assert.equal(jump.hidden, false);
  const [source] = sources;
  source.readyState = source.constructor.CLOSED;
  source.dispatchEvent(new Event('error'));
  assert.equal(el.dataset.state, 'closed');
  assert.equal(status.textContent, 'Disconnected');
  assert.equal(follow.disabled, true);
  assert.equal(pause.disabled, true);
  assert.equal(jump.hidden, true);
  assert.equal(source.closed, true);
  source.dispatchEvent(new MessageEvent('message', { data: 'late delivery' }));
  assert.equal(body.textContent, '');
});

test('scrolling after a terminal error cannot show Jump to latest', () => {
  const { Kit, sources } = loadOps();
  const { el, body, jump } = logFixture();
  Kit.logViewer(el);
  const [source] = sources;
  source.readyState = source.constructor.CLOSED;
  source.dispatchEvent(new Event('error'));

  body.scrollTop = 80;
  body.dispatchEvent(new Event('scroll'));
  assert.equal(jump.hidden, true);
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
  assert.equal(requests.length, 2);
  await advance(4999);
  assert.equal(requests.length, 2);
  await advance(1);
  assert.equal(requests.length, 3);

  remove(el);
  await advance(5000);
  assert.equal(requests.length, 3);
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
