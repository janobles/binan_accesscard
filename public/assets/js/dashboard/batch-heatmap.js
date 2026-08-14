/* Owns day selection and card-strip switching for the dashboard's Distribution
   pane. Two things live here because they share one property: neither reloads
   the page and neither writes state anywhere but the URL.

   Day selection has one source of truth. A click on a #peakHeatmap row header
   and a change on #dayPick both call selectDay(), and selectDay() is the only
   thing that writes either control - nothing writes them directly, so the two
   controls can never disagree about which day is selected. The KPI cards
   (Eligible/Served/Peak hour/Scanners active) are recomputed from the
   #reportsData payload on every selection change, mirroring
   DashboardPageBuilder::batchHeadline() exactly (see that method's docblock
   for the rules this follows), never scraped from the DOM. Values are written
   with textContent, never innerHTML, matching scanner-reports.js's note, so
   nothing here needs a separate escaping step.

   render(payload) is the live poll's hook (called from
   window.ReportsCharts.update()): it rebuilds #peakHeatmap's rows from the
   fresh heatmap and reapplies whatever day is currently selected. If that day
   is missing from the fresh payload - the poll caught the batch between two
   days, or a stale link named a day this batch never had - the selection
   falls back to all days rather than rendering an empty card around it.

   Card-strip switching (Hours/Days/Weekdays, Table/Map, All/Per day) is
   unrelated to the day filter except that both are card-local, client-side
   controls: switching a strip writes no query parameter and touches nothing
   outside the card it sits in, so one delegated listener on [data-strip-target]
   covers every strip on the page rather than one binding per card.

   The Stations card's Per day pane (#stations-pane-day) is also driven from
   selectDay(), the same path as everything else that depends on the picked
   day: Admin/batch-overview.php renders one of a hint paragraph or a
   #stationsTableDay table there, chosen by ?day=, and a selection made after
   load has to reproduce that same choice client-side rather than leave the
   pane showing whatever the server picked at load. This duplicates the
   column formatting (duration(), hourLabel(), timeLabel()) that
   scanner-reports.js's stationsTable() already has for the All pane's table,
   for the same reason that file's copy of hourLabel() is not shared with
   this one: this script has to run standalone the moment the page loads,
   before scanner-reports.js has necessarily run (it loads after this file
   and no-ops without Chart.js), so the Per day pane cannot depend on a
   function that script has not defined yet. */
