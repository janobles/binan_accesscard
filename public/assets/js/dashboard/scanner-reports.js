/* Builds the dashboard batch zone's two charts (barangay coverage, cumulative
   served) from the #reportsData JSON block and exposes
   window.ReportsCharts.update(data) so a poller can repaint them live without
   a page reload. No-ops when chart.js or the canvases are absent.

   update(data) also repaints everything else under the "Last updated" stamp
   that a fresh distribution/reports/stats payload carries: the progress
   headline and bar, the Remaining/Voided tiles, and the Stations table. All
   values are written with textContent, never innerHTML, so nothing here
   needs a separate escaping step. */
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

    // Worst coverage first, matching SubsidyStatsModel::byBarangay() - the top
    // row of the chart is where staff get sent.
    function sortedBarangay(rows) {
        return (rows || []).slice().sort(function (a, b) {
            return (a.coverage - b.coverage) || (b.total - a.total);
        });
    }

    var barangay = ctx('chartBarangay');
    if (barangay && Array.isArray(data.barangay)) {
        var rows = sortedBarangay(data.barangay);
        charts.barangay = new Chart(barangay, {
            type: 'bar',
            data: {
                labels: rows.map(function (b) { return b.barangay; }),
                datasets: [{
                    label: 'Coverage %',
                    data: rows.map(function (b) { return b.coverage; }),
                    backgroundColor: palette[0],
                    borderRadius: 3,
                    barThickness: 12
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true, max: 100, ticks: { stepSize: 20 }, grid: { color: gridColor } },
                    y: { ticks: { autoSkip: false, font: { size: 11 } }, grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

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
            if (charts.barangay && Array.isArray(fresh.barangay)) {
                var r = sortedBarangay(fresh.barangay);
                charts.barangay.data.labels = r.map(function (b) { return b.barangay; });
                charts.barangay.data.datasets[0].data = r.map(function (b) { return b.coverage; });
                charts.barangay.update();
            }
            if (charts.timeline && Array.isArray(fresh.timeline)) {
                charts.timeline.data.labels = fresh.timeline.map(function (t) { return t.label; });
                charts.timeline.data.datasets[0].data = fresh.timeline.map(function (t) { return t.cumulative; });
                charts.timeline.update();
            }
            applyCoverage(fresh.coverage);
            applyStations(fresh.perScanner);
        }
    };
})();
