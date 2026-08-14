/* Opens one station's figures over the dashboard's Stations table, so an admin
   reading the batch never has to detour through the scanner kiosk shell.

   Reads scanner/stats, the same endpoint the kiosk polls, with ?scanner= and
   ?batch=. The click is delegated off #stationsCard, the card that holds both
   the All and Per day panes, rather than one table's id: the live poll
   rebuilds the All table's rows every few seconds, which would strip a
   handler bound to a row directly, and the Per day pane renders a second
   table (its own id, batch-stations-table.php's $tableId) that this same
   listener has to answer too, since a card cannot bind twice to the row it
   opens the modal from. Matching on tr[data-scanner-id] rather than the
   nearest table is what makes one listener correct for both. Only rows the
   server marked with data-scanner-id answer, which is how a role that may not
   read scanner/stats gets an inert table rather than a control that 403s.
   Values are written with textContent, never innerHTML. Loads only where the
   modal was rendered, which is the roles that may read it.

   The modal body is Scanner/_metrics-grid.php, the same partial the kiosk
   performance page renders, so the fields are addressed by [data-metric]
   rather than by element id: that survives the grid being reordered or a
   line being added without a matching id being invented here too. The
   response's metrics row is the raw byScanner shape (ScannerMetrics::derive()
   plus families/handouts), so duration() and hourLabel() below duplicate
   ViewFormatter::duration() and the station table's hour formula rather than
   reading formatted text from JSON - the same duplication scanner-reports.js
   and batch-heatmap.js each carry for the same reason: this file has to
   stand alone and cannot depend on either having run first. */
(function () {
    'use strict';

    var card = document.getElementById('stationsCard');
    var modalEl = document.getElementById('stationModal');
    if (!card || !modalEl || !window.bootstrap) {
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
    // zero, lowercase am/pm, no space. See scanner-reports.js's copy for why
    // this is not shared.
    function hourLabel(hour) {
        var normalized = ((hour % 24) + 24) % 24;
        var h = normalized % 12;
        if (h === 0) { h = 12; }

        return h + (normalized < 12 ? 'am' : 'pm');
    }

    // Reads the fetched metrics row by data-metric key, formatting each the
    // same way Scanner/_metrics-grid.php does server side. null (no fetch
    // yet, or the account has no scans in this batch) renders every line as
    // '-' with no qualifier, matching the partial's own null branch.
    function setMetrics(metrics) {
        var text = {
            families: metrics ? Number(metrics.families || 0).toLocaleString() : '-',
            handouts: metrics ? Number(metrics.handouts || 0).toLocaleString() : '-',
            pace: !metrics || metrics.pace === null || metrics.pace === undefined
                ? '-' : Number(metrics.pace).toFixed(0) + ' / hour',
            typical: !metrics || metrics.typicalSeconds === null || metrics.typicalSeconds === undefined
                ? '-' : duration(Number(metrics.typicalSeconds)),
            onStation: metrics ? duration(Number(metrics.onStationSeconds || 0)) : '-',
            idle: metrics ? duration(Number(metrics.idleSeconds || 0)) : '-',
            bestHour: !metrics || metrics.bestHour === null || metrics.bestHour === undefined
                ? '-' : hourLabel(Number(metrics.bestHour)) + ' - ' + hourLabel(Number(metrics.bestHour) + 1),
            share: metrics ? Number((metrics.share || 0) * 100).toFixed(0) + '%' : '-'
        };
        var sub = {
            bestHour: metrics && metrics.bestHour !== null && metrics.bestHour !== undefined
                ? Number(metrics.bestHourFamilies || 0).toLocaleString() + ' families' : ''
        };

        bodyEl.querySelectorAll('[data-metric]').forEach(function (el) {
            var key = el.getAttribute('data-metric');
            el.textContent = key in text ? text[key] : '-';
        });
        bodyEl.querySelectorAll('[data-metric-sub]').forEach(function (el) {
            var key = el.getAttribute('data-metric-sub');
            if (key in sub) {
                el.textContent = sub[key];
            }
        });
    }

    function clear() {
        setMetrics(null);
        errorEl.classList.add('d-none');
        bodyEl.classList.remove('d-none');
    }

    function fail() {
        bodyEl.classList.add('d-none');
        errorEl.classList.remove('d-none');
    }

    card.addEventListener('click', function (event) {
        var row = event.target.closest('tr[data-scanner-id]');
        if (!row || !card.contains(row)) {
            return;
        }

        // batchId comes from the row's own table (data-batch, written by
        // batch-stations-table.php), not a table this file holds a reference
        // to: the All and Per day panes each render their own table, and both
        // carry the same batch's id.
        var rowTable = row.closest('table');
        var scannerId = row.getAttribute('data-scanner-id');
        var batchId = (rowTable && rowTable.getAttribute('data-batch')) || '0';

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
                setMetrics(data.metrics || null);
            })
            .catch(function () {
                if (ticket === latest) {
                    fail();
                }
            });
    });
})();
