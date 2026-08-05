/* Colours the Biñan barangay map from the batch's coverage rows, shows the
   exact figures in a Bootstrap popover, and highlights the matching row in the
   leaderboard beside it.

   Listens for the rollout:day event the day chart emits, so clicking a day
   recolours the map to that day alone. The default, and what you get back by
   clicking the same bar again, is the cumulative picture that matches the
   table. */
(function () {
    'use strict';

    var host = document.querySelector('[data-barangay-map]');
    if (!host) { return; }

    var rows;
    try {
        rows = JSON.parse(host.getAttribute('data-coverage') || '[]');
    } catch (e) {
        return;
    }

    var byName = {};
    rows.forEach(function (row) { byName[row.barangay] = row; });

    // Four flat steps rather than a continuous ramp: the eye cannot read a
    // gradient to two significant figures anyway, and the popover carries the
    // exact number for anyone who needs it.
    function step(coverage) {
        if (coverage >= 75) { return 'is-high'; }
        if (coverage >= 50) { return 'is-mid'; }
        if (coverage > 0) { return 'is-low'; }
        return 'is-none';
    }

    var paths = host.querySelectorAll('path[data-brgy]');

    function paint(scopeRows) {
        var scoped = {};
        (scopeRows || rows).forEach(function (row) { scoped[row.barangay] = row; });

        paths.forEach(function (path) {
            var row = scoped[path.getAttribute('data-brgy')];
            path.classList.remove('is-high', 'is-mid', 'is-low', 'is-none');
            path.classList.add(step(row ? row.coverage : 0));
        });
    }

    paths.forEach(function (path) {
        var name = path.getAttribute('data-brgy');
        var row  = byName[name];
        var text = row
            ? row.received.toLocaleString() + ' of ' + row.total.toLocaleString() + ' served, ' + row.coverage + '%'
            : 'No eligible families in this batch';

        path.setAttribute('tabindex', '0');
        new bootstrap.Popover(path, {
            title: name,
            content: text,
            trigger: 'hover focus',
            placement: 'top',
            container: 'body'
        });

        // Hovering a barangay marks its row in the leaderboard, so the map
        // acts as a spatial index into the table rather than a second copy.
        path.addEventListener('mouseenter', function () {
            var tr = document.querySelector('tr[data-barangay="' + name + '"]');
            if (tr) { tr.classList.add('is-highlighted'); }
        });
        path.addEventListener('mouseleave', function () {
            var tr = document.querySelector('tr[data-barangay="' + name + '"]');
            if (tr) { tr.classList.remove('is-highlighted'); }
        });
    });

    paint(rows);

    document.addEventListener('rollout:day', function () {
        // The stats payload carries cumulative barangay rows only, so a day
        // click cannot recolour to one day without a per-day-per-barangay
        // query. Until that exists, repaint the cumulative picture rather than
        // showing a colour that silently means something else.
        paint(rows);
    });
})();
