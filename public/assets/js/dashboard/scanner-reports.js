/* Builds the dashboard batch pane's charts (rollout by day, cumulative served)
   from the #reportsData JSON block and exposes window.ReportsCharts.update(data)
   so a poller can repaint them live without a page reload. No-ops when chart.js
   or the canvases are absent.

   update(data) also repaints everything else under the "Last updated" stamp
   that a fresh distribution/reports/stats payload carries: the batch cards,
   the bar, the Stations table (see stationsTable() below) and, through
   window.BatchHeatmap.render(), the peak-hours grid and the day-filtered KPIs.
   All values are written with textContent, never innerHTML, so nothing here
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

    // Counts the server printed through number_format, so a repaint does not
    // turn "1,204" into "1204" halfway through a batch. Percentages and labels
    // keep going through text() unchanged.
    function count(id, value) {
        text(id, typeof value === 'number' ? value.toLocaleString() : value);
    }

    // Progress headline + bar, from fresh.coverage (SubsidyStatsModel::coverage()'s
    // shape: eligible/served/remaining/coverage/voided).
    function applyCoverage(coverage) {
        if (!coverage) { return; }
        count('progressServed', coverage.served);
        count('progressEligible', coverage.eligible);
        text('progressCoverage', coverage.coverage);

        var bar = document.getElementById('coverageProgress');
        var fill = document.getElementById('coverageProgressFill');
        if (bar) { bar.setAttribute('aria-valuenow', String(coverage.coverage)); }
        if (fill) { fill.style.width = coverage.coverage + '%'; }

        count('remainingTileValue', coverage.remaining);

        var voidedWrap = document.getElementById('voidedTileWrap');
        if (voidedWrap) {
            count('voidedTileValue', coverage.voided);
            voidedWrap.classList.toggle('d-none', !(coverage.voided > 0));
        }
    }

    // Matches ViewFormatter::duration() exactly, so a repaint prints the same
    // wording the server would have for the same figure.
    function duration(seconds) {
        if (seconds <= 0) { return '-'; }
        if (seconds < 60) { return seconds + ' s'; }
        if (seconds < 300) {
            var minutes = Math.floor(seconds / 60);
            var rest = seconds % 60;

            return rest === 0 ? minutes + ' m' : minutes + ' m ' + rest + ' s';
        }
        if (seconds < 3600) { return Math.floor(seconds / 60) + ' m'; }

        var hours = Math.floor(seconds / 3600);
        var restMinutes = Math.floor((seconds % 3600) / 60);

        return restMinutes === 0 ? hours + ' h' : hours + ' h ' + restMinutes + ' m';
    }

    // Matches PHP's date('ga', mktime($hour, 0)): hour without a leading
    // zero, lowercase am/pm, no space. mktime() rolls hour 24 over into the
    // next day's midnight, so a Best hour of 23 ranges to "12am", not
    // "12pm" - wrap the hour first, the same rollover, before deriving am/pm
    // from it. (This duplicates batch-heatmap.js's hourLabel(); see that
    // file's header for why the two are not shared, which is also why this
    // fix landed in both places.)
    function hourLabel(hour) {
        var normalized = ((hour % 24) + 24) % 24;
        var h = normalized % 12;
        if (h === 0) { h = 12; }

        return h + (normalized < 12 ? 'am' : 'pm');
    }

    function timeLabel(unixSeconds) {
        var d = new Date(unixSeconds * 1000);
        var h = d.getHours() % 12;
        if (h === 0) { h = 12; }
        var minutes = d.getMinutes();

        return h + ':' + (minutes < 10 ? '0' : '') + minutes + (d.getHours() < 12 ? 'am' : 'pm');
    }

    // Rebuilds #stationsTable's body from a fresh byScanner fold, replacing
    // the squares grid's old repaint function. Mirrors
    // Admin/batch-stations-table.php column for column, including the role
    // gate: a row only carries data-scanner-id when the table's own
    // data-can-drill-in says this role may read scanner/stats, read fresh off
    // the table on every call rather than cached, so the gate can never end up
    // wider than what the server rendered.
    function stationsTable(byScanner) {
        var table = document.getElementById('stationsTable');
        if (!table || !Array.isArray(byScanner)) { return; }
        var tbody = table.querySelector('tbody');
        if (!tbody) { return; }

        var canDrillIn = table.getAttribute('data-can-drill-in') === '1';

        while (tbody.firstChild) { tbody.removeChild(tbody.firstChild); }

        byScanner.forEach(function (row) {
            var userId = Number(row.userID) || 0;
            var tr = document.createElement('tr');
            if (userId === 0) { tr.className = 'is-total'; }
            if (canDrillIn && userId > 0) {
                tr.setAttribute('data-scanner-id', String(userId));
                tr.setAttribute('data-scanner-name', String(row.scanner));
            }

            [
                row.scanner,
                Number(row.families || 0).toLocaleString(),
                Number(row.handouts || 0).toLocaleString(),
                row.pace === null || row.pace === undefined ? '-' : Number(row.pace).toFixed(0),
                row.typicalSeconds === null || row.typicalSeconds === undefined ? '-' : duration(Number(row.typicalSeconds)),
                row.firstTs === null || row.firstTs === undefined
                    ? '-'
                    : timeLabel(Number(row.firstTs)) + ' - ' + timeLabel(Number(row.lastTs)),
                duration(Number(row.idleSeconds || 0)),
                row.bestHour === null || row.bestHour === undefined
                    ? '-'
                    : hourLabel(Number(row.bestHour)) + ' - ' + hourLabel(Number(row.bestHour) + 1),
                Number((row.share || 0) * 100).toFixed(0) + '%'
            ].forEach(function (value) {
                var td = document.createElement('td');
                td.textContent = String(value);
                tr.appendChild(td);
            });

            tbody.appendChild(tr);
        });

        if (byScanner.length === 0) {
            var emptyRow = document.createElement('tr');
            emptyRow.id = 'stationsTableEmpty';
            var emptyCell = document.createElement('td');
            emptyCell.setAttribute('colspan', '9');
            emptyCell.className = 'text-muted';
            emptyCell.textContent = 'No station has logged a scan in this batch yet.';
            emptyRow.appendChild(emptyCell);
            tbody.appendChild(emptyRow);
        }
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
            stationsTable(fresh.byScanner);
            if (window.BatchHeatmap) { window.BatchHeatmap.render(fresh); }
        }
    };

    var downloadBtn = document.querySelector('.reports-download-btn');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function () {
            if (window.showToast) {
                window.showToast('Exporting report. The download will begin shortly...', 'primary', { delay: 6000 });
            }
        });
    }
})();
