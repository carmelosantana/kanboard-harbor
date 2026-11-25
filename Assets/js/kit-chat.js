(function (window, document) {
  'use strict';

  const threads = new WeakSet();

  function chatThread(thread) {
    if (threads.has(thread)) return;
    threads.add(thread);

    let follow = true;
    function scrollToBottom() {
      thread.scrollTop = thread.scrollHeight;
    }

    scrollToBottom();
    thread.addEventListener('scroll', () => {
      follow = thread.scrollHeight - thread.clientHeight - thread.scrollTop <= 80;
    }, { passive: true });

    const observer = new window.MutationObserver(() => {
      if (follow) scrollToBottom();
    });
    observer.observe(thread, { childList: true, subtree: true });
  }

  window.Kit.chatThread = chatThread;

  document.addEventListener('keydown', (event) => {
    if (event.defaultPrevented || event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
    const textarea = event.target;
    if (!textarea.matches?.('textarea') || !textarea.form?.matches('[data-slot="composer"]')) return;

    event.preventDefault();
    textarea.form.requestSubmit();
  });

  function initialize() {
    document.querySelectorAll('[data-slot="chat-thread"]').forEach(chatThread);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})(window, document);