(function () {
    'use strict';

    var dataEl = document.getElementById('reportsData');
    if (!dataEl) {
        return;
    }

    var payload;
    try {
        payload = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        return;
    }

    var dayPick = document.getElementById('dayPick');
    var grid = document.getElementById('peakHeatmap');
    var stationsDayPane = document.getElementById('stations-pane-day');
    // The All pane's table is the one place the role gate and the batch id
    // are already on the page (Admin/batch-stations-table.php writes both on
    // every table it renders), so the Per day table reads them from there
    // rather than carrying a second copy of either.
    var stationsAllTable = document.getElementById('stationsTable');

    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    // Row header text ("Aug 1 (Day 1)") is only ever composed server-side, by
    // batch-overview.php's $dayLabels. Cached off the markup the server
    // already rendered so a live-poll rebuild reuses the same wording instead
    // of reformatting a date client-side; only a day the cache has never seen
    // (the batch crossed into a new day since the page loaded) falls back to
    // computing one.
    var dayLabelCache = {};
    if (grid) {
        var initialButtons = grid.querySelectorAll('button.heatmap-day');
        for (var i = 0; i < initialButtons.length; i++) {
            dayLabelCache[initialButtons[i].getAttribute('data-day')] = initialButtons[i].textContent;
        }
    }

    function fallbackDayLabel(day, index) {
        var parts = day.split('-');
        var month = MONTHS[parseInt(parts[1], 10) - 1] || parts[1];

        return month + ' ' + parseInt(parts[2], 10) + ' (Day ' + (index + 1) + ')';
    }

    function dayLabel(day, index) {
        if (!(day in dayLabelCache)) {
            dayLabelCache[day] = fallbackDayLabel(day, index);
        }

        return dayLabelCache[day];
    }

    // Matches PHP's date('ga', mktime($hour, 0)): hour without a leading
    // zero, lowercase am/pm, no space. mktime() rolls hour 24 over into the
    // next day's midnight, so the range end for a peak hour of 23 reads
    // "12am", not "12pm" - wrap the hour first, the same rollover, before
    // deriving am/pm from it. Used both for the heatmap's own hour headers
    // and for the Peak hour KPI, which prints "10am - 11am".
    function hourLabel(hour) {
        var normalized = ((hour % 24) + 24) % 24;
        var h = normalized % 12;
        if (h === 0) { h = 12; }

        return h + (normalized < 12 ? 'am' : 'pm');
    }

    // The same proportional five-step scale as Admin/batch-heatmap.php's
    // $step(): integer division against the max would put almost every cell
    // of a lopsided batch in step one, so a repaint has to use the same
    // formula the server would have, not a simpler approximation.
    function heatStep(families, max) {
        if (families <= 0 || max <= 0) {
            return 0;
        }

        return Math.max(1, Math.ceil((families / max) * 5));
    }

    // Matches ViewFormatter::duration() exactly. See this file's header for
    // why it is not shared with scanner-reports.js's copy.
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

    // Matches PHP's date('g:ia', ...).
    function timeLabel(unixSeconds) {
        var d = new Date(unixSeconds * 1000);
        var h = d.getHours() % 12;
        if (h === 0) { h = 12; }
        var minutes = d.getMinutes();

        return h + ':' + (minutes < 10 ? '0' : '') + minutes + (d.getHours() < 12 ? 'am' : 'pm');
    }

    // Matches PHP's date('M j', strtotime($selectedDay)): the Per day hint's
    // "No station logged a scan on Aug 1." wording, with no day-index suffix
    // (unlike dayLabel() above, which is the heatmap row's own label).
    function monthDayLabel(day) {
        var parts = day.split('-');
        var month = MONTHS[parseInt(parts[1], 10) - 1] || parts[1];

        return month + ' ' + parseInt(parts[2], 10);
    }

    var state = {
        heatmap: payload.heatmap || { days: [], hours: [], cells: {}, max: 0 },
        coverage: payload.coverage || { eligible: 0, served: 0, coverage: 0 },
        byScanner: payload.byScanner || [],
        byScannerByDay: payload.byScannerByDay || {},
        selectedDay: payload.selectedDay || null
    };

    function write(node, value) {
        if (node) { node.textContent = String(value); }
    }

    function metric(key) { return document.querySelector('[data-metric="' + key + '"]'); }
    function metricSub(key) { return document.querySelector('[data-metric-sub="' + key + '"]'); }

    // Mirrors DashboardPageBuilder::batchHeadline() field for field, so the
    // server-rendered figures on first load and this client recomputation on
    // every selection change or poll can never disagree.
    function recomputeHeadline() {
        var heatmap = state.heatmap;
        var coverage = state.coverage;
        var day = state.selectedDay;
        var days = day === null ? heatmap.days : [day];

        var servedOnDay = 0;
        var byHour = {};
        days.forEach(function (d) {
            var hours = heatmap.cells[d] || {};
            Object.keys(hours).forEach(function (h) {
                var families = hours[h].families || 0;
                servedOnDay += families;
                byHour[h] = (byHour[h] || 0) + families;
            });
        });

        var peakHour = null;
        var peakFamilies = 0;
        Object.keys(byHour)
            .sort(function (a, b) { return Number(a) - Number(b); })
            .forEach(function (h) {
                if (byHour[h] > peakFamilies) {
                    peakHour = parseInt(h, 10);
                    peakFamilies = byHour[h];
                }
            });

        var servedValue = day === null ? (coverage.served || 0) : servedOnDay;

        write(metric('eligible'), Number(coverage.eligible || 0).toLocaleString());
        write(metricSub('eligible'), 'in this batch');

        write(metric('served'), Number(servedValue).toLocaleString());
        write(metricSub('served'), day === null
            ? coverage.coverage + '% of eligible'
            : Number(coverage.served || 0).toLocaleString() + ' across the batch');

        write(metric('peakHour'), peakHour === null ? '-' : hourLabel(peakHour) + ' - ' + hourLabel(peakHour + 1));
        write(metricSub('peakHour'), peakHour === null ? 'no scans yet' : Number(peakFamilies).toLocaleString() + ' families');

        // With a day selected, count that day's own rows from byScannerByDay
        // rather than the batch-wide byScanner fold, or a station that never
        // showed up that day still gets counted. Mirrors batchHeadline().
        var scannerRows = day === null ? state.byScanner : (state.byScannerByDay[day] || []);
        var stations = scannerRows.filter(function (row) { return (row.userID || 0) > 0; }).length;
        write(metric('scannersActive'), Number(stations).toLocaleString());
        write(metricSub('scannersActive'), day === null ? 'across the batch' : 'that day');
    }

    function applyDayPick() {
        if (dayPick) { dayPick.value = state.selectedDay || ''; }
    }

    function applyRowSelection() {
        if (!grid) { return; }
        var rows = grid.querySelectorAll('tr');
        for (var i = 0; i < rows.length; i++) {
            var button = rows[i].querySelector('button.heatmap-day');
            if (!button) { continue; }
            var isSelected = button.getAttribute('data-day') === state.selectedDay;
            button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            rows[i].classList.toggle('is-selected', isSelected);
        }
    }

    // ?day= rides on replaceState rather than pushState, so a reload or a
    // shared link keeps the day without every click growing the back-button
    // history by one entry.
    function updateUrl(day) {
        if (typeof URL === 'undefined' || typeof history === 'undefined' || !history.replaceState) {
            return;
        }
        var url = new URL(location.href);
        if (day) {
            url.searchParams.set('day', day);
        } else {
            url.searchParams.delete('day');
        }
        history.replaceState(null, '', url.toString());
    }

    // One row, matching Admin/batch-stations-table.php's columns and its role
    // gate: data-scanner-id/data-scanner-name only when canDrillIn is true
    // and the row is not the TOTAL fold (userID 0).
    function stationsDayRow(row, canDrillIn) {
        var userId = Number(row.userID) || 0;
        var tr = document.createElement('tr');
        if (userId === 0) { tr.setAttribute('class', 'is-total'); }
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

        return tr;
    }

    // Rebuilds #stations-pane-day from scratch: the server renders one of a
    // hint paragraph or a table there depending on ?day=, and a client-side
    // selection has to reproduce that same three-way choice, not just patch
    // rows into whichever one happened to load. The role gate and the batch
    // id both come from the All pane's table, the one place the server
    // already wrote them.
    function renderStationsDayPane() {
        if (!stationsDayPane) { return; }

        while (stationsDayPane.firstChild) { stationsDayPane.removeChild(stationsDayPane.firstChild); }

        var day = state.selectedDay;
        if (day === null) {
            var hint = document.createElement('p');
            hint.setAttribute('class', 'text-muted mb-0');
            hint.id = 'stationsDayHint';
            hint.textContent = 'Use the Day picker above to choose a day.';
            stationsDayPane.appendChild(hint);
            return;
        }

        var rows = state.byScannerByDay[day] || [];
        if (rows.length === 0) {
            var noScans = document.createElement('p');
            noScans.setAttribute('class', 'text-muted mb-0');
            noScans.id = 'stationsDayHint';
            noScans.textContent = 'No station logged a scan on ' + monthDayLabel(day) + '.';
            stationsDayPane.appendChild(noScans);
            return;
        }

        var canDrillIn = !!stationsAllTable && stationsAllTable.getAttribute('data-can-drill-in') === '1';
        var batchId = stationsAllTable ? (stationsAllTable.getAttribute('data-batch') || '0') : '0';

        var wrap = document.createElement('div');
        wrap.setAttribute('class', 'table-responsive');
        var table = document.createElement('table');
        table.setAttribute('class', 'table manage-record-table align-middle w-100 mb-0');
        table.id = 'stationsTableDay';
        table.setAttribute('data-batch', batchId);
        table.setAttribute('data-can-drill-in', canDrillIn ? '1' : '0');

        var thead = document.createElement('thead');
        var headRow = document.createElement('tr');
        ['Scanner', 'Families', 'Handouts', 'Pace', 'Typical', 'On station', 'Idle', 'Best hour', 'Share'].forEach(function (label) {
            var th = document.createElement('th');
            th.setAttribute('scope', 'col');
            th.textContent = label;
            headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        rows.forEach(function (row) { tbody.appendChild(stationsDayRow(row, canDrillIn)); });
        table.appendChild(tbody);

        wrap.appendChild(table);
        stationsDayPane.appendChild(wrap);
    }

    function selectDay(day) {
        var normalized = day || null;
        // A payload refresh or a stale link can name a day this batch no
        // longer carries (or never did); falling back to all days beats
        // rendering an empty card around a selection that does not exist.
        if (normalized !== null && state.heatmap.days.indexOf(normalized) === -1) {
            normalized = null;
        }

        state.selectedDay = normalized;
        applyDayPick();
        applyRowSelection();
        recomputeHeadline();
        renderStationsDayPane();
        updateUrl(normalized);
    }

    function rebuildGrid() {
        if (!grid) { return; }
        var tbody = grid.querySelector('tbody');
        if (!tbody) { return; }

        while (tbody.firstChild) { tbody.removeChild(tbody.firstChild); }

        var heatmap = state.heatmap;
        heatmap.days.forEach(function (day, index) {
            var row = document.createElement('tr');
            var th = document.createElement('th');
            th.setAttribute('scope', 'row');

            var button = document.createElement('button');
            button.setAttribute('type', 'button');
            button.setAttribute('class', 'heatmap-day');
            button.setAttribute('data-day', day);
            button.setAttribute('aria-pressed', 'false');
            button.textContent = dayLabel(day, index);
            th.appendChild(button);
            row.appendChild(th);

            heatmap.hours.forEach(function (hour) {
                var cell = (heatmap.cells[day] && heatmap.cells[day][hour]) || { families: 0, state: 'closed' };
                var td = document.createElement('td');
                td.setAttribute('class', 'heatmap-cell is-' + cell.state);
                td.setAttribute('data-heat', String(heatStep(cell.families, heatmap.max)));
                td.setAttribute('data-day', day);

                var reading = cell.state === 'closed'
                    ? dayLabel(day, index) + ', ' + hourLabel(hour) + ', station closed'
                    : dayLabel(day, index) + ', ' + hourLabel(hour) + ', ' + Number(cell.families).toLocaleString() + ' families';
                td.setAttribute('title', reading);

                var hiddenReading = document.createElement('span');
                hiddenReading.setAttribute('class', 'visually-hidden');
                hiddenReading.textContent = reading;
                td.appendChild(hiddenReading);

                var visible = document.createElement('span');
                visible.setAttribute('aria-hidden', 'true');
                visible.textContent = cell.state === 'closed' ? '' : Number(cell.families).toLocaleString();
                td.appendChild(visible);

                row.appendChild(td);
            });

            tbody.appendChild(row);
        });
    }

    function render(fresh) {
        if (!fresh) { return; }

        state.heatmap = fresh.heatmap || { days: [], hours: [], cells: {}, max: 0 };
        state.coverage = fresh.coverage || { eligible: 0, served: 0, coverage: 0 };
        state.byScanner = fresh.byScanner || [];
        state.byScannerByDay = fresh.byScannerByDay || {};

        rebuildGrid();
        // Reapplies through selectDay() rather than writing the controls
        // directly, so a poll that just made the selected day disappear falls
        // back the same way a stale link would.
        selectDay(state.selectedDay);
    }

    if (grid) {
        grid.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('button.heatmap-day') : null;
            if (button) { selectDay(button.getAttribute('data-day')); }
        });
    }

    if (dayPick) {
        dayPick.addEventListener('change', function () {
            selectDay(dayPick.value || null);
        });
    }

    // One delegated listener for every card strip on the page
    // (Activity/Barangay/Stations): components/card_tabs.php's buttons all
    // carry data-strip-target, and the card that owns them carries data-strip,
    // so a click is resolved to "which card, which pane" without a binding
    // per card.
    document.addEventListener('click', function (event) {
        var target = event.target.closest ? event.target.closest('[data-strip-target]') : null;
        if (!target) { return; }
        var card = target.closest('[data-strip]');
        if (!card) { return; }

        var key = target.getAttribute('data-strip-target');
        card.setAttribute('data-strip', key);

        var tabs = card.querySelectorAll('[data-strip-target]');
        for (var i = 0; i < tabs.length; i++) {
            var active = tabs[i].getAttribute('data-strip-target') === key;
            tabs[i].classList.toggle('active', active);
            tabs[i].setAttribute('aria-selected', active ? 'true' : 'false');
        }

        var panes = card.querySelectorAll('[data-strip-pane]');
        for (var j = 0; j < panes.length; j++) {
            if (panes[j].getAttribute('data-strip-pane') === key) {
                panes[j].removeAttribute('hidden');
            } else {
                panes[j].setAttribute('hidden', '');
            }
        }
    });

    // Applies the day the server already selected (from ?day=) to the
    // controls and KPIs once at load, so a reload lands on a page that agrees
    // with itself before any click or poll happens.
    selectDay(state.selectedDay);

    window.BatchHeatmap = {
        selectDay: selectDay,
        render: render,
        selectedDay: function () { return state.selectedDay; }
    };
})();
