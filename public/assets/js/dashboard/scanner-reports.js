/* Builds the dashboard batch pane's charts (rollout by day, cumulative served)
   from the #reportsData JSON block and exposes window.ReportsCharts.update(data)
   so a poller can repaint them live without a page reload. No-ops when chart.js
   or the canvases are absent.

   update(data) also repaints everything else under the "Last updated" stamp
   that a fresh distribution/reports/stats payload carries: the batch cards and
   bar, and the Stations grid. All values are written with textContent, never
   innerHTML, so nothing here needs a separate escaping step. */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') {
        return;
    }
    var el = document.getElementById('reportsData');
    if (!el) {
        return;
    }

    var data;
    try {
        data = JSON.parse(el.textContent || '{}');
    } catch (e) {
        return;
    }

    function ctx(id) {
        var c = document.getElementById(id);
        return c ? c.getContext('2d') : null;
    }

    // Chart colors come from theme.css CSS variables so the palette lives in one
    // place; fall back to the SB Admin 1 hex if a var is missing.
    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name);
        return (v && v.trim()) || fallback;
    }
    var palette = [
        cssVar('--chart-color-1', '#4e73df'),
        cssVar('--chart-color-2', '#e74a3b'),
        cssVar('--chart-color-3', '#f6c23e'),
        cssVar('--chart-color-4', '#1cc88a')
    ];
    var gridColor = cssVar('--chart-grid', '#eaecf4');

    var charts = {};

    // Cumulative served over time (open batch only - the canvas isn't in the
    // DOM for a closed batch). A flat tail means scanning stopped.
    var timeline = ctx('chartTimeline');
    if (timeline && Array.isArray(data.timeline)) {
        charts.timeline = new Chart(timeline, {
            type: 'line',
            data: {
                labels: data.timeline.map(function (t) { return t.label; }),
                datasets: [{
                    label: 'Families served',
                    data: data.timeline.map(function (t) { return t.cumulative; }),
                    borderColor: palette[3],
                    backgroundColor: palette[3],
                    tension: 0.2,
                    pointRadius: 2,
                    fill: false
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
                    x: { ticks: { autoSkip: true, maxTicksLimit: 6 }, grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // Families served per day. Bars rather than a line: these are discrete
    // days of a rollout, not a continuous series, and the shape of the drop
    // from day one is the thing worth seeing.
    var rollout = ctx('chartRollout');
    if (rollout && Array.isArray(data.byDay)) {
        charts.rollout = new Chart(rollout, {
            type: 'bar',
            data: {
                labels: data.byDay.map(function (d) { return d.label; }),
                datasets: [{
                    label: 'Families served',
                    data: data.byDay.map(function (d) { return d.served; }),
                    backgroundColor: palette[0]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                },
                onClick: function (evt, items) {
                    if (!items.length) { return; }
                    var row = data.byDay[items[0].index];
                    // Task 11's map listens for this and recolours to one day.
                    document.dispatchEvent(new CustomEvent('rollout:day', {
                        detail: { date: row ? row.date : null }
                    }));
                }
            }
        });
    }

    function text(id, value) {
        var node = document.getElementById(id);
        if (node) { node.textContent = String(value); }
    }

    // Progress headline + bar, from fresh.coverage (SubsidyStatsModel::coverage()'s
    // shape: eligible/served/remaining/coverage/voided).
    function applyCoverage(coverage) {
        if (!coverage) { return; }
        text('progressServed', coverage.served);
        text('progressEligible', coverage.eligible);
        text('progressCoverage', coverage.coverage);

        var bar = document.getElementById('coverageProgress');
        var fill = document.getElementById('coverageProgressFill');
        if (bar) { bar.setAttribute('aria-valuenow', String(coverage.coverage)); }
        if (fill) { fill.style.width = coverage.coverage + '%'; }

        text('remainingTileValue', coverage.remaining);

        var voidedWrap = document.getElementById('voidedTileWrap');
        if (voidedWrap) {
            text('voidedTileValue', coverage.voided);
            voidedWrap.classList.toggle('d-none', !(coverage.voided > 0));
        }
    }

    // Stations table (Stations tab only - the table isn't in the DOM on the
    // other two sub-tabs, so this is a no-op there).
    function applyStations(perScanner) {
        var table = document.getElementById('stationsTable');
        if (!table || !Array.isArray(perScanner)) { return; }
        var tbody = table.querySelector('tbody');
        if (!tbody) { return; }

        tbody.innerHTML = '';
        if (perScanner.length === 0) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 3;
            emptyCell.className = 'text-muted';
            emptyCell.textContent = 'No scans in this batch yet.';
            emptyRow.appendChild(emptyCell);
            tbody.appendChild(emptyRow);
            return;
        }
        perScanner.forEach(function (row) {
            var tr = document.createElement('tr');
            [row.scanner, row.families, row.handouts].forEach(function (value) {
                var td = document.createElement('td');
                td.textContent = String(value);
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }

    // Live update: repaint datasets and the surrounding page in place from a
    // fresh distribution/reports/stats payload.
    window.ReportsCharts = {
        update: function (fresh) {
            if (!fresh) { return; }
            if (charts.timeline && Array.isArray(fresh.timeline)) {
                charts.timeline.data.labels = fresh.timeline.map(function (t) { return t.label; });
                charts.timeline.data.datasets[0].data = fresh.timeline.map(function (t) { return t.cumulative; });
                charts.timeline.update();
            }
            if (charts.rollout && Array.isArray(fresh.byDay)) {
                charts.rollout.data.labels = fresh.byDay.map(function (d) { return d.label; });
                charts.rollout.data.datasets[0].data = fresh.byDay.map(function (d) { return d.served; });
                charts.rollout.update();
            }
            applyCoverage(fresh.coverage);
            applyStations(fresh.perScanner);
        }
    };
})();
