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
                    backgroundColor: ['#1cc88a', '#e74a3b']
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    }

    var barangay = ctx('chartBarangay');
    if (barangay && Array.isArray(data.barangay)) {
        new Chart(barangay, {
            type: 'bar',
            data: {
                labels: data.barangay.map(function (b) { return b.barangay; }),
                datasets: [{
                    label: 'Coverage %',
                    data: data.barangay.map(function (b) { return b.coverage; }),
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                indexAxis: 'y',
                scales: { x: { beginAtZero: true, max: 100 } },
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
                    backgroundColor: '#f6c23e'
                }]
            },
            options: {
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
    }
})();
