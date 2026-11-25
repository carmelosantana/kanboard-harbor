(function (window, document) {
  'use strict';

  const drawn = new WeakSet();
  const svgNamespace = 'http://www.w3.org/2000/svg';

  function svgElement(tag, attributes) {
    const node = document.createElementNS(svgNamespace, tag);
    for (const [name, value] of Object.entries(attributes)) node.setAttribute(name, value);
    return node;
  }

  function extent(values) {
    let min = Infinity;
    let max = -Infinity;
    for (const value of values) {
      if (!Number.isFinite(value)) continue;
      min = Math.min(min, value);
      max = Math.max(max, value);
    }
    return { min, max };
  }

  function yScale(min, max, top, bottom) {
    if (min === max) return () => (top + bottom) / 2;
    return value => bottom - (value - min) / (max - min) * (bottom - top);
  }

  function drawSparkline(el) {
    if (drawn.has(el)) return;
    const values = JSON.parse(el.dataset.points);
    if (!values.length) return;

    const { min, max } = extent(values);
    const y = yScale(min, max, 2, 30);
    const points = values.length === 1
      ? '2,16 118,16'
      : values.map((value, index) => [2 + index * 116 / (values.length - 1), y(value)]).join(' ');
    const svg = svgElement('svg', { viewBox: '0 0 120 32', preserveAspectRatio: 'none', 'aria-hidden': 'true' });
    svg.append(svgElement('polyline', { points, fill: 'none', 'stroke-width': 2, 'vector-effect': 'non-scaling-stroke' }));
    el.append(svg);
    drawn.add(el);
  }

  function drawLineChart(el) {
    if (drawn.has(el)) return;
    const series = JSON.parse(el.dataset.series);
    const labels = JSON.parse(el.dataset.labels);
    const { min, max } = extent(series.flatMap(entry => entry.values));
    if (!labels.length || min === Infinity) return;

    const width = 600;
    const height = 200;
    const left = 72;
    const right = width - 10;
    const top = 12;
    const bottom = height - 12;
    const lastIndex = labels.length - 1;
    const step = lastIndex ? (right - left) / lastIndex : 0;
    const x = index => lastIndex ? left + step * index : (left + right) / 2;
    const y = yScale(min, max, top, bottom);
    const svg = svgElement('svg', {
      viewBox: `0 0 ${width} ${height}`,
      preserveAspectRatio: 'none',
      tabindex: 0,
      role: 'group',
      'aria-label': el.dataset.chartDescription || 'Line chart',
    });

    svg.append(svgElement('line', { x1: left, x2: left, y1: top, y2: bottom, 'data-chart-grid': '' }));
    const ticks = min === max ? [min] : [min, min + (max - min) / 2, max];
    for (const tick of ticks) {
      svg.append(svgElement('line', { x1: left, x2: right, y1: y(tick), y2: y(tick), 'data-chart-grid': '' }));
      const label = svgElement('text', { x: left - 6, y: y(tick), 'text-anchor': 'end', 'dominant-baseline': 'middle', 'aria-hidden': 'true' });
      label.textContent = String(Number(tick.toPrecision(4)));
      svg.append(label);
    }

    for (const entry of series) {
      const segments = [];
      let previous = false;
      entry.values.forEach((value, index) => {
        const present = Number.isFinite(value);
        if (present) {
          const point = `${x(index)} ${y(value)}`;
          segments.push(previous ? `L${point}` : `M${point}`);
          if (!previous && !Number.isFinite(entry.values[index + 1])) segments.push(`L${point}`);
        }
        previous = present;
      });
      svg.append(svgElement('path', {
        d: segments.join(' '),
        fill: 'none',
        'stroke-width': 2,
        'stroke-linecap': 'round',
        'vector-effect': 'non-scaling-stroke',
        'data-chart-color': entry.color,
      }));
    }

    const crosshair = svgElement('line', { y1: top, y2: bottom, visibility: 'hidden', 'data-chart-crosshair': '' });
    svg.append(crosshair);
    const tooltip = document.createElement('div');
    tooltip.setAttribute('data-chart-tooltip', '');
    tooltip.setAttribute('role', 'status');
    tooltip.hidden = true;
    el.append(svg, tooltip);
    drawn.add(el);

    let selected = 0;
    let focused = false;
    function show(index) {
      selected = Math.min(lastIndex, Math.max(0, index));
      if (!tooltip.hidden && crosshair.getAttribute('x1') === String(x(selected))) return;
      crosshair.setAttribute('x1', x(selected));
      crosshair.setAttribute('x2', x(selected));
      crosshair.setAttribute('visibility', 'visible');
      const label = document.createElement('strong');
      label.textContent = labels[selected];
      const rows = series.map(entry => {
        const row = document.createElement('div');
        const name = document.createElement('span');
        const value = document.createElement('span');
        name.textContent = entry.name;
        value.textContent = String(entry.values[selected] ?? '—');
        row.append(name, value);
        return row;
      });
      tooltip.hidden = false;
      tooltip.replaceChildren(label, ...rows);
    }

    function hide() {
      crosshair.setAttribute('visibility', 'hidden');
      tooltip.hidden = true;
    }

    svg.addEventListener('pointermove', event => {
      const bounds = svg.getBoundingClientRect();
      const plotX = (event.clientX - bounds.left) * width / bounds.width;
      show(step ? Math.round((plotX - left) / step) : 0);
    });
    svg.addEventListener('pointerleave', () => {
      if (!focused) hide();
    });
    svg.addEventListener('focus', () => {
      focused = true;
      show(selected);
    });
    svg.addEventListener('blur', () => {
      focused = false;
      hide();
    });
    svg.addEventListener('keydown', event => {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      event.preventDefault();
      show(selected + (event.key === 'ArrowRight' ? 1 : -1));
    });
  }

  window.Kit.drawLineChart = drawLineChart;
  window.Kit.drawSparkline = drawSparkline;

  function initialize() {
    document.querySelectorAll('[data-slot="line-chart"]').forEach(drawLineChart);
    document.querySelectorAll('[data-slot="sparkline"]').forEach(drawSparkline);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})(window, document);
