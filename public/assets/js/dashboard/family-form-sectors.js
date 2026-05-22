(function (window, document) {
    function createFamilyFormSectorHandlers(config) {
        const q = config.q;
        const qa = config.qa;
        const sectorCategoryList = config.sectorCategoryList;
        const sectorNameList = config.sectorNameList;
        const sectorIdInput = config.sectorIdInput;
        const sectorCatalog = config.sectorCatalog;
        const state = config.state;

        function resetSectorSelection() {
            if (!sectorNameList) {
                return;
            }

            sectorNameList.innerHTML = '<small class="text-muted">Select one or more sector categories first.</small>';

            if (sectorIdInput) {
                sectorIdInput.value = '';
                sectorIdInput.setCustomValidity('Please select at least one sector name.');
            }
        }

        function updateSectorSelection() {
            if (!sectorCategoryList || !sectorNameList) {
                return;
            }

            const selectedCount = qa(sectorNameList, 'input[type="checkbox"]:checked').length;

            if (sectorIdInput) {
                const firstSelected = q(sectorNameList, 'input[type="checkbox"]:checked');
                sectorIdInput.value = firstSelected ? String(firstSelected.value || '') : '';
                sectorIdInput.setCustomValidity(selectedCount > 0 ? '' : 'Please select at least one sector name.');
            }
        }

        function populateSectorsByCategory() {
            if (!sectorCategoryList || !sectorNameList) {
                return;
            }

            const selectedCategories = qa(sectorCategoryList, 'input[type="checkbox"]:checked').map(function (checkbox) {
                return checkbox.value;
            });
            const options = [];
            const seenSectorIds = new Set();

            selectedCategories.forEach(function (category) {
                const categoryOptions = Array.isArray(sectorCatalog[category]) ? sectorCatalog[category] : [];

                categoryOptions.forEach(function (option) {
                    const key = String(option.sectorID || '');

                    if (seenSectorIds.has(key)) {
                        return;
                    }

                    seenSectorIds.add(key);
                    options.push(option);
                });
            });

            sectorNameList.innerHTML = '';

            if (options.length === 0) {
                sectorNameList.innerHTML = '<small class="text-muted">No sector names available for selected sector(s).</small>';
                updateSectorSelection();

                return;
            }

            options.forEach(function (option) {
                const wrapper = document.createElement('label');
                wrapper.className = 'form-check mb-1';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'form-check-input';
                checkbox.name = 'sector_ids[]';
                checkbox.value = String(option.sectorID || '');
                checkbox.dataset.name = String(option.name || '');
                checkbox.dataset.description = String(option.description || '');
                checkbox.checked = state.selectedSectorIds.includes(checkbox.value);

                const labelText = document.createElement('span');
                labelText.className = 'form-check-label';
                const description = String(option.description || '').trim();
                labelText.textContent = description !== ''
                    ? String(option.name || '') + ' - ' + description
                    : String(option.name || '');

                wrapper.appendChild(checkbox);
                wrapper.appendChild(labelText);
                sectorNameList.appendChild(wrapper);
            });

            updateSectorSelection();
        }

        return {
            resetSectorSelection: resetSectorSelection,
            updateSectorSelection: updateSectorSelection,
            populateSectorsByCategory: populateSectorsByCategory,
        };
    }

    window.createFamilyFormSectorHandlers = createFamilyFormSectorHandlers;
})(window, document);
