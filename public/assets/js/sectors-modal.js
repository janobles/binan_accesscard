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
        const updateButton = event.target.closest('.js-sector-update-button');
        const archiveButton = event.target.closest('.js-sector-archive-button');

        if (!updateButton && !archiveButton) {
            return;
        }

        const selected = selectedSector();

        if (!selected) {
            return;
        }

        if (updateButton) {
            const form = document.querySelector('#sectorUpdateModal form');

            if (!form) {
                return;
            }

            form.action = form.dataset.actionBase + '/' + selected.dataset.sectorId;
            document.getElementById('sectorUpdateShortcode').value = selected.dataset.shortcode || '';
            document.getElementById('sectorUpdateName').value = selected.dataset.name || '';
            document.getElementById('sectorUpdateDescription').value = selected.dataset.description || '';
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('sectorUpdateModal')).show();
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
