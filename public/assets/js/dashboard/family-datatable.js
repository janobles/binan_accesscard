// Server-side DataTables integration for the Manage Records family table.
(function (window, document) {
    'use strict';

    function selectedValues(container) {
        if (!container) {
            return [];
        }

        return Array.from(container.querySelectorAll('input[type="checkbox"]:checked'))
            .map(function (input) { return input.value; })
            .filter(Boolean);
    }

    function updateFilterLabel(dropdown) {
        if (!dropdown) {
            return;
        }

        var label = dropdown.querySelector('[data-records-filter-label]');
        var selected = Array.from(dropdown.querySelectorAll('input[type="checkbox"]:checked'));
        var isBarangay = dropdown.dataset.recordsFilter === 'barangay';

        dropdown.querySelectorAll('[data-records-option]').forEach(function (option) {
            var input = option.querySelector('input[type="checkbox"]');
            option.classList.toggle('active', !!(input && input.checked));
        });

        if (!label) {
            return;
        }

        if (selected.length === 0) {
            label.textContent = isBarangay ? '-Select barangay-' : '-Select sector-';
        } else if (selected.length === 1) {
            var option = selected[0].closest('label');
            label.textContent = option ? option.textContent.trim() : selected[0].value;
        } else {
            label.textContent = selected.length + ' selected';
        }
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

        if (!tableElement || !filterForm || typeof window.DataTable !== 'function') {
            return;
        }

        var scope = 'heads';
        var keywordInput = filterForm.querySelector('[name="q"]');
        var statusSelect = filterForm.querySelector('[name="status"]');
        var sectorFilter = filterForm.querySelector('[data-records-filter="sector"]');
        var barangayFilter = filterForm.querySelector('[data-records-filter="barangay"]');
        var quickSearchTerm = '';
        var quickSearchInput = null;

        function applyCurrentPageQuickSearch() {
            var searchTerm = quickSearchTerm.trim().toLowerCase();
            var rows = tableElement.querySelectorAll('tbody tr');

            rows.forEach(function (row) {
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
            dataTablesSearchInput.parentNode.replaceChild(quickSearchInput, dataTablesSearchInput);

            quickSearchInput.addEventListener('input', function () {
                quickSearchTerm = quickSearchInput.value;
                applyCurrentPageQuickSearch();
            });
        }

        updateFilterLabel(sectorFilter);
        updateFilterLabel(barangayFilter);

        var dataTable = new window.DataTable(tableElement, {
            processing: true,
            serverSide: true,
            searching: true,
            scrollX: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            // No initial column sort -> server returns newest records first, so a
            // just-added/imported family shows at the top. Header clicks still sort.
            order: [],
            ajax: {
                url: tableElement.dataset.ajaxUrl,
                data: function (request) {
                    if (request.search) {
                        request.search.value = '';
                        request.search.regex = false;
                    }

                    request.q = keywordInput ? keywordInput.value.trim() : '';
                    request.status = statusSelect ? statusSelect.value : 'all';
                    request.sectorID = selectedValues(sectorFilter);
                    request.barangay = selectedValues(barangayFilter);
                    request.scope = scope;
                }
            },
            columns: [
                { data: 'qr', name: 'qr', orderable: false, className: 'text-center text-nowrap' },
                { data: 'name', name: 'name', orderSequence: ['asc', 'desc', ''] },
                { data: 'sector', name: 'sector', orderable: false },
                { data: 'address', name: 'address', orderSequence: ['asc', 'desc', ''] },
                { data: 'birthday', name: 'birthday', orderSequence: ['asc', 'desc', ''] },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' }
            ],
            layout: {
                topStart: 'pageLength',
                topEnd: 'search',
                bottomStart: 'info',
                bottomEnd: 'paging'
            },
            language: {
                emptyTable: 'No records found.',
                zeroRecords: 'No matching records found.',
                processing: 'Loading records...',
                lengthMenu: 'Show _MENU_ entries',
                search: 'Search:'
            },
            drawCallback: function () {
                if (typeof window.initFamilyListActionDropdowns === 'function') {
                    window.initFamilyListActionDropdowns(tableElement);
                }

                applyCurrentPageQuickSearch();
            }
        });

        bindCurrentPageQuickSearch();

        // Exposed so the Add/Update modal can refresh the table after a save.
        window.reloadFamilyDataTable = function () {
            try {
                dataTable.ajax.reload(null, false);
            } catch (error) {
                /* table not initialised yet */
            }
        };

        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            scope = 'all';
            dataTable.ajax.reload(null, true);
        });

        filterForm.addEventListener('change', function (event) {
            var target = event.target;

            if (target.matches('input[type="checkbox"]')) {
                updateFilterLabel(target.closest('[data-records-filter]'));
                return;
            }

            if (target === statusSelect) {
                dataTable.ajax.reload(null, true);
            }
        });

        var clearButton = filterForm.querySelector('[data-records-clear]');
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                if (keywordInput) {
                    keywordInput.value = '';
                }
                if (statusSelect) {
                    statusSelect.value = 'all';
                }
                filterForm.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                    input.checked = false;
                });
                quickSearchTerm = '';
                if (quickSearchInput) {
                    quickSearchInput.value = '';
                }
                scope = 'heads';
                updateFilterLabel(sectorFilter);
                updateFilterLabel(barangayFilter);
                dataTable.order([]);
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
