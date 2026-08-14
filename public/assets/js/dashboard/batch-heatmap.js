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
   covers every strip on the page rather than one binding per card. */
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

    // Matches PHP's date('ga', ...): hour without a leading zero, lowercase
    // am/pm, no space. Used both for the heatmap's own hour headers and for
    // the Peak hour KPI, which prints "10am - 11am".
    function hourLabel(hour) {
        var h = hour % 12;
        if (h === 0) { h = 12; }

        return h + (hour < 12 ? 'am' : 'pm');
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

    var state = {
        heatmap: payload.heatmap || { days: [], hours: [], cells: {}, max: 0 },
        coverage: payload.coverage || { eligible: 0, served: 0, coverage: 0 },
        byScanner: payload.byScanner || [],
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

        var stations = state.byScanner.filter(function (row) { return (row.userID || 0) > 0; }).length;
        write(metric('scannersActive'), Number(stations).toLocaleString());
        write(metricSub('scannersActive'), 'across the batch');
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
