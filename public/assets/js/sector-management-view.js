(function (window, document) {
    function initSectorManagementView(rootElement) {
        const root = rootElement instanceof HTMLElement ? rootElement : document;
        const panel = root.querySelector('[data-sector-management-root]');

        if (!panel || panel.dataset.sectorManagementInitialized === '1') {
            return;
        }

        panel.dataset.sectorManagementInitialized = '1';

        const addTrigger = root.querySelector('#sectorAddTrigger');
        const updateTrigger = root.querySelector('#sectorUpdateTrigger');
        const deleteTrigger = root.querySelector('#sectorDeleteTrigger');
        const addPopup = root.querySelector('#sectorAddPopup');
        const updatePopup = root.querySelector('#sectorUpdatePopup');
        const deletePopup = root.querySelector('#sectorDeletePopup');
        const addPopupCloseButtons = root.querySelectorAll('.js-close-sector-add-popup');
        const updatePopupCloseButtons = root.querySelectorAll('.js-close-sector-update-popup');
        const deletePopupCloseButtons = root.querySelectorAll('.js-close-sector-delete-popup');
        const sectorDataElement = root.querySelector('#sectorListData');
        const updateSelect = root.querySelector('#sectorUpdateSelect');
        const archiveSelect = root.querySelector('#sectorArchiveSelect');
        const updateForm = root.querySelector('#sectorUpdateForm');
        const archiveForm = root.querySelector('#sectorArchiveForm');
        const shortcodeInput = root.querySelector('#sectorUpdateShortcode');
        const nameInput = root.querySelector('#sectorUpdateName');
        const descriptionInput = root.querySelector('#sectorUpdateDescription');

        const togglePopup = function (popupElement, show) {
            if (!popupElement) {
                return;
            }

            popupElement.classList.toggle('d-none', !show);
            document.body.classList.toggle('overflow-hidden', show);
        };

        const bindPopup = function (trigger, popupElement, closeButtons) {
            if (!trigger || !popupElement) {
                return;
            }

            trigger.addEventListener('click', function () {
                togglePopup(popupElement, true);
            });

            closeButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    togglePopup(popupElement, false);
                });
            });

            popupElement.addEventListener('click', function (event) {
                if (event.target === popupElement) {
                    togglePopup(popupElement, false);
                }
            });
        };

        bindPopup(addTrigger, addPopup, addPopupCloseButtons);
        bindPopup(updateTrigger, updatePopup, updatePopupCloseButtons);
        bindPopup(deleteTrigger, deletePopup, deletePopupCloseButtons);

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            togglePopup(addPopup, false);
            togglePopup(updatePopup, false);
            togglePopup(deletePopup, false);
        });

        if (!sectorDataElement || !updateSelect || !archiveSelect || !updateForm || !archiveForm) {
            return;
        }

        const updateBaseUrl = updateForm.action.replace(/\/\d+$/, '');
        const archiveBaseUrl = archiveForm.action.replace(/\/\d+$/, '');
        const sectors = JSON.parse(sectorDataElement.textContent || '[]');

        const findSector = function (sectorId) {
            return sectors.find(function (item) {
                return Number(item.sectorID) === Number(sectorId);
            });
        };

        updateSelect.addEventListener('change', function () {
            const selectedId = updateSelect.value;
            const selectedSector = findSector(selectedId);
            updateForm.action = selectedId
                ? updateBaseUrl + '/' + selectedId
                : updateBaseUrl + '/0';

            if (!selectedSector) {
                shortcodeInput.value = '';
                nameInput.value = '';
                descriptionInput.value = '';

                return;
            }

            shortcodeInput.value = selectedSector.shortcode || '';
            nameInput.value = selectedSector.name || '';
            descriptionInput.value = selectedSector.description || '';
        });

        archiveSelect.addEventListener('change', function () {
            const selectedId = archiveSelect.value;
            archiveForm.action = selectedId
                ? archiveBaseUrl + '/' + selectedId
                : archiveBaseUrl + '/0';
        });
    }

    window.initSectorManagementView = initSectorManagementView;

    document.addEventListener('DOMContentLoaded', function () {
        initSectorManagementView(document);
    });
})(window, document);
