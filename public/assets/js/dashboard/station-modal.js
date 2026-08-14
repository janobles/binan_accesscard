/* Opens one station's figures over the dashboard's Stations table, so an admin
   reading the batch never has to detour through the scanner kiosk shell.

   Reads scanner/stats, the same endpoint the kiosk polls, with ?scanner= and
   ?batch=. The click is delegated off #stationsTable because the live poll
   rebuilds the rows every few seconds, which would strip a handler bound to a
   row directly. Only rows the server marked with data-scanner-id answer, which
   is how a role that may not read scanner/stats gets an inert table rather than
   a control that 403s. Values are written with textContent, never innerHTML.
   Loads only where the modal was rendered, which is the roles that may read
   it. */
(function () {
    'use strict';

    var table = document.getElementById('stationsTable');
    var modalEl = document.getElementById('stationModal');
    if (!table || !modalEl || !window.bootstrap) {
        return;
    }

    var modal = new window.bootstrap.Modal(modalEl);
    var statsUrl = modalEl.getAttribute('data-stats-url') || '';
    var title = document.getElementById('stationModalLabel');
    var errorEl = document.getElementById('stationModalError');
    var bodyEl = document.getElementById('stationModalBody');
    // Request sequence number. A second row clicked while the first is still
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

    table.addEventListener('click', function (event) {
        var row = event.target.closest('tr[data-scanner-id]');
        if (!row || !table.contains(row)) {
            return;
        }

        var scannerId = row.getAttribute('data-scanner-id');
        var batchId = table.getAttribute('data-batch') || '0';

        title.textContent = 'Station ' + (row.getAttribute('data-scanner-name') || '');
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
