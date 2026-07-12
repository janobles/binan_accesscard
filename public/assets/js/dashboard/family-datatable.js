// Server-side DataTables integration for the Manage Records family table.
// Toolbar markup lives in app/Views/components/records_toolbar.php; the
// data-records-* attributes there are the contract this file depends on.
(function (window, document) {
    'use strict';

    var FILTER_DEBOUNCE_MS = 350;

    function selectedCheckboxes(container) {
        if (!container) {
            return [];
        }

        return Array.from(container.querySelectorAll('input[type="checkbox"]:checked'));
    }

    function selectedValues(container) {
        return selectedCheckboxes(container).map(function (input) { return input.value; }).filter(Boolean);
    }

    function rowMatchesSearch(row, searchTerm) {
        if (!searchTerm) {
            return true;
        }

        return row.textContent.toLowerCase().indexOf(searchTerm) !== -1;
    }

    function initializeFamilyDataTable() {
        var tableElement = document.getElementById('familyRecordsTable');
        var filterForm = document.getElementById('familyDataTableFilters');
        var pillsContainer = document.getElementById('familyFilterPills');

        if (!tableElement || !filterForm || typeof window.DataTable !== 'function') {
            return;
        }

        var scope = 'heads';
        var keywordInput = filterForm.querySelector('[data-records-database-keyword]');
        var sectorFilter = filterForm.querySelector('[data-records-filter="sector"]');
        var barangayFilter = filterForm.querySelector('[data-records-filter="barangay"]');
        var quickSearchTerm = '';
        var quickSearchInput = null;
        var debounceTimer = null;

        function statusValue() {
            var checked = filterForm.querySelector('input[name="status"]:checked');
            return checked ? checked.value : 'all';
        }

        function applyCurrentPageQuickSearch() {
            var searchTerm = quickSearchTerm.trim().toLowerCase();

            tableElement.querySelectorAll('tbody tr').forEach(function (row) {
                if (row.querySelector('td.dt-empty')) {
                    return;
                }

                row.style.display = rowMatchesSearch(row, searchTerm) ? '' : 'none';
            });
        }

        function bindCurrentPageQuickSearch() {
            var container = typeof dataTable.table === 'function'
                ? dataTable.table().container()
                : tableElement.closest('.dt-container');
            var dataTablesSearchInput = container ? container.querySelector('.dt-search input') : null;

            if (!dataTablesSearchInput) {
                return;
            }

            quickSearchInput = dataTablesSearchInput.cloneNode(true);
            quickSearchInput.value = quickSearchTerm;
            quickSearchInput.placeholder = 'Filter loaded results...';
            dataTablesSearchInput.parentNode.replaceChild(quickSearchInput, dataTablesSearchInput);

            quickSearchInput.addEventListener('input', function () {
                quickSearchTerm = quickSearchInput.value;
                applyCurrentPageQuickSearch();
            });
        }

        // One pill per checked filter input. Pill markup contract is documented
        // in app/Views/components/filter_pills.php.
        function renderFilterPills() {
            if (!pillsContainer) {
                return;
            }

            pillsContainer.textContent = '';

            var entries = [];
            selectedCheckboxes(sectorFilter).forEach(function (input) {
                entries.push({ prefix: 'Sector', input: input });
            });
            selectedCheckboxes(barangayFilter).forEach(function (input) {
                entries.push({ prefix: 'Barangay', input: input });
            });
            if (statusValue() !== 'all') {
                entries.push({
                    prefix: 'Status',
                    input: filterForm.querySelector('input[name="status"]:checked')
                });
            }

            entries.forEach(function (entry) {
                if (!entry.input) {
                    return;
                }

                var pill = document.createElement('span');
                pill.className = 'badge text-bg-light border d-inline-flex align-items-center gap-1';
                pill.appendChild(document.createTextNode(
                    entry.prefix + ': ' + (entry.input.dataset.recordsPillLabel || entry.input.value)
                ));

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn-close';
                remove.setAttribute('aria-label', 'Remove filter ' + entry.prefix);
                remove.addEventListener('click', function () {
                    if (entry.input.type === 'radio') {
                        var allRadio = filterForm.querySelector('input[name="status"][value="all"]');
                        if (allRadio) {
                            allRadio.checked = true;
                        }
                    } else {
                        entry.input.checked = false;
                    }
                    onFilterChanged();
                });
                pill.appendChild(remove);

                pillsContainer.appendChild(pill);
            });
        }

        // Filters live-apply: each panel change redraws pills at once and
        // reloads the table after a short pause so rapid multi-checking sends
        // one request instead of one per click.
        function onFilterChanged() {
            renderFilterPills();

            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
            }
            debounceTimer = window.setTimeout(function () {
                debounceTimer = null;
                dataTable.ajax.reload(null, true);
            }, FILTER_DEBOUNCE_MS);
        }

        var dataTable = new window.DataTable(tableElement, {
            processing: true,
            serverSide: true,
            searching: true,
            scrollX: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            // Default order: QR control number ascending, 1 to n. The /data
            // endpoint maps column 0 to the qr_control join (dataTableOrder()).
            order: [[0, 'asc']],
            ajax: {
                url: tableElement.dataset.ajaxUrl,
                data: function (request) {
                    if (request.search) {
                        request.search.value = '';
                        request.search.regex = false;
                    }

                    request.q = keywordInput ? keywordInput.value.trim() : '';
                    request.status = statusValue();
                    request.sectorID = selectedValues(sectorFilter);
                    request.barangay = selectedValues(barangayFilter);
                    request.scope = scope;
                }
            },
            columns: [
                { data: 'qr', name: 'qr', orderSequence: ['asc', 'desc'], className: 'text-center text-nowrap' },
                { data: 'name', name: 'name', orderSequence: ['asc', 'desc'] },
                { data: 'sector', name: 'sector', orderable: false },
                { data: 'address', name: 'address', orderSequence: ['asc', 'desc'] },
                { data: 'birthday', name: 'birthday', orderSequence: ['asc', 'desc'] },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' }
            ],
            layout: {
                topStart: 'search',
                topEnd: 'pageLength',
                bottomStart: 'info',
                bottomEnd: 'paging'
            },
            language: {
                emptyTable: 'No records found.',
                zeroRecords: 'No matching records found.',
                processing: 'Loading records...',
                lengthMenu: 'Show _MENU_ entries',
                search: ''
            },
            drawCallback: function () {
                if (typeof window.initFamilyListActionDropdowns === 'function') {
                    window.initFamilyListActionDropdowns(tableElement);
                }

                applyCurrentPageQuickSearch();
            }
        });

        bindCurrentPageQuickSearch();
        renderFilterPills();

        // Exposed so the Add/Update modal can refresh the table after a save.
        window.reloadFamilyDataTable = function () {
            try {
                dataTable.ajax.reload(null, false);
            } catch (error) {
                /* table not initialised yet */
            }
        };

        // Explicit keyword search. Switches to the whole-database scope so the
        // keyword also matches non-head family members.
        // Cancels a pending live-apply reload before an immediate one so the
        // stale timer does not fire a duplicate request afterwards.
        function cancelPendingFilterReload() {
            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
                debounceTimer = null;
            }
        }

        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            scope = 'all';
            cancelPendingFilterReload();
            dataTable.ajax.reload(null, true);
        });

        filterForm.addEventListener('change', function (event) {
            var target = event.target;

            if (target.matches('input[type="checkbox"], input[name="status"]')) {
                onFilterChanged();
            }
        });

        // Panel-wide type-to-narrow: hides non-matching options across the
        // sector, barangay, and status columns, nothing more.
        var panelRoot = filterForm.querySelector('[data-records-panel]');
        var narrowInput = filterForm.querySelector('[data-records-narrow]');
        if (narrowInput && panelRoot) {
            narrowInput.addEventListener('input', function () {
                var term = narrowInput.value.trim().toLowerCase();

                panelRoot.querySelectorAll('[data-records-option]').forEach(function (option) {
                    var matches = !term || option.textContent.toLowerCase().indexOf(term) !== -1;
                    option.classList.toggle('d-none', !matches);
                });

                // A column with no matches hides entirely (header included) so
                // the remaining columns pack to the left.
                panelRoot.querySelectorAll('[data-records-filter]').forEach(function (group) {
                    var anyVisible = group.querySelector('[data-records-option]:not(.d-none)');
                    group.classList.toggle('d-none', !anyVisible);
                });
            });
        }

        // The single full reset: keyword, filters, scope, quick search, sort.
        var clearButton = filterForm.querySelector('[data-records-clear]');
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                if (keywordInput) {
                    keywordInput.value = '';
                }
                filterForm.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                    input.checked = false;
                });
                var allRadio = filterForm.querySelector('input[name="status"][value="all"]');
                if (allRadio) {
                    allRadio.checked = true;
                }
                if (narrowInput) {
                    narrowInput.value = '';
                    narrowInput.dispatchEvent(new Event('input'));
                }
                quickSearchTerm = '';
                if (quickSearchInput) {
                    quickSearchInput.value = '';
                }
                scope = 'heads';
                renderFilterPills();
                cancelPendingFilterReload();
                dataTable.order([[0, 'asc']]);
                dataTable.ajax.reload(null, true);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeFamilyDataTable);
    } else {
        initializeFamilyDataTable();
    }
})(window, document);
