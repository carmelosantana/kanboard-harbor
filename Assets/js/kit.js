(function (window, document) {
    'use strict';

    var menuItem = '[role="menuitem"]';
    var focusable = 'a[href], button, input, select, textarea, summary, [tabindex], [contenteditable="true"]';
    var popupStack = [];
    var popupRoles = { menu: 'menu', popover: 'dialog', dialog: 'dialog', tooltip: 'tooltip' };
    var toasterFocusOwner;

    function enabledMenuItem(item) {
        return item.getAttribute('aria-disabled') !== 'true' && !item.disabled && !item.hidden;
    }

    function roving(list) {
        var query = '';
        var lastTyped = 0;

        function setCurrent(items, current) {
            items.forEach(function (item) { item.tabIndex = item === current ? 0 : -1; });
        }

        function onKey(event) {
            if (event.defaultPrevented || event.ctrlKey || event.altKey || event.metaKey || event.isComposing) return;
            var all = Array.from(list.querySelectorAll(menuItem));
            var items = all.filter(enabledMenuItem);
            if (!items.length) return;
            var index = items.indexOf(document.activeElement);
            var next;

            switch (event.key) {
                case 'ArrowDown':
                case 'ArrowRight':
                    next = items[(index + 1) % items.length];
                    break;
                case 'ArrowUp':
                case 'ArrowLeft':
                    next = items[index <= 0 ? items.length - 1 : index - 1];
                    break;
                case 'Home':
                    next = items[0];
                    break;
                case 'End':
                    next = items[items.length - 1];
                    break;
                default:
                    if (event.key.length !== 1 || event.key === ' ') return;
                    var now = Date.now();
                    var letter = event.key.toLowerCase();
                    if (now - lastTyped >= 500) query = '';
                    // Repeating a single letter cycles through the matching items.
                    if (query !== letter) query += letter;
                    lastTyped = now;
                    var matches = items.slice(index + 1).concat(items.slice(0, index + 1));
                    next = matches.find(function (item) {
                        var label = item.getAttribute('aria-label') || item.textContent;
                        return label.trim().toLowerCase().startsWith(query);
                    });
            }

            if (next) {
                event.preventDefault();
                setCurrent(all, next);
                next.focus();
            }
        }

        var items = Array.from(list.querySelectorAll(menuItem));
        setCurrent(items, items.find(enabledMenuItem));
        list.addEventListener('keydown', onKey);
        return function () { list.removeEventListener('keydown', onKey); };
    }

    // Popup styles consume viewport coordinates via --popup-x and --popup-y.
    function placePopup(anchor, panel, preset) {
        var gap = 8;
        var viewportWidth = window.innerWidth;
        var viewportHeight = window.innerHeight;
        var trigger = anchor.getBoundingClientRect();
        var box = panel.getBoundingClientRect();
        var x = trigger.left;
        var y = trigger.bottom + gap;
        var above = trigger.top - box.height - gap;

        if (preset === 'dialog') {
            x = (viewportWidth - box.width) / 2;
            y = (viewportHeight - box.height) / 2;
        } else if (preset === 'tooltip' && above >= gap) {
            x = trigger.left + (trigger.width - box.width) / 2;
            y = above;
        } else if (y + box.height > viewportHeight - gap && above > viewportHeight - box.height - y) {
            y = above;
        }

        panel.style.setProperty('--popup-x', Math.max(gap, Math.min(x, viewportWidth - box.width - gap)) + 'px');
        panel.style.setProperty('--popup-y', Math.max(gap, Math.min(y, viewportHeight - box.height - gap)) + 'px');
    }

    // Opens immediately. The returned controls can close or reopen this panel.
    function popup(anchor, panel, options) {
        var preset = (options && options.preset) || 'popover';
        var dialog = preset === 'dialog';
        var tooltip = preset === 'tooltip';
        var nativeDialog = panel.tagName === 'DIALOG';
        var opened = false;
        var motion = 0;
        var frame;
        var stopRoving;
        var controls = { open: open, close: close };

        if (!panel.hasAttribute('data-slot')) panel.setAttribute('data-slot', preset);
        panel.setAttribute('data-popup-preset', preset);
        if (!panel.hasAttribute('role')) panel.setAttribute('role', popupRoles[preset] || 'dialog');
        if (dialog) panel.setAttribute('aria-modal', 'true');

        function isActive() {
            return popupStack[popupStack.length - 1] === controls;
        }

        function tabStops() {
            var selector = preset === 'menu' ? menuItem : focusable;
            return Array.from(panel.querySelectorAll(selector)).filter(function (item) {
                return item.tabIndex >= 0 && enabledMenuItem(item) && item.getClientRects().length > 0;
            });
        }

        function focusFirst() {
            var first = tabStops()[0] || panel;
            first.focus({ preventScroll: true });
        }

        function place() {
            placePopup(anchor, panel, preset);
        }

        function onKey(event) {
            if (tooltip) {
                if (event.key === 'Escape') close();
                return;
            }
            if (!isActive() || event.defaultPrevented) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close();
            } else if (dialog && event.key === 'Tab') {
                var items = tabStops();
                var index = items.indexOf(document.activeElement);
                var boundary = event.shiftKey ? 0 : items.length - 1;
                if (index === -1 || index === boundary) {
                    event.preventDefault();
                    var next = event.shiftKey ? items[items.length - 1] : items[0];
                    (next || panel).focus();
                }
            } else if (preset === 'menu' && event.key === 'Tab' && panel.contains(event.target)) {
                // Let the browser move focus before making the menu inert.
                var tabbing = motion;
                window.requestAnimationFrame(function () {
                    if (motion === tabbing) close(false);
                });
            }
        }

        function onFocus(event) {
            if (isActive() && !panel.contains(event.target)) focusFirst();
        }

        function onClick(event) {
            if ((tooltip || isActive()) && !anchor.contains(event.target) && !panel.contains(event.target)) {
                close(false);
            }
        }

        function open() {
            if (opened) return;
            opened = true;
            motion++;
            window.cancelAnimationFrame(frame);
            panel.removeAttribute('data-ending-style');
            panel.setAttribute('data-starting-style', '');
            panel.setAttribute('data-state', 'open');
            panel.inert = false;
            panel.hidden = false;
            if (nativeDialog) panel.setAttribute('open', '');
            if (!tooltip) {
                anchor.setAttribute('aria-expanded', 'true');
                if (!panel.hasAttribute('tabindex')) panel.tabIndex = -1;
            }

            place();
            if (!tooltip) popupStack.push(controls);
            document.addEventListener('keydown', onKey, true);
            document.addEventListener('click', onClick, true);
            window.addEventListener('resize', place);
            window.addEventListener('scroll', place, true);
            if (dialog) document.addEventListener('focusin', onFocus);
            if (preset === 'menu') stopRoving = roving(panel);
            if (!tooltip) focusFirst();

            frame = window.requestAnimationFrame(function () { panel.removeAttribute('data-starting-style'); });
        }

        function close(returnFocus) {
            if (!opened) return;
            var restoreFocus = returnFocus !== false && isActive();
            opened = false;
            var closing = ++motion;
            if (!tooltip) popupStack.splice(popupStack.indexOf(controls), 1);
            document.removeEventListener('keydown', onKey, true);
            document.removeEventListener('click', onClick, true);
            document.removeEventListener('focusin', onFocus);
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
            if (stopRoving) stopRoving();

            window.cancelAnimationFrame(frame);
            panel.removeAttribute('data-starting-style');
            panel.setAttribute('data-ending-style', '');
            panel.setAttribute('data-state', 'closed');
            panel.inert = true;
            if (!tooltip) anchor.setAttribute('aria-expanded', 'false');
            if (restoreFocus) anchor.focus({ preventScroll: true });

            function hide() {
                if (motion !== closing) return;
                panel.hidden = true;
                if (nativeDialog) panel.removeAttribute('open');
                panel.removeAttribute('data-ending-style');
            }

            frame = window.requestAnimationFrame(function () {
                var animations = panel.getAnimations ? panel.getAnimations() : [];
                var running = animations.filter(function (animation) {
                    return Number.isFinite(animation.effect.getComputedTiming().endTime);
                });
                if (running.length) {
                    Promise.allSettled(running.map(function (animation) { return animation.finished; })).then(hide);
                } else {
                    hide();
                }
            });
        }

        open();
        return controls;
    }

    function toast(text, options) {
        var region = document.querySelector('[data-slot="toaster"]');
        if (!region) {
            region = document.createElement('div');
            region.setAttribute('data-slot', 'toaster');
            region.setAttribute('role', 'status');
            document.body.append(region);
        }

        var notice = document.createElement('div');
        notice.setAttribute('data-slot', 'toast');
        var message = document.createElement('span');
        message.textContent = text;
        notice.append(message);

        var undo = options && options.undo;
        var remaining = 6000;
        var started;
        var timer;
        var hovered = false;
        var focused = false;
        var dismissed = false;

        function dismiss() {
            if (dismissed) return false;
            dismissed = true;
            clearTimeout(timer);
            notice.remove();
            return true;
        }

        function updateTimer() {
            if (dismissed) return;
            if (hovered || focused) {
                if (timer !== undefined) {
                    clearTimeout(timer);
                    timer = undefined;
                    remaining = Math.max(0, remaining - (Date.now() - started));
                }
            } else if (timer === undefined) {
                started = Date.now();
                timer = setTimeout(dismiss, remaining);
            }
        }

        if (typeof undo === 'function') {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Undo';
            button.addEventListener('click', function () {
                var previous = notice.contains(document.activeElement) && toasterFocusOwner;
                if (!dismiss()) return;
                if (previous && previous.isConnected) previous.focus({ preventScroll: true });
                undo();
            });
            notice.append(button);
        }

        notice.addEventListener('pointerenter', function () {
            hovered = true;
            updateTimer();
        });
        notice.addEventListener('pointerleave', function () {
            hovered = false;
            updateTimer();
        });
        notice.addEventListener('focusin', function (event) {
            if (!region.contains(event.relatedTarget)) toasterFocusOwner = event.relatedTarget;
            focused = true;
            updateTimer();
        });
        notice.addEventListener('focusout', function (event) {
            focused = notice.contains(event.relatedTarget);
            updateTimer();
        });

        region.append(notice);
        updateTimer();
    }

    window.Kit = { popup: popup, roving: roving, toast: toast };
})(window, document);
