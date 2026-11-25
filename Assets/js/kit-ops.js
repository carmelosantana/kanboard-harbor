(function (window, document) {
  'use strict';

  const logs = new WeakSet();
  const approvals = new WeakSet();
  const polls = new WeakSet();
  const maxLogLines = 5000;
  const removalWatches = new Map();
  let removalObserver;

  function watchRemoval(el, stop) {
    removalObserver ??= new window.MutationObserver(records => {
      if (!records.some(record => record.removedNodes.length)) return;
      for (const [cleanup, element] of removalWatches) {
        if (!element.isConnected) cleanup();
      }
    });
    if (removalWatches.size === 0) {
      removalObserver.observe(document, { childList: true, subtree: true });
    }
    removalWatches.set(stop, el);
    return () => {
      removalWatches.delete(stop);
      if (removalWatches.size === 0) removalObserver.disconnect();
    };
  }

  function logViewer(el) {
    if (logs.has(el) || !el.isConnected || !el.dataset.sseUrl || !window.EventSource) return;

    const body = el.querySelector('[role="log"]');
    const count = el.querySelector('[data-log-count]');
    const follow = el.querySelector('[data-log-follow]');
    const pause = el.querySelector('[data-log-pause]');
    const jump = el.querySelector('[data-log-jump]');
    const status = el.querySelector('[data-log-status]');
    const source = new window.EventSource(el.dataset.sseUrl);
    logs.add(el);

    let following = true;
    let lines = 0;

    function setFollowing(value) {
      if (el.dataset.state === 'closed') return;
      following = value;
      follow.setAttribute('aria-pressed', String(value));
      pause.setAttribute('aria-pressed', String(!value));
      jump.hidden = value;
      if (value) body.scrollTop = body.scrollHeight;
    }

    body.addEventListener('scroll', () => {
      if (following && body.scrollHeight - body.clientHeight - body.scrollTop > 1) {
        setFollowing(false);
      }
    }, { passive: true });
    follow.addEventListener('click', () => setFollowing(true));
    pause.addEventListener('click', () => setFollowing(false));
    jump.addEventListener('click', () => setFollowing(true));
    setFollowing(true);

    function append(event) {
      // Strip ANSI control strings before processing shorter escape sequences.
      const text = event.data
        .replace(/(?:\x1b\]|\x9d)[^\x07\x1b\x9c]*(?:\x07|\x1b\\|\x9c)/g, '')
        .replace(/\x1b[P^_X][^\x1b\x9c]*(?:\x1b\\|\x9c)/g, '')
        .replace(/(?:\x1b\[|\x9b)[0-?]*[ -/]*[@-~]|\x1b[ -/]*[@-Z\\-_]/g, '')
        .replace(/\r\n?/g, '\n')
        .replace(/\n$/, '');
      const incoming = text.split('\n');
      for (const line of incoming.slice(-maxLogLines)) {
        body.append(document.createTextNode(line + '\n'));
      }
      if (body.childNodes.length > maxLogLines) {
        const heightBeforeTrim = following ? 0 : body.scrollHeight;
        const topBeforeTrim = following ? 0 : body.scrollTop;
        while (body.childNodes.length > maxLogLines) body.removeChild(body.firstChild);
        if (!following) {
          const removedHeight = heightBeforeTrim - body.scrollHeight;
          body.scrollTop = Math.max(0, topBeforeTrim - removedHeight);
        }
      }
      lines += incoming.length;
      count.textContent = count.dataset.logCount.replace(/%(?:1\$)?d/, lines);
      if (following) body.scrollTop = body.scrollHeight;
    }

    // EventSource handles transient errors and carries Last-Event-ID on reconnect.
    source.addEventListener('message', append);
    source.addEventListener('line', append);
    source.addEventListener('done', close);
    source.addEventListener('error', onError);
    const unwatch = watchRemoval(el, close);

    function onError() {
      if (source.readyState !== window.EventSource.CLOSED) return;
      el.dataset.state = 'closed';
      follow.disabled = true;
      pause.disabled = true;
      jump.hidden = true;
      status.textContent = status.dataset.logStatus;
      close();
    }

    function close() {
      source.removeEventListener('message', append);
      source.removeEventListener('line', append);
      source.removeEventListener('done', close);
      source.removeEventListener('error', onError);
      source.close();
      unwatch();
    }
  }

  function approvalCountdown(el) {
    if (approvals.has(el) || !el.isConnected) return;
    approvals.add(el);

    const clock = el.querySelector('[data-approval-countdown]');
    const action = el.querySelector('[data-approval-action]');
    const deadline = Number(el.dataset.expiresAt) * 1000;

    function secondsLeft() {
      return Math.ceil((deadline - Date.now()) / 1000);
    }

    function expire() {
      clock.textContent = clock.dataset.approvalCountdown;
      action.removeAttribute('href');
      action.setAttribute('role', 'link');
      action.setAttribute('aria-disabled', 'true');
    }

    action.addEventListener('click', event => {
      if (!event.isTrusted || !(secondsLeft() > 0)) event.preventDefault();
    });

    function render() {
      const seconds = secondsLeft();
      if (!(seconds > 0)) {
        expire();
        return false;
      }
      clock.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
      return true;
    }

    if (clock.textContent.trim() === clock.dataset.approvalCountdown) return;
    if (!render()) return;
    const timer = window.setInterval(() => {
      if (!render()) stop();
    }, 1000);
    const unwatch = watchRemoval(el, stop);

    function stop() {
      window.clearInterval(timer);
      unwatch();
    }
  }

  // The URL must serve a trusted, same-origin HTML fragment for this tile.
  function poll(el, url, interval = 5000) {
    if (polls.has(el) || !el.isConnected) return;
    polls.add(el);

    let timer;
    let pending;

    async function refresh() {
      if (document.hidden || !el.isConnected || pending) return;

      const request = new AbortController();
      pending = request;
      try {
        const response = await window.fetch(url, { mode: 'same-origin', cache: 'no-store', signal: request.signal });
        if (!response.ok) return;

        const html = await response.text();
        if (pending === request && el.isConnected && !document.hidden) el.innerHTML = html;
      } catch {
        // Keep the last successful content and retry on the next visible tick.
      } finally {
        if (pending === request) pending = undefined;
      }
    }

    function suspend() {
      window.clearInterval(timer);
      timer = undefined;
      pending?.abort();
      pending = undefined;
    }

    function updateVisibility(visibilityEvent) {
      if (document.hidden) {
        suspend();
      } else if (timer === undefined) {
        timer = window.setInterval(refresh, interval);
        if (visibilityEvent) refresh();
      }
    }

    document.addEventListener('visibilitychange', updateVisibility);
    const unwatch = watchRemoval(el, () => {
      suspend();
      document.removeEventListener('visibilitychange', updateVisibility);
      unwatch();
      polls.delete(el);
    });
    updateVisibility();
  }

  Object.assign(window.Kit, { logViewer, approvalCountdown, poll });

  function initialize() {
    document.querySelectorAll('[data-slot="log-viewer"]').forEach(logViewer);
    document.querySelectorAll('[data-slot="approval-card"]').forEach(approvalCountdown);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})(window, document);
