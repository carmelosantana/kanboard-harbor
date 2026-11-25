(function (window, document) {
  'use strict';

  const Kit = window.Kit;
  const breadcrumbLayouts = new WeakMap();
  const dateRanges = new WeakSet();
  const DAY = 86_400_000;
  const dayOffsets = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };

  function breadcrumbs(root) {
    if (breadcrumbLayouts.has(root)) {
      breadcrumbLayouts.get(root)();
      return;
    }

    const list = root.querySelector('ol');
    const toggle = root.querySelector('[data-breadcrumb-toggle]');
    if (!toggle) return;

    const [, overflow, ...rest] = list.children;
    const middle = rest.slice(0, -1);
    let expanded = false;

    function layout() {
      if (expanded) return;
      const focus = document.activeElement;
      middle.forEach(item => { item.hidden = false; });
      overflow.hidden = true;
      list.removeAttribute('data-breadcrumb-folded');

      const folded = list.scrollWidth > list.clientWidth;
      if (folded) {
        overflow.hidden = false;
        list.setAttribute('data-breadcrumb-folded', '');
        if (focus === toggle || middle.some(item => item.contains(focus))) toggle.focus();
        middle.forEach(item => { item.hidden = true; });
      } else if (focus === toggle) {
        focusMiddle();
      }
    }

    function focusMiddle() {
      const link = middle.map(item => item.querySelector('a')).find(Boolean);
      (link || list).focus();
    }

    toggle.addEventListener('click', () => {
      expanded = true;
      list.removeAttribute('data-breadcrumb-folded');
      middle.forEach(item => { item.hidden = false; });
      toggle.setAttribute('aria-expanded', 'true');
      focusMiddle();
      overflow.hidden = true;
    });

    breadcrumbLayouts.set(root, layout);
    if (window.ResizeObserver) new window.ResizeObserver(layout).observe(list);
    document.fonts?.ready.then(layout);
    layout();
  }

  function monthStart(day, offset = 0) {
    const date = new Date(day);
    date.setUTCDate(1);
    date.setUTCMonth(date.getUTCMonth() + offset);
    return date.getTime();
  }

  function dayStart(time) {
    return Math.floor(time / DAY) * DAY;
  }

  function isoDay(day) {
    return new Date(day).toISOString().split('T')[0];
  }

  function dateRange(root) {
    if (dateRanges.has(root)) return;
    dateRanges.add(root);

    const custom = root.querySelector('[data-range-custom]');
    const panel = root.querySelector('[data-range-panel]');
    const calendar = panel.querySelector('[data-range-months]');
    const apply = panel.querySelector('[data-range-apply]');
    const summary = panel.querySelector('[data-range-summary]');
    const url = new URL(window.location.href);
    const from = Date.parse(root.dataset.from || url.searchParams.get('from'));
    const to = Date.parse(root.dataset.to || url.searchParams.get('to'));
    const hasRange = Number.isFinite(from) && Number.isFinite(to) && from <= to;
    const locale = document.documentElement.lang || undefined;
    const format = options => new Intl.DateTimeFormat(locale, { timeZone: 'UTC', calendar: 'gregory', ...options });
    const monthLabel = format({ month: 'long', year: 'numeric' });
    const weekLabel = format({ weekday: 'short' });
    const dayLabel = format({ dateStyle: 'full' });
    const summaryLabel = format({ dateStyle: 'medium' });
    const dayButtons = new Map();
    let popup;
    let firstMonth;
    let activeDay;
    let start;
    let end;
    let edited;

    function navigate(fromTime, toTime) {
      const destination = new URL(window.location.href);
      destination.searchParams.set('from', new Date(fromTime).toISOString());
      destination.searchParams.set('to', new Date(toTime).toISOString());
      window.location.assign(destination.toString());
    }

    let presetSelected = false;
    for (const button of root.querySelectorAll('[data-range-preset]')) {
      const duration = Number(button.dataset.rangePreset) * 1000;
      const selected = hasRange && to - from === duration;
      button.setAttribute('aria-pressed', String(selected));
      presetSelected ||= selected;
      button.addEventListener('click', () => {
        const now = Date.now();
        navigate(now - duration, now);
      });
    }
    custom.setAttribute('aria-pressed', String(hasRange && !presetSelected));

    function renderMonth(firstDay) {
      const month = document.createElement('section');
      const title = document.createElement('h3');
      title.textContent = monthLabel.format(firstDay);
      const grid = document.createElement('div');
      grid.className = 'kit-range-grid';

      // The calendar starts on Monday. 1970-01-05 is a Monday in UTC.
      for (let weekday = 0; weekday < 7; weekday++) {
        const label = document.createElement('span');
        label.textContent = weekLabel.format((4 + weekday) * DAY);
        label.setAttribute('aria-hidden', 'true');
        grid.append(label);
      }

      const offset = (new Date(firstDay).getUTCDay() + 6) % 7;
      for (let blank = 0; blank < offset; blank++) grid.append(document.createElement('span'));

      const nextMonth = monthStart(firstDay, 1);
      for (let day = firstDay; day < nextMonth; day += DAY) {
        const button = document.createElement('button');
        button.type = 'button';
        button.tabIndex = day === activeDay ? 0 : -1;
        button.dataset.rangeDay = isoDay(day);
        button.textContent = String(new Date(day).getUTCDate());
        button.setAttribute('aria-label', dayLabel.format(day));
        if (day === start || day === end) {
          button.setAttribute('aria-pressed', 'true');
        } else {
          button.setAttribute('aria-pressed', 'false');
          if (start < day && day < end) button.setAttribute('data-in-range', '');
        }
        grid.append(button);
        dayButtons.set(day, button);
      }

      month.append(title, grid);
      return month;
    }

    function render() {
      dayButtons.clear();
      calendar.replaceChildren(renderMonth(firstMonth), renderMonth(monthStart(firstMonth, 1)));
      apply.disabled = end === undefined;
      if (summary) {
        summary.textContent = end !== undefined ? summaryLabel.formatRange(start, end)
          : start !== undefined ? summaryLabel.format(start) : '';
      }
    }

    function moveMonth(offset) {
      firstMonth = monthStart(firstMonth, offset);
      activeDay = firstMonth;
      render();
    }

    custom.addEventListener('click', () => {
      if (panel.getAttribute('data-state') === 'open') {
        popup.close();
        return;
      }

      start = hasRange ? dayStart(from) : undefined;
      end = hasRange ? dayStart(to) : undefined;
      edited = false;
      activeDay = start ?? dayStart(Date.now());
      firstMonth = monthStart(activeDay);
      render();
      if (popup) popup.open();
      else popup = Kit.popup(custom, panel, { preset: 'popover' });
      dayButtons.get(activeDay).focus();
    });

    panel.querySelector('[data-range-previous]').addEventListener('click', () => moveMonth(-1));
    panel.querySelector('[data-range-next]').addEventListener('click', () => moveMonth(1));
    panel.querySelector('[data-range-cancel]').addEventListener('click', () => popup.close());
    apply.addEventListener('click', () => {
      if (end === undefined) return;
      popup.close();
      navigate(edited ? start : from, edited ? end + DAY - 1 : to);
    });

    calendar.addEventListener('click', event => {
      const button = event.target.closest('[data-range-day]');
      if (!button) return;

      activeDay = Date.parse(button.dataset.rangeDay);
      if (start === undefined || end !== undefined) {
        start = activeDay;
        end = undefined;
      } else {
        end = Math.max(start, activeDay);
        start = Math.min(start, activeDay);
      }
      edited = true;
      render();
      dayButtons.get(activeDay).focus();
    });

    calendar.addEventListener('keydown', event => {
      const button = event.target.closest('[data-range-day]');
      if (!button || !Object.hasOwn(dayOffsets, event.key)) return;
      event.preventDefault();
      activeDay = Date.parse(button.dataset.rangeDay) + dayOffsets[event.key] * DAY;
      if (dayButtons.has(activeDay)) {
        button.tabIndex = -1;
        dayButtons.get(activeDay).tabIndex = 0;
      } else {
        firstMonth = activeDay < firstMonth ? monthStart(activeDay) : monthStart(activeDay, -1);
        render();
      }
      dayButtons.get(activeDay).focus();
    });
  }

  Kit.breadcrumbs = breadcrumbs;
  Kit.dateRange = dateRange;

  function initialize() {
    document.querySelectorAll('[data-slot="breadcrumbs"]').forEach(breadcrumbs);
    document.querySelectorAll('[data-slot="date-range"]').forEach(dateRange);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})(window, document);
