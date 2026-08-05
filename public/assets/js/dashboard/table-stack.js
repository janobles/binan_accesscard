/* Stamps each cell of a .table-stack table with the column name its value sits
   under, so theme.css can render the row as one labelled block on a phone.

   The labels are read from the table's own thead rather than written into every
   view, because a hand-written data-label and the column it names drift apart
   the first time someone reorders a column, and the stale label is invisible
   until a user reads it. Re-stamps whenever a script replaces the rows (the
   dashboard's live poll rebuilds the stations tbody, DataTables rebuilds on
   every draw), so labels never outlive the columns they came from.

   Escaping: textContent in, setAttribute out. No markup is built here. */
(function () {
    'use strict';

    function columnLabels(table) {
        return Array.prototype.map.call(
            table.querySelectorAll('thead th'),
            function (th) { return th.textContent.trim(); }
        );
    }

    function stamp(table) {
        var labels = columnLabels(table);
        if (labels.length === 0) {
            return;
        }

        Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (row) {
            Array.prototype.forEach.call(row.children, function (cell, index) {
                // A spanning cell (the empty state) belongs to no single column.
                if (cell.colSpan > 1) {
                    cell.removeAttribute('data-label');

                    return;
                }
                if (labels[index]) {
                    cell.setAttribute('data-label', labels[index]);
                }
            });
        });
    }

    function watch(table) {
        stamp(table);

        var body = table.tBodies[0];
        if (!body || typeof MutationObserver === 'undefined') {
            return;
        }
        // childList only: stamp() sets attributes, so it cannot retrigger this.
        new MutationObserver(function () { stamp(table); })
            .observe(body, { childList: true });
    }

    function init(root) {
        Array.prototype.forEach.call(
            (root || document).querySelectorAll('table.table-stack'),
            watch
        );
    }

    document.addEventListener('DOMContentLoaded', function () { init(document); });

    // For tables that arrive after load (a modal fragment, an AJAX pane).
    window.TableStack = { apply: init };
})();
