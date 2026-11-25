import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

class Element extends EventTarget {
  constructor(tagName) {
    super();
    this.tagName = tagName;
    this.children = [];
    this.attributes = new Map();
    this.dataset = {};
    this.text = '';
  }

  setAttribute(name, value) { this.attributes.set(name, String(value)); }
  getAttribute(name) { return this.attributes.get(name); }
  append(...children) { this.children.push(...children); }
  replaceChildren(...children) { this.children = children; }
  set textContent(text) { this.text = text; this.children = []; }
  get textContent() { return this.text + this.children.map(element => element.textContent).join(' '); }
  getBoundingClientRect() { return { left: 30, width: 300 }; }

  find(predicate) {
    return this.children.flatMap(child => [
      ...(predicate(child) ? [child] : []),
      ...child.find(predicate),
    ]);
  }
}

const series = [
  { name: 'User', values: [5, 15, 20], color: '--chart-1' },
  { name: 'System', values: [25, 15, 0], color: '--chart-2' },
];

function chart() {
  const el = new Element('div');
  el.dataset.series = JSON.stringify(series);
  el.dataset.labels = JSON.stringify(['10:00', '10:05', '10:10']);
  return el;
}

function loadCharts(elements = {}) {
  const document = Object.assign(new EventTarget(), {
    readyState: 'loading',
    querySelectorAll: selector => elements[selector] ?? [],
    createElement: tag => new Element(tag),
    createElementNS: (namespace, tag) => new Element(tag),
  });
  const window = { Kit: {} };
  const source = readFileSync(new URL('../../Assets/js/kit-charts.js', import.meta.url), 'utf8');
  new Function('window', 'document', source)(window, document);
  document.dispatchEvent(new Event('DOMContentLoaded'));
  return window.Kit;
}

function dispatch(el, type, properties = {}) {
  const event = Object.assign(new Event(type, { cancelable: true }), properties);
  el.dispatchEvent(event);
  return event;
}

test('line paths share one y-scale and keep the assigned series colors', () => {
  const Kit = loadCharts();
  const el = chart();
  Kit.drawLineChart(el);
  Kit.drawLineChart(el);
  const paths = el.find(node => node.tagName === 'path');
  assert.equal(paths.length, 2);
  const coordinates = paths.map(path => path.getAttribute('d').match(/-?[\d.]+/g).map(Number));
  assert.ok(coordinates[0][1] > coordinates[1][1], '5 is below 25');
  assert.equal(coordinates[0][3], coordinates[1][3], 'both values of 15 share a y coordinate');
  assert.ok(coordinates[0][5] < coordinates[1][5], '20 is above 0');
  assert.deepEqual(paths.map(path => path.getAttribute('data-chart-color')), ['--chart-1', '--chart-2']);
});

test('keyboard focus and arrows show every series value and pointer movement snaps to the nearest x', () => {
  const el = chart();
  loadCharts({ '[data-slot="line-chart"]': [el] });
  const [svg] = el.find(node => node.tagName === 'svg');
  const [tooltip] = el.find(node => node.getAttribute('role') === 'status');
  const [crosshair] = svg.find(node => node.getAttribute('data-chart-crosshair') !== undefined);
  assert.equal(svg.getAttribute('tabindex'), '0');

  dispatch(svg, 'focus');
  assert.equal(tooltip.hidden, false);
  assert.match(tooltip.textContent, /10:00.*User.*5.*System.*25/);
  const first = Number(crosshair.getAttribute('x1'));
  assert.equal(dispatch(svg, 'keydown', { key: 'ArrowRight' }).defaultPrevented, true);
  assert.match(tooltip.textContent, /10:05.*User.*15.*System.*15/);
  const middle = Number(crosshair.getAttribute('x1'));
  assert.ok(middle > first);

  dispatch(svg, 'blur');
  assert.equal(tooltip.hidden, true);
  dispatch(svg, 'pointermove', { clientX: 190 });
  assert.equal(tooltip.hidden, false);
  assert.match(tooltip.textContent, /10:05.*User.*15.*System.*15/);
  assert.equal(Number(crosshair.getAttribute('x1')), middle);
  assert.equal(crosshair.getAttribute('x2'), crosshair.getAttribute('x1'));
  dispatch(svg, 'pointermove', { clientX: 320 });
  assert.match(tooltip.textContent, /10:10.*User.*20.*System.*0/);
  dispatch(svg, 'pointerleave');
  assert.equal(tooltip.hidden, true);
});

test('sparkline draws a two-pixel polyline with no axes', () => {
  const Kit = loadCharts();
  const el = new Element('span');
  el.dataset.points = '[5,1,3]';
  Kit.drawSparkline(el);
  const [svg] = el.children;
  assert.equal(svg.getAttribute('aria-hidden'), 'true');
  assert.deepEqual(svg.children.map(child => child.tagName), ['polyline']);
  const [line] = svg.children;
  assert.equal(line.getAttribute('stroke-width'), '2');
  assert.equal(line.getAttribute('vector-effect'), 'non-scaling-stroke');
  const points = line.getAttribute('points').split(' ').map(point => point.split(',').map(Number));
  assert.equal(points.length, 3);
  assert.ok(points[0][1] < points[2][1] && points[2][1] < points[1][1]);
});
