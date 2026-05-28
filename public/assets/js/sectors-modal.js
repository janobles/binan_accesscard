(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'sectors',
        triggerSelector: '.js-open-sectors-modal',
        defaultTitle: 'Sector List',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading sectors...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load sectors. Please try again.</div>'
    });
})(window);

(function (window, document) {
    function selectedSector() {
        return document.querySelector('.js-sector-select:checked');
    }

    function setSectorActionState(enabled) {
        document.querySelectorAll('.js-sector-update-button, .js-sector-archive-button').forEach(function (button) {
            button.disabled = !enabled;
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches('.js-sector-select')) {
            setSectorActionState(true);
        }
    });

    document.addEventListener('click', function (event) {
        const addButton = event.target.closest('.js-sector-add-button');
        const updateButton = event.target.closest('.js-sector-update-button');
        const archiveButton = event.target.closest('.js-sector-archive-button');

        if (!addButton && !updateButton && !archiveButton) {
            return;
        }

        if (addButton) {
            const modal = document.getElementById('sectorEditorModal');
            const form = modal?.querySelector('form');

            if (!modal || !form) {
                return;
            }

            form.action = form.dataset.createAction;
            form.reset();
            document.getElementById('sectorEditorModalLabel').textContent = 'Add Sector';
            document.querySelector('.js-sector-editor-submit').textContent = 'Add';
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
            return;
        }

        const selected = selectedSector();

        if (!selected) {
            return;
        }

        if (updateButton) {
            const modal = document.getElementById('sectorEditorModal');
            const form = modal?.querySelector('form');

            if (!modal || !form) {
                return;
            }

            form.action = form.dataset.updateAction + '/' + selected.dataset.sectorId;
            document.getElementById('sectorEditorShortcode').value = selected.dataset.shortcode || '';
            document.getElementById('sectorEditorName').value = selected.dataset.name || '';
            document.getElementById('sectorEditorDescription').value = selected.dataset.description || '';
            document.getElementById('sectorEditorModalLabel').textContent = 'Update Sector';
            document.querySelector('.js-sector-editor-submit').textContent = 'Update';
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
            return;
        }

        const form = document.querySelector('#sectorArchiveModal form');
        const label = document.querySelector('#sectorArchiveModal .js-sector-archive-name');

        if (!form) {
            return;
        }

        form.action = form.dataset.actionBase + '/' + selected.dataset.sectorId;

        if (label) {
            label.textContent = selected.dataset.name || 'this sector';
        }

        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('sectorArchiveModal')).show();
    });
})(window, document);
