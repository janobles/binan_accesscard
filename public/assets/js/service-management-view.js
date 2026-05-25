(function (window, document) {
    function initServiceManagementView(rootElement) {
        const root = rootElement instanceof HTMLElement ? rootElement : document;
        const panel = root.querySelector('[data-service-management-root]');

        if (!panel || panel.dataset.serviceManagementInitialized === '1') {
            return;
        }

        panel.dataset.serviceManagementInitialized = '1';

        const addTrigger = root.querySelector('#serviceAddTrigger');
        const updateTrigger = root.querySelector('#serviceUpdateTrigger');
        const deleteTrigger = root.querySelector('#serviceDeleteTrigger');
        const addPopup = root.querySelector('#serviceAddPopup');
        const updatePopup = root.querySelector('#serviceUpdatePopup');
        const deletePopup = root.querySelector('#serviceDeletePopup');
        const addPopupCloseButtons = root.querySelectorAll('.js-close-service-add-popup');
        const updatePopupCloseButtons = root.querySelectorAll('.js-close-service-update-popup');
        const deletePopupCloseButtons = root.querySelectorAll('.js-close-service-delete-popup');
        const serviceDataElement = root.querySelector('#serviceListData');
        const updateSelect = root.querySelector('#serviceUpdateSelect');
        const archiveSelect = root.querySelector('#serviceArchiveSelect');
        const updateForm = root.querySelector('#serviceUpdateForm');
        const archiveForm = root.querySelector('#serviceArchiveForm');
        const categoryInput = root.querySelector('#serviceUpdateCategory');
        const nameInput = root.querySelector('#serviceUpdateName');
        const descriptionInput = root.querySelector('#serviceUpdateDescription');

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

        if (!serviceDataElement || !updateSelect || !archiveSelect || !updateForm || !archiveForm) {
            return;
        }

        const updateBaseUrl = updateForm.action.replace(/\/\d+$/, '');
        const archiveBaseUrl = archiveForm.action.replace(/\/\d+$/, '');

        const services = JSON.parse(serviceDataElement.textContent || '[]');

        const findService = function (serviceId) {
            return services.find(function (item) {
                return Number(item.serviceID) === Number(serviceId);
            });
        };

        updateSelect.addEventListener('change', function () {
            const selectedId = updateSelect.value;
            const selectedService = findService(selectedId);
            updateForm.action = selectedId
                ? updateBaseUrl + '/' + selectedId
                : updateBaseUrl + '/0';

            if (!selectedService) {
                categoryInput.value = '';
                nameInput.value = '';
                descriptionInput.value = '';

                return;
            }

            categoryInput.value = selectedService.category || '';
            nameInput.value = selectedService.name || '';
            descriptionInput.value = selectedService.description || '';
        });

        archiveSelect.addEventListener('change', function () {
            const selectedId = archiveSelect.value;
            archiveForm.action = selectedId
                ? archiveBaseUrl + '/' + selectedId
                : archiveBaseUrl + '/0';
        });
    }

    window.initServiceManagementView = initServiceManagementView;

    document.addEventListener('DOMContentLoaded', function () {
        initServiceManagementView(document);
    });
})(window, document);
