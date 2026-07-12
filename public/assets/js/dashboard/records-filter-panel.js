// Filter panel + pills for the server-driven retrofit tabs (lookups, audit
// trails, employee activity). The panel's radios/checkboxes live inside the
// page's GET search form; changing one submits the form immediately
// (live-apply, no Apply/Reset buttons — see
// docs/knowledge/binan-conventions/ui-design-system.md). Manage Records has
// its own AJAX version of this in family-datatable.js.
//
// Connected to:
//   - form[data-records-filter-form]        the GET search form
//   - form[data-records-pills="<id>"]       id of the pill container
//     (components/filter_pills.php; pill markup contract documented there)
//   - .records-filter-panel inputs          panel radios/checkboxes
//   - [data-records-filter] + data-records-group-label   filter group wrapper
//   - [data-records-pill-label]             pill text for a checked input
//   - [data-records-default]                the "no filter" choice; never pilled
//   - [data-records-narrow]                 type-to-narrow input for long option lists
// Exposes window.initRecordsFilterPanel(root) so AJAX-loaded fragments can re-bind.
(function (window, document) {
    function panelInputs(form) {
        return form.querySelectorAll('.records-filter-panel input[type="radio"], .records-filter-panel input[type="checkbox"]');
    }

    function renderPills(form, pillsContainer) {
        pillsContainer.textContent = '';

        panelInputs(form).forEach(function (input) {
            if (!input.checked || input.hasAttribute('data-records-default')) {
                return;
            }

            const group = input.closest('[data-records-filter]');
            const prefix = group ? (group.dataset.recordsGroupLabel || group.dataset.recordsFilter) : 'Filter';

            const pill = document.createElement('span');
            pill.className = 'badge text-bg-light border d-inline-flex align-items-center gap-1';
            pill.appendChild(document.createTextNode(prefix + ': ' + (input.dataset.recordsPillLabel || input.value)));

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-close';
            remove.setAttribute('aria-label', 'Remove filter ' + prefix);
            remove.addEventListener('click', function () {
                if (input.type === 'radio') {
                    const fallback = form.querySelector('input[name="' + input.name + '"][data-records-default]');
                    if (fallback) {
                        fallback.checked = true;
                    }
                } else {
                    input.checked = false;
                }
                form.submit();
            });
            pill.appendChild(remove);

            pillsContainer.appendChild(pill);
        });
    }

    function initRecordsFilterPanel(rootElement) {
        const root = rootElement instanceof HTMLElement ? rootElement : document;

        root.querySelectorAll('form[data-records-filter-form]').forEach(function (form) {
            if (form.dataset.recordsFilterBound === '1') {
                return;
            }
            form.dataset.recordsFilterBound = '1';

            panelInputs(form).forEach(function (input) {
                input.addEventListener('change', function () {
                    form.submit();
                });
            });

            // Long option lists narrow as you type (matching the Manage Records
            // panel); the checked option stays visible so it can't get "lost".
            form.querySelectorAll('[data-records-narrow]').forEach(function (narrow) {
                narrow.addEventListener('input', function () {
                    const needle = narrow.value.trim().toLowerCase();
                    const panel = narrow.closest('.records-filter-panel');

                    panel.querySelectorAll('[data-records-option]').forEach(function (option) {
                        const optionInput = option.querySelector('input');
                        const matches = needle === ''
                            || option.textContent.toLowerCase().indexOf(needle) !== -1
                            || (optionInput && optionInput.checked);
                        option.classList.toggle('d-none', !matches);
                    });
                });
            });

            // Server-rendered pages reload on every filter change, so pills only
            // need one render from the server-checked state.
            const pillsContainer = document.getElementById(form.dataset.recordsPills || '');
            if (pillsContainer) {
                renderPills(form, pillsContainer);
            }
        });
    }

    window.initRecordsFilterPanel = initRecordsFilterPanel;

    document.addEventListener('DOMContentLoaded', function () {
        initRecordsFilterPanel(document);
    });
})(window, document);
