(function (window, document) {
  'use strict';

  const Kit = window.Kit;
  const stages = new WeakMap();
  const confirms = new WeakMap();
  const stageSlot = '[data-slot="stage"]';
  const confirmSlot = '[data-slot="typed-confirm"]';

  function floatControls(stage) {
    if (stages.has(stage)) return stages.get(stage);

    const activity = ['pointermove', 'pointerdown', 'keydown', 'focusin'];
    let timeout;

    function wake() {
      window.clearTimeout(timeout);
      stage.setAttribute('data-state', 'active');
      if (stage.hasAttribute('data-playing')) {
        timeout = window.setTimeout(() => {
          if (stage.hasAttribute('data-playing')) stage.setAttribute('data-state', 'idle');
        }, 3000);
      }
    }

    for (const event of activity) stage.addEventListener(event, wake);
    const observer = new window.MutationObserver(wake);
    observer.observe(stage, { attributeFilter: ['data-playing'] });
    wake();

    function cleanup() {
      window.clearTimeout(timeout);
      observer.disconnect();
      for (const event of activity) stage.removeEventListener(event, wake);
      stage.setAttribute('data-state', 'active');
      stages.delete(stage);
    }

    stages.set(stage, cleanup);
    return cleanup;
  }

  function bindConfirm(root) {
    const form = root.querySelector('form');
    const input = form?.querySelector('[name="confirmation"]');
    const button = form?.querySelector('button[type="submit"]');
    const phrase = root.getAttribute('data-phrase');
    if (!input || !button || !phrase) return;

    let acceptedClick = false;
    let popup;

    input.addEventListener('input', (event) => {
      button.disabled = !event.isTrusted || input.value !== phrase;
    });
    button.addEventListener('click', (event) => {
      if (!event.isTrusted || button.disabled || input.value !== phrase) {
        event.preventDefault();
        return;
      }

      // The native click submits synchronously. Expire its permission if no submit follows.
      acceptedClick = true;
      window.setTimeout(() => { acceptedClick = false; }, 0);
    });
    form.addEventListener('submit', (event) => {
      if (!acceptedClick || !event.isTrusted || event.submitter !== button || input.value !== phrase) {
        event.preventDefault();
      }
      acceptedClick = false;
    });
    root.querySelector('[data-confirm-cancel]')?.addEventListener('click', () => popup?.close());

    return (anchor) => {
      if (root.getAttribute('data-state') === 'open') return;
      input.value = '';
      button.disabled = true;
      if (popup) {
        popup.open();
      } else {
        popup = Kit.popup(anchor, root, { preset: 'dialog' });
      }
    };
  }

  function typedConfirm(root, anchor) {
    if (!confirms.has(root)) confirms.set(root, bindConfirm(root));
    if (anchor) confirms.get(root)?.(anchor);
  }

  Kit.floatControls = floatControls;
  Kit.typedConfirm = typedConfirm;

  function initialize() {
    document.querySelectorAll(stageSlot).forEach(floatControls);
    document.querySelectorAll(confirmSlot).forEach((root) => typedConfirm(root));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})(window, document);
