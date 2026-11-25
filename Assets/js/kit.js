(function (window, document) {
    'use strict';

    var menuItem = '[role="menuitem"]';
    var focusable = 'a[href], button, input, select, textarea, summary, [tabindex], [contenteditable="true"]';
    var popupStack = [];
    var popupRoles = { menu: 'menu', popover: 'dialog', dialog: 'dialog', tooltip: 'tooltip' };
    var toasterFocusOwner;
    var pendingSkeletons = new WeakMap();

    function skeleton(el, promise) {
        window.clearTimeout(pendingSkeletons.get(el));
        el.setAttribute('data-skeleton', 'waiting');
        var timer = window.setTimeout(function () {
            el.setAttribute('data-skeleton', 'visible');
        }, 300);
        pendingSkeletons.set(el, timer);

        return Promise.resolve(promise).finally(function () {
            if (pendingSkeletons.get(el) !== timer) return;
            window.clearTimeout(timer);
            pendingSkeletons.delete(el);
            el.setAttribute('data-skeleton', 'settled');
        });
    }

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

    var subtaskRow = '.subtasks-table tr[data-subtask-id]';
    var subtaskMeta = '[data-slot="subtask-row"]';
    var subtaskTrigger = '[data-slot="subtask-actions-trigger"]';
    var subtaskCheck = '.js-subtask-toggle-status, .subtask-title a.js-modal-confirm';
    var subtaskActions = { todo: 'subtask.todo', doing: 'subtask.start', done: 'subtask.done' };
    var subtaskIcons = { todo: 'fa-square-o', doing: 'fa-gears', done: 'fa-check-square-o' };
    var subtaskToasts = {
        todo: 'Subtask moved back to todo.',
        doing: 'Subtask started.',
        done: 'Subtask marked done.'
    };
    var lastSubtaskChange = new WeakMap();
    var subtaskMenu;
    var subtaskStartAllowed = null;

    function prepareSubtaskRows() {
        document.querySelectorAll(subtaskRow).forEach(function (row) {
            var metadata = row.querySelector(subtaskMeta);
            var trigger = row.querySelector(subtaskTrigger);
            var check = row.querySelector(subtaskCheck);
            if (metadata && trigger && check && metadata.dataset.actions) {
                row.dataset.state = metadata.dataset.state;
                trigger.hidden = false;
                check.setAttribute('aria-haspopup', 'menu');
            }
        });
    }

    function subtaskActionEnabled(action, state) {
        if (action.id === subtaskActions.doing && subtaskStartAllowed !== null) {
            return state === 'todo' && subtaskStartAllowed;
        }
        return action.states.includes(state);
    }

    function setSubtaskState(row, metadata, actions, state) {
        row.dataset.state = metadata.dataset.state = state;
        metadata.dataset.actions = actions.filter(function (action) {
            return subtaskActionEnabled(action, state);
        }).map(function (action) { return action.id; }).join(' ');
    }

    function updateSubtaskControls(row, state, running) {
        var checkbox = row.querySelector(subtaskCheck);
        var labels = row.querySelector(subtaskTrigger).dataset;
        var icon = checkbox.querySelector('i');
        icon.classList.remove(...Object.values(subtaskIcons));
        icon.classList.add(subtaskIcons[state]);
        checkbox.title = labels[state + 'Title'];

        var timer = row.querySelector('.js-subtask-toggle-timer');
        if (timer) {
            var url = new URL(timer.href);
            var next = running ? 'stop' : 'start';
            if (url.searchParams.get('timer') !== next) {
                url.searchParams.set('timer', next);
                timer.href = url.href;
                var timerIcon = timer.querySelector('i');
                timerIcon.classList.remove('fa-pause', 'fa-play-circle-o');
                timerIcon.classList.add(running ? 'fa-pause' : 'fa-play-circle-o');
                timer.replaceChildren(timerIcon, running ? labels.stopTimer : labels.startTimer);
                timer.parentNode.replaceChildren(timer);
            }
        }
    }

    async function postSubtaskStatus(row, metadata, actions, action, offerUndo) {
        var previous = metadata.dataset.state;
        if (previous === 'pending') return;

        setSubtaskState(row, metadata, actions, 'pending');
        row.setAttribute('aria-busy', 'true');

        var response;
        var result;
        try {
            response = await window.fetch(action.url, {
                method: 'POST',
                mode: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ csrf_token: action.csrf_token })
            });
            var startAllowed = response.headers?.get('X-Harbor-Can-Start-Subtask');
            if (startAllowed === '0' || startAllowed === '1') {
                subtaskStartAllowed = startAllowed === '1';
            }
            if (!response.ok) throw new Error('Subtask POST failed');
            result = await response.json();
            if (!Object.hasOwn(subtaskActions, result.status) || typeof result.timer !== 'boolean') {
                throw new Error('Invalid subtask response');
            }
        } catch {
            setSubtaskState(row, metadata, actions, previous);
            toast("Couldn't update the subtask. Try again.");
            return;
        } finally {
            row.removeAttribute('aria-busy');
        }

        setSubtaskState(row, metadata, actions, result.status);
        updateSubtaskControls(row, result.status, result.timer);
        lastSubtaskChange.set(row, result);
        var undo = offerUndo && actions.find(function (candidate) {
            return candidate.id === subtaskActions[previous];
        });
        toast(subtaskToasts[result.status], undo && {
            undo: function () {
                if (lastSubtaskChange.get(row) === result) postSubtaskStatus(row, metadata, actions, undo, false);
            }
        });
    }

    function openSubtaskMenu(event, contextMenu) {
        var row = event.target.closest(subtaskRow);
        if (!row) return;

        var metadata = row.querySelector(subtaskMeta);
        var check = row.querySelector(subtaskCheck);
        var anchor = contextMenu ? check : event.target.closest(subtaskCheck) || event.target.closest(subtaskTrigger);
        if (!metadata || !check || !anchor || (!metadata.dataset.actions && metadata.dataset.state !== 'pending')) return;

        // Core delegates checkbox clicks in the bubble phase.
        event.preventDefault();
        event.stopImmediatePropagation();
        if (metadata.dataset.state === 'pending') return;

        var actions = JSON.parse(metadata.querySelector('template').content.textContent);
        setSubtaskState(row, metadata, actions, metadata.dataset.state);
        var panel = document.createElement('div');
        if (subtaskMenu) {
            subtaskMenu.controls.close(false);
            subtaskMenu.panel.remove();
        }

        actions.forEach(function (action) {
            if (!Object.values(subtaskActions).includes(action.id)) return;

            var item = document.createElement('button');
            item.type = 'button';
            item.setAttribute('role', 'menuitem');
            item.textContent = action.label;
            item.disabled = !subtaskActionEnabled(action, metadata.dataset.state);
            item.setAttribute('aria-disabled', String(item.disabled));
            item.addEventListener('click', function () {
                if (item.disabled) return;
                controls.close();
                postSubtaskStatus(row, metadata, actions, action, true);
            });
            panel.append(item);
        });

        document.body.append(panel);
        var controls = popup(anchor, panel, { preset: 'menu' });
        subtaskMenu = { controls: controls, panel: panel };
    }

    var copyFeedback = new WeakMap();

    async function copyText(event) {
        var button = event.target.closest?.('[data-slot="copy-button"]');
        if (!button || button.dataset.copy === undefined || !window.navigator.clipboard) return;

        try {
            await window.navigator.clipboard.writeText(button.dataset.copy);
        } catch {
            return;
        }

        var previous = copyFeedback.get(button);
        var label = previous ? previous.label : button.textContent;
        if (previous) window.clearTimeout(previous.timer);
        button.textContent = button.dataset.copiedLabel || 'Copied';
        var timer = window.setTimeout(function () {
            button.textContent = label;
            copyFeedback.delete(button);
        }, 2000);
        copyFeedback.set(button, { label: label, timer: timer });
    }

    function openShortcutSheet(event) {
        if (event.key !== '?' || event.defaultPrevented || event.isComposing
            || event.ctrlKey || event.metaKey || event.altKey) return;
        if (event.target.isContentEditable || event.target.closest?.('input, textarea, select')) return;

        var sheet = document.querySelector('[data-slot="shortcut-sheet"]');
        if (!sheet || window.KB?.modal?.isOpen()) return;

        // Core listens on window for the same key.
        event.preventDefault();
        event.stopPropagation();
        if (!sheet.open) sheet.showModal();
    }

    if (document.readyState) {
        document.addEventListener('click', function (event) { openSubtaskMenu(event, false); }, true);
        document.addEventListener('contextmenu', function (event) { openSubtaskMenu(event, true); }, true);
        document.addEventListener('click', copyText);
        document.addEventListener('click', function (event) {
            event.target.closest?.('[data-shortcut-close]')?.closest('[data-slot="shortcut-sheet"]')?.close();
        });
        document.addEventListener('keydown', openShortcutSheet);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', prepareSubtaskRows);
        } else {
            prepareSubtaskRows();
        }
        if (window.KB) window.KB.on('subtasks.reloaded', prepareSubtaskRows);
    }

    window.Kit = { popup: popup, roving: roving, toast: toast, skeleton: skeleton };
})(window, document);
