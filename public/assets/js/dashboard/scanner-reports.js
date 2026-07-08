/* Builds the three Reports-tab charts from the #reportsData JSON block.
   No-ops when chart.js or the canvases are absent (e.g. Scan/Manage pages). */
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

    var received = ctx('chartReceived');
    if (received && data.received) {
        new Chart(received, {
            type: 'doughnut',
            data: {
                labels: ['Received aid', 'Still waiting'],
                datasets: [{
                    data: [data.received.received || 0, data.received.notReceived || 0],
                    backgroundColor: ['#145c3b', '#dce5e2'],
                    borderWidth: 0
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    }

    var barangay = ctx('chartBarangay');
    if (barangay && Array.isArray(data.barangay)) {
        // Sort by coverage desc so the barangays that matter sit on top, and grow
        // the canvas height with the number of bars so labels never overlap.
        var rows = data.barangay.slice().sort(function (a, b) {
            return (b.coverage - a.coverage) || (b.total - a.total);
        });
        new Chart(barangay, {
            type: 'bar',
            data: {
                labels: rows.map(function (b) { return b.barangay; }),
                datasets: [{
                    label: 'Coverage %',
                    data: rows.map(function (b) { return b.coverage; }),
                    backgroundColor: '#145c3b',
                    borderRadius: 3,
                    barThickness: 12
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true, max: 100, ticks: { stepSize: 20 }, grid: { color: '#eef1f4' } },
                    y: { ticks: { autoSkip: false, font: { size: 11 } }, grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    var aidType = ctx('chartAidType');
    if (aidType && Array.isArray(data.aidType)) {
        new Chart(aidType, {
            type: 'bar',
            data: {
                labels: data.aidType.map(function (a) { return a.aid_type; }),
                datasets: [{
                    label: 'Handouts',
                    data: data.aidType.map(function (a) { return a.count; }),
                    backgroundColor: '#145c3b',
                    borderRadius: 3,
                    maxBarThickness: 90
                }]
            },
            options: {
                scales: { y: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 } } },
                plugins: { legend: { display: false } }
            }
        });
    }
})();
