/* My Bingwa Admin — tiny dependency-free SVG charts (no Chart.js).
   Reads a JSON spec from each element's data-chart attribute. Colours come from CSS
   custom properties so light/dark are always in step and validated (dataviz skill).
   Marks: 2px line, soft area, recessive grid, hover crosshair + tooltip. */
(function () {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  function el(name, attrs) {
    var e = document.createElementNS(NS, name);
    for (var k in attrs) e.setAttribute(k, attrs[k]);
    return e;
  }
  function cssVar(node, name) {
    return getComputedStyle(node).getPropertyValue(name).trim() || '#888';
  }

  function lineChart(host, spec) {
    var W = 640, H = 220, padL = 44, padR = 12, padT = 14, padB = 26;
    var labels = spec.labels || [];
    var values = spec.values || [];
    if (!values.length) { host.innerHTML = '<div class="empty small">No data for this period.</div>'; return; }
    var max = Math.max.apply(null, values); if (max <= 0) max = 1;
    var stroke = cssVar(host, '--chart-line'), fill = cssVar(host, '--chart-fill');
    var grid = cssVar(host, '--chart-grid'), axis = cssVar(host, '--chart-axis');
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img' });
    var iw = W - padL - padR, ih = H - padT - padB;
    var x = function (i) { return padL + (values.length === 1 ? iw / 2 : (iw * i) / (values.length - 1)); };
    var y = function (v) { return padT + ih - (ih * v) / max; };

    // gridlines + y labels (4 steps)
    for (var g = 0; g <= 4; g++) {
      var gy = padT + (ih * g) / 4;
      svg.appendChild(el('line', { x1: padL, y1: gy, x2: W - padR, y2: gy, stroke: grid, 'stroke-width': 1 }));
      var t = el('text', { x: padL - 8, y: gy + 4, 'text-anchor': 'end', 'font-size': 10, fill: axis });
      t.textContent = Math.round(max * (1 - g / 4)).toLocaleString();
      svg.appendChild(t);
    }
    // x labels
    labels.forEach(function (lab, i) {
      var t = el('text', { x: x(i), y: H - 6, 'text-anchor': 'middle', 'font-size': 10, fill: axis });
      t.textContent = lab; svg.appendChild(t);
    });
    // area + line
    var d = '', area = 'M' + x(0) + ',' + (padT + ih);
    values.forEach(function (v, i) { var cmd = (i ? 'L' : 'M') + x(i) + ',' + y(v); d += cmd; area += 'L' + x(i) + ',' + y(v); });
    area += 'L' + x(values.length - 1) + ',' + (padT + ih) + 'Z';
    svg.appendChild(el('path', { d: area, fill: fill, stroke: 'none' }));
    svg.appendChild(el('path', { d: d, fill: 'none', stroke: stroke, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));
    values.forEach(function (v, i) { svg.appendChild(el('circle', { cx: x(i), cy: y(v), r: 3, fill: stroke })); });

    // hover crosshair + tooltip
    var cross = el('line', { x1: 0, y1: padT, x2: 0, y2: padT + ih, stroke: axis, 'stroke-width': 1, 'stroke-dasharray': '3 3', opacity: 0 });
    svg.appendChild(cross);
    var tip = document.createElement('div'); tip.className = 'chart-tooltip'; host.appendChild(tip);
    var prefix = spec.prefix || '';
    svg.addEventListener('mousemove', function (ev) {
      var r = svg.getBoundingClientRect();
      var px = ((ev.clientX - r.left) / r.width) * W;
      var i = Math.round(((px - padL) / iw) * (values.length - 1));
      i = Math.max(0, Math.min(values.length - 1, i));
      cross.setAttribute('x1', x(i)); cross.setAttribute('x2', x(i)); cross.setAttribute('opacity', 1);
      tip.classList.add('show');
      tip.style.left = ((x(i) / W) * r.width) + 'px';
      tip.style.top = ((y(values[i]) / H) * r.height) + 'px';
      tip.textContent = labels[i] + ': ' + prefix + Math.round(values[i]).toLocaleString();
    });
    svg.addEventListener('mouseleave', function () { cross.setAttribute('opacity', 0); tip.classList.remove('show'); });
    host.appendChild(svg);
  }

  function donut(host, spec) {
    var segs = spec.segments || [];
    var total = segs.reduce(function (s, x) { return s + x.value; }, 0) || 1;
    var size = 168, r = 66, cx = size / 2, cy = size / 2, circ = 2 * Math.PI * r;
    var svg = el('svg', { viewBox: '0 0 ' + size + ' ' + size, role: 'img' });
    svg.appendChild(el('circle', { cx: cx, cy: cy, r: r, fill: 'none', stroke: cssVar(host, '--chart-grid'), 'stroke-width': 16 }));
    var offset = 0;
    segs.forEach(function (seg, i) {
      var frac = seg.value / total;
      var color = seg.color ? cssVar(host, seg.color) : cssVar(host, '--chart-' + ((i % 4) + 1));
      var c = el('circle', {
        cx: cx, cy: cy, r: r, fill: 'none', stroke: color, 'stroke-width': 16,
        'stroke-dasharray': (circ * frac - 2) + ' ' + (circ - circ * frac + 2),
        'stroke-dashoffset': -circ * offset, transform: 'rotate(-90 ' + cx + ' ' + cy + ')', 'stroke-linecap': 'round'
      });
      svg.appendChild(c);
      offset += frac;
    });
    if (spec.centerValue) {
      var v = el('text', { x: cx, y: cy - 2, 'text-anchor': 'middle', 'font-size': 20, 'font-weight': 700, fill: cssVar(host, '--text'), 'font-family': 'Outfit, sans-serif' });
      v.textContent = spec.centerValue; svg.appendChild(v);
      var l = el('text', { x: cx, y: cy + 16, 'text-anchor': 'middle', 'font-size': 10, fill: cssVar(host, '--chart-axis') });
      l.textContent = spec.centerLabel || ''; svg.appendChild(l);
    }
    host.appendChild(svg);
  }

  document.querySelectorAll('[data-chart]').forEach(function (host) {
    var spec;
    try { spec = JSON.parse(host.getAttribute('data-chart')); } catch (e) { return; }
    host.classList.add('chart');
    if (spec.type === 'donut') donut(host, spec); else lineChart(host, spec);
  });
})();
