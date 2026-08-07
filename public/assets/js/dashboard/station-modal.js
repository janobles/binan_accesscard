/* Opens one station's figures over the dashboard's Stations grid, so an admin
   reading the batch never has to detour through the scanner kiosk shell.

   Reads scanner/stats, the same endpoint the kiosk polls, with ?scanner= and
   ?batch=. The click is delegated off #stationsGrid because the live poll
   rebuilds the squares every few seconds (scanner-reports.js applyStations),
   which would strip a handler bound to a square directly. Values are written
   with textContent, never innerHTML. Loads only where the modal was rendered,
   which is the roles that may read it. */
(function () {
    'use strict';

    var grid = document.getElementById('stationsGrid');
    var modalEl = document.getElementById('stationModal');
    if (!grid || !modalEl || !window.bootstrap) {
        return;
    }

    var modal = new window.bootstrap.Modal(modalEl);
    var statsUrl = modalEl.getAttribute('data-stats-url') || '';
    var title = document.getElementById('stationModalLabel');
    var errorEl = document.getElementById('stationModalError');
    var bodyEl = document.getElementById('stationModalBody');
    // Request sequence number. A second square clicked while the first is still
    // in flight must not have its numbers overwritten by the slower response.
    var latest = 0;

    var fields = {
        families: document.getElementById('stationModalFamilies'),
        handouts: document.getElementById('stationModalHandouts'),
        perHour: document.getElementById('stationModalPerHour'),
        busiest: document.getElementById('stationModalBusiest')
    };

    function set(values) {
        Object.keys(fields).forEach(function (key) {
            if (fields[key]) {
                fields[key].textContent = values[key];
            }
        });
    }

    function clear() {
        set({ families: '-', handouts: '-', perHour: '-', busiest: '-' });
        errorEl.classList.add('d-none');
        bodyEl.classList.remove('d-none');
    }

    function fail() {
        bodyEl.classList.add('d-none');
        errorEl.classList.remove('d-none');
    }

    grid.addEventListener('click', function (event) {
        var square = event.target.closest('.station-square[data-scanner-id]');
        if (!square || !grid.contains(square)) {
            return;
        }

        var scannerId = square.getAttribute('data-scanner-id');
        var batchId = grid.getAttribute('data-batch') || '0';

        title.textContent = 'Station ' + (square.getAttribute('data-scanner-name') || '');
        clear();
        modal.show();

        var ticket = ++latest;
        fetch(
            statsUrl + '?scanner=' + encodeURIComponent(scannerId) + '&batch=' + encodeURIComponent(batchId),
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        )
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (ticket !== latest) {
                    return;
                }
                if (!data) {
                    fail();
                    return;
                }
                var pace = data.pace || {};
                set({
                    families: String(data.families != null ? data.families : 0),
                    handouts: String(data.handouts != null ? data.handouts : 0),
                    perHour: String(pace.perHour != null ? pace.perHour : 0),
                    busiest: pace.busiest ? String(pace.busiest) : '-'
                });
            })
            .catch(function () {
                if (ticket === latest) {
                    fail();
                }
            });
    });
})();
