/* Builds the three Reports charts from the #reportsData JSON block and exposes
   window.ReportsCharts.update(data) so a poller can repaint them live without a
   page reload. No-ops when chart.js or the canvases are absent. */
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

    // Whole-number axis: never show fractional ticks for handout/family counts.
    var countScale = {
        beginAtZero: true,
        ticks: { precision: 0, stepSize: 1 },
        grid: { color: gridColor }
    };

    var charts = {};

    var received = ctx('chartReceived');
    if (received && data.received) {
        charts.received = new Chart(received, {
            type: 'pie',
            data: {
                labels: ['Received aid', 'Still waiting'],
                datasets: [{
                    data: [data.received.received || 0, data.received.notReceived || 0],
                    backgroundColor: [palette[3], palette[1]],
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    }

    function sortedBarangay(rows) {
        return (rows || []).slice().sort(function (a, b) {
            return (b.coverage - a.coverage) || (b.total - a.total);
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

    var aidType = ctx('chartAidType');
    if (aidType && Array.isArray(data.aidType)) {
        charts.aidType = new Chart(aidType, {
            type: 'bar',
            data: {
                labels: data.aidType.map(function (a) { return a.aid_type; }),
                datasets: [{
                    label: 'Handouts',
                    data: data.aidType.map(function (a) { return a.count; }),
                    backgroundColor: palette[2],
                    borderRadius: 4,
                    maxBarThickness: 64,
                    categoryPercentage: 0.6
                }]
            },
            options: {
                scales: { y: countScale, x: { grid: { display: false } } },
                plugins: { legend: { display: false } }
            }
        });
    }

    // Live update: repaint datasets in place from a fresh stats payload.
    window.ReportsCharts = {
        update: function (fresh) {
            if (!fresh) { return; }
            if (charts.received && fresh.received) {
                charts.received.data.datasets[0].data = [fresh.received.received || 0, fresh.received.notReceived || 0];
                charts.received.update();
            }
            if (charts.barangay && Array.isArray(fresh.barangay)) {
                var r = sortedBarangay(fresh.barangay);
                charts.barangay.data.labels = r.map(function (b) { return b.barangay; });
                charts.barangay.data.datasets[0].data = r.map(function (b) { return b.coverage; });
                charts.barangay.update();
            }
            if (charts.aidType && Array.isArray(fresh.aidType)) {
                charts.aidType.data.labels = fresh.aidType.map(function (a) { return a.aid_type; });
                charts.aidType.data.datasets[0].data = fresh.aidType.map(function (a) { return a.count; });
                charts.aidType.update();
            }
        }
    };
})();
