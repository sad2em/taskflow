/* ==========================================================================
   TaskFlow — Tiny SVG chart renderer (line, donut, bars) — zero dependencies
   Reads:  data-chart="line|donut|bars"  data-labels='[...]'  data-series='[...]'
   Series: { name, color, data:[numbers], colors?:[per-point colours] }
   ========================================================================== */
(function () {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';

    function el(name, attrs = {}, children = []) {
        const node = document.createElementNS(NS, name);
        Object.entries(attrs).forEach(([key, value]) => {
            if (value !== null && value !== undefined) node.setAttribute(key, value);
        });
        (Array.isArray(children) ? children : [children]).forEach((child) => {
            if (typeof child === 'string') node.appendChild(document.createTextNode(child));
            else if (child) node.appendChild(child);
        });
        return node;
    }

    function readData(node) {
        const parse = (attr) => {
            try { return JSON.parse(node.dataset[attr] || '[]'); } catch (e) { return []; }
        };
        return {
            type: node.dataset.chart,
            labels: parse('labels'),
            series: parse('series'),
            horizontal: node.classList.contains('horizontal')
        };
    }

    function tooltip() {
        let tip = document.getElementById('chartTooltip');
        if (!tip) {
            tip = document.createElement('div');
            tip.id = 'chartTooltip';
            tip.className = 'chart-tooltip';
            document.body.appendChild(tip);
        }
        return {
            show(html, x, y) {
                tip.innerHTML = html;
                tip.style.left = `${x}px`;
                tip.style.top = `${y}px`;
                tip.style.display = 'block';
            },
            hide() { tip.style.display = 'none'; }
        };
    }

    function niceMax(value) {
        if (value <= 4) return Math.max(4, Math.ceil(value));
        const magnitude = Math.pow(10, Math.floor(Math.log10(value)));
        const normalized = value / magnitude;
        const step = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;
        return step * magnitude;
    }

    /* ------------------------------ LINE ------------------------------ */

    function lineChart(node, { labels, series }) {
        const W = 820, H = 300;
        const pad = { top: 16, right: 18, bottom: 30, left: 34 };
        const innerW = W - pad.left - pad.right;
        const innerH = H - pad.top - pad.bottom;

        const allValues = series.flatMap((s) => s.data.map(Number));
        const max = niceMax(Math.max(1, ...allValues));
        const stepX = labels.length > 1 ? innerW / (labels.length - 1) : innerW;
        const x = (i) => pad.left + i * stepX;
        const y = (v) => pad.top + innerH - (v / max) * innerH;

        const svg = el('svg', { viewBox: `0 0 ${W} ${H}`, preserveAspectRatio: 'none', width: '100%', height: '100%' });
        const tip = tooltip();

        // grid + y labels
        const ticks = 4;
        for (let i = 0; i <= ticks; i++) {
            const value = (max / ticks) * i;
            const yy = y(value);
            svg.appendChild(el('line', { x1: pad.left, y1: yy, x2: W - pad.right, y2: yy, class: 'chart-grid-line', 'stroke-dasharray': i === 0 ? '' : '3 4' }));
            svg.appendChild(el('text', { x: pad.left - 8, y: yy + 3.5, 'text-anchor': 'end', class: 'chart-axis-label' }, Math.round(value).toString()));
        }

        // x labels (thinned out)
        const labelEvery = Math.max(1, Math.ceil(labels.length / 10));
        labels.forEach((label, i) => {
            if (i % labelEvery !== 0 && i !== labels.length - 1) return;
            svg.appendChild(el('text', { x: x(i), y: H - 8, 'text-anchor': 'middle', class: 'chart-axis-label' }, String(label)));
        });

        // areas + lines + dots
        series.forEach((s) => {
            const points = s.data.map((v, i) => `${x(i)},${y(Number(v))}`).join(' ');
            const areaPoints = `${pad.left},${pad.top + innerH} ${points} ${x(s.data.length - 1)},${pad.top + innerH}`;
            svg.appendChild(el('polygon', { points: areaPoints, fill: s.color, class: 'chart-area' }));
            svg.appendChild(el('polyline', { points, stroke: s.color, class: 'chart-line' }));
            s.data.forEach((v, i) => {
                svg.appendChild(el('circle', { cx: x(i), cy: y(Number(v)), r: 2.8, fill: s.color, class: 'chart-dot' }));
            });
        });

        // hover columns
        labels.forEach((label, i) => {
            const rect = el('rect', {
                x: x(i) - stepX / 2, y: pad.top, width: stepX, height: innerH,
                fill: 'transparent', style: 'cursor:crosshair'
            });
            rect.addEventListener('mouseenter', (e) => tip.show(tipHTML(label, series, i), e.clientX, e.clientY));
            rect.addEventListener('mousemove', (e) => tip.show(tipHTML(label, series, i), e.clientX, e.clientY));
            rect.addEventListener('mouseleave', () => tip.hide());
            svg.appendChild(rect);
        });

        node.innerHTML = '';
        node.appendChild(svg);
    }

    function tipHTML(label, series, i) {
        return `<small>${label}</small>` + series.map((s) =>
            `<div style="display:flex;align-items:center;gap:6px"><i style="width:8px;height:8px;border-radius:2px;background:${s.color};display:inline-block"></i>${s.name}: <strong>${s.data[i]}</strong></div>`
        ).join('');
    }

    /* ------------------------------ DONUT ------------------------------ */

    function donutChart(node, { series }) {
        const total = series.reduce((sum, s) => sum + Number(s.value || 0), 0);
        const W = 120, H = 120, R = 42, C = 2 * Math.PI * R;
        const svg = el('svg', { viewBox: `0 0 ${W} ${H}`, width: '100%', height: '100%' });
        const tip = tooltip();

        svg.appendChild(el('circle', { cx: W / 2, cy: H / 2, r: R, fill: 'none', stroke: 'currentColor', 'stroke-opacity': '.08', 'stroke-width': 17 }));

        if (total === 0) {
            node.innerHTML = '';
            node.appendChild(svg);
            return;
        }

        let offset = 0;
        series.forEach((s) => {
            const value = Number(s.value || 0);
            if (value <= 0) return;
            const fraction = value / total;
            const dash = fraction * C;
            const circle = el('circle', {
                cx: W / 2, cy: H / 2, r: R, fill: 'none',
                stroke: s.color, 'stroke-width': 17, 'stroke-linecap': 'butt',
                'stroke-dasharray': `${dash} ${C - dash}`,
                'stroke-dashoffset': -offset,
                transform: `rotate(-90 ${W / 2} ${H / 2})`,
                class: 'donut-slice'
            });
            circle.addEventListener('mouseenter', (e) => {
                tip.show(`<strong>${s.name}</strong><small>${value} tasks · ${Math.round(fraction * 100)}%</small>`, e.clientX, e.clientY);
            });
            circle.addEventListener('mousemove', (e) => {
                tip.show(`<strong>${s.name}</strong><small>${value} tasks · ${Math.round(fraction * 100)}%</small>`, e.clientX, e.clientY);
            });
            circle.addEventListener('mouseleave', () => tip.hide());
            svg.appendChild(circle);
            offset += dash;
        });

        node.innerHTML = '';
        node.appendChild(svg);
    }

    /* ------------------------------ BARS ------------------------------ */

    function barChart(node, { labels, series, horizontal }) {
        const groups = labels.length;
        if (!groups) {
            node.innerHTML = '<p class="inline-empty">No data for this period.</p>';
            return;
        }

        if (horizontal) {
            const max = niceMax(Math.max(1, ...series.flatMap((s) => s.data.map(Number))));
            const wrap = document.createElement('div');
            wrap.className = 'hbar-list';
            labels.forEach((label, i) => {
                const row = document.createElement('div');
                row.className = 'hbar-row';
                row.innerHTML = `<span class="hbar-label" title="${label}">${label}</span><div class="hbar-track">`;
                const track = row.querySelector('.hbar-track');
                series.forEach((s) => {
                    const value = Number(s.data[i] || 0);
                    const bar = document.createElement('span');
                    bar.className = 'hbar-fill';
                    bar.style.width = `${(value / max) * 100}%`;
                    bar.style.background = s.color;
                    bar.title = `${s.name}: ${value}`;
                    track.appendChild(bar);
                });
                const total = series.reduce((sum, s) => sum + Number(s.data[i] || 0), 0);
                row.innerHTML += `</div><strong class="hbar-value">${total}</strong>`;
                wrap.appendChild(row);
            });
            node.innerHTML = '';
            node.appendChild(wrap);
            return;
        }

        const W = 760, H = 280;
        const pad = { top: 16, right: 12, bottom: 34, left: 34 };
        const innerW = W - pad.left - pad.right;
        const innerH = H - pad.top - pad.bottom;
        const max = niceMax(Math.max(1, ...series.flatMap((s) => s.data.map(Number))));

        const groupWidth = innerW / groups;
        const barWidth = Math.min(26, (groupWidth * 0.62) / series.length);
        const y = (v) => pad.top + innerH - (v / max) * innerH;

        const svg = el('svg', { viewBox: `0 0 ${W} ${H}`, width: '100%', height: '100%', preserveAspectRatio: 'none' });
        const tip = tooltip();

        for (let i = 0; i <= 4; i++) {
            const value = (max / 4) * i;
            const yy = y(value);
            svg.appendChild(el('line', { x1: pad.left, y1: yy, x2: W - pad.right, y2: yy, class: 'chart-grid-line', 'stroke-dasharray': i === 0 ? '' : '3 4' }));
            svg.appendChild(el('text', { x: pad.left - 8, y: yy + 3.5, 'text-anchor': 'end', class: 'chart-axis-label' }, Math.round(value).toString()));
        }

        const labelEvery = Math.max(1, Math.ceil(groups / 14));
        labels.forEach((label, i) => {
            const groupStart = pad.left + i * groupWidth;
            const totalWidth = barWidth * series.length + (series.length - 1) * 3;
            series.forEach((s, si) => {
                const value = Number(s.data[i] || 0);
                const color = (s.colors && s.colors[i]) || s.color;
                const barH = Math.max(value > 0 ? 2 : 0, (value / max) * innerH);
                const rect = el('rect', {
                    x: groupStart + (groupWidth - totalWidth) / 2 + si * (barWidth + 3),
                    y: pad.top + innerH - barH,
                    width: barWidth, height: barH, rx: Math.min(4, barWidth / 2),
                    fill: color, class: 'bar-rect'
                });
                rect.addEventListener('mouseenter', (e) => tip.show(`<strong>${s.name}: ${value}</strong><small>${label}</small>`, e.clientX, e.clientY));
                rect.addEventListener('mousemove', (e) => tip.show(`<strong>${s.name}: ${value}</strong><small>${label}</small>`, e.clientX, e.clientY));
                rect.addEventListener('mouseleave', () => tip.hide());
                svg.appendChild(rect);
            });

            if (i % labelEvery === 0 || groups <= 8) {
                svg.appendChild(el('text', {
                    x: groupStart + groupWidth / 2, y: H - 10, 'text-anchor': 'middle', class: 'chart-axis-label'
                }, String(label).slice(0, 12)));
            }
        });

        node.innerHTML = '';
        node.appendChild(svg);

        // Legend for multi-series bars
        if (series.length > 1) {
            const legend = document.createElement('div');
            legend.className = 'legend';
            legend.style.marginTop = '10px';
            legend.style.justifyContent = 'center';
            legend.innerHTML = series.map((s) => `<span><i style="background:${s.color}"></i>${s.name}</span>`).join('');
            node.appendChild(legend);
        }
    }

    /* ------------------------------ Boot ------------------------------ */

    function renderAll(root = document) {
        root.querySelectorAll('.chart-container[data-chart]').forEach((node) => {
            if (node.dataset.rendered === '1') return;
            const data = readData(node);
            try {
                if (data.type === 'line') lineChart(node, data);
                else if (data.type === 'donut') donutChart(node, data);
                else if (data.type === 'bars') barChart(node, data);
                node.dataset.rendered = '1';
            } catch (err) {
                node.innerHTML = '<p class="inline-empty">Chart could not be rendered.</p>';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => renderAll());
    window.addEventListener('resize', (() => {
        let timer;
        return () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                document.querySelectorAll('.chart-container[data-rendered="1"]').forEach((n) => { n.dataset.rendered = ''; });
                renderAll();
            }, 260);
        };
    })());

    window.TaskFlowCharts = { renderAll };
})();
