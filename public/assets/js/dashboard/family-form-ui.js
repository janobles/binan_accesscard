// Pure UI rendering helpers for the family wizard. No event binding here —
// family-form.js owns all event wiring and calls into this module via window.FamilyFormUI.
//
// Key helpers:
//   setWizardStep()          — shows/hides panels and prev/next/submit buttons
//   setEntryType()           — switches between "Head" and "Member" entry modes
//   createMemberRow()        — clones #memberTemplate, assigns indexed field names,
//                              and pre-fills values when editing an existing record
//   renderHeadSummary()      — populates the step-3 summary card from step-1 fields
//   populateSectorsByCategory() — rebuilds the sector checkbox list from sectorCatalog
//                                 data embedded in the page by PHP
//   syncOtherControl()       — shows/hides the freetext input paired with a
//                              js-other-select when "Other" is chosen
//   applyOtherValues()       — on submit, injects a generated <option> so the custom
//                              text value is what gets posted to the server
//   initDropdownChecklists() — wires the custom dropdown-checklist widgets for
//                              multi-select sector/service fields inside member rows
//
// Connected to:
//   - family-form.js : consumes all exports via window.FamilyFormUI
//   - Views : Family/form.php, head-fields.php,
//             Family/member-fields.php, Lookups/picker.php
// Shared UI helpers for the family wizard; backend validation stays in PHP.
(function (window) {
    function setHidden(element, hidden) {
        if (element) {
            element.classList.toggle('family-form-hidden', hidden);
        }
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char] || char;
        });
    }

    // Cleans a custom "Other" free-text value at submit: keeps letters, digits, spaces
    // and . , ' - / & ( ), collapses whitespace, then Title Case (first letter of each
    // word capitalized). Only ever runs on genuinely typed "Other" text, so canonical
    // dropdown picks stay untouched.
    function cleanOtherValue(value) {
        return String(value || '')
            .replace(/[^\p{L}\p{N}\s.,'\-/&()]/gu, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase()
            .replace(/(^|[^\p{L}])(\p{L})/gu, function (match, boundary, letter) {
                return boundary + letter.toUpperCase();
            });
    }

    function isOtherValue(value) {
        const normalized = String(value || '').trim().toLowerCase();

        return normalized === 'other' || normalized === 'others' || normalized === '__other__';
    }

    function findOtherInput(select) {
        if (!select) {
            return null;
        }

        const selector = select.dataset.otherInput || '';

        if (selector !== '') {
            return document.querySelector(selector);
        }

        const field = select.dataset.otherField || '';
        const container = select.closest('.col-md-3, .col-md-4, .col-md-6, .col-md-8, .col-md-12, .col-12, .member-row, form') || select.parentElement;

        return field !== '' && container
            ? container.querySelector('[data-other-for="' + field + '"]')
            : null;
    }

    function optionExists(select, value) {
        return Array.from(select.options).some(function (option) {
            return option.value === value;
        });
    }

    function selectedFieldValue(select) {
        const otherInput = findOtherInput(select);

        if (isOtherValue(select.value) && otherInput) {
            const otherValue = String(otherInput.value || '').trim();

            return otherValue !== '' ? otherValue : select.value;
        }

        return select.value;
    }

    function syncOtherControl(select) {
        const otherInput = findOtherInput(select);

        if (!otherInput) {
            return;
        }

        const shouldShow = isOtherValue(select.value);

        setHidden(otherInput, !shouldShow);
        otherInput.required = shouldShow;

        if (!shouldShow) {
            otherInput.value = '';
        }
    }

    function setSelectValueWithOther(select, value) {
        const normalizedValue = String(value || '');

        if (normalizedValue === '' || optionExists(select, normalizedValue)) {
            select.value = normalizedValue;
            syncOtherControl(select);

            return;
        }

        const otherOption = Array.from(select.options).find(function (option) {
            return isOtherValue(option.value);
        });

        if (otherOption) {
            otherOption.selected = true;
            const otherInput = findOtherInput(select);

            if (otherInput) {
                otherInput.value = normalizedValue;
            }

            syncOtherControl(select);
        }
    }

    function applyOtherValues(root) {
        Array.from(root.querySelectorAll('.js-other-select')).forEach(function (select) {
            const otherInput = findOtherInput(select);

            if (!otherInput || !isOtherValue(select.value)) {
                return;
            }

            const otherValue = cleanOtherValue(otherInput.value);

            if (otherValue === '') {
                return;
            }

            let generatedOption = Array.from(select.options).find(function (option) {
                return option.dataset.generatedOther === '1';
            });

            if (!generatedOption) {
                generatedOption = document.createElement('option');
                generatedOption.dataset.generatedOther = '1';
                select.appendChild(generatedOption);
            }

            generatedOption.value = otherValue;
            generatedOption.textContent = otherValue;
            generatedOption.selected = true;
        });
    }

    function syncOtherControls(root) {
        Array.from(root.querySelectorAll('.js-other-select')).forEach(syncOtherControl);
    }

    let dropdownChecklistCloseBound = false;

    function dropdownChecklistInputLabel(input) {
        if (!input) {
            return '';
        }

        const dataLabel = String(input.dataset.label || '').trim();

        if (dataLabel !== '') {
            return dataLabel;
        }

        const label = input.closest('label');

        return label ? String(label.textContent || '').trim() : '';
    }

    function updateDropdownChecklist(dropdown) {
        if (!dropdown) {
            return;
        }

        const label = dropdown.querySelector('[data-dropdown-checklist-label]');
        const placeholder = String(dropdown.dataset.placeholder || 'Select options');
        const checked = Array.from(dropdown.querySelectorAll('input[type="checkbox"]:checked'));
        const labels = checked.map(dropdownChecklistInputLabel).filter(function (value) {
            return value !== '';
        });

        let labelText = placeholder;

        if (labels.length === 1) {
            labelText = labels[0];
        } else if (labels.length === 2) {
            labelText = labels.join(', ');
        } else if (labels.length > 2) {
            labelText = labels.length + ' selected';
        }

        if (label) {
            label.textContent = labelText;
            label.title = labels.join(', ');
        }

        dropdown.classList.toggle('has-selection', labels.length > 0);
    }

    function closeDropdownChecklists(exceptDropdown) {
        Array.from(document.querySelectorAll('.js-dropdown-checklist.is-open')).forEach(function (dropdown) {
            if (dropdown !== exceptDropdown) {
                dropdown.classList.remove('is-open');
            }
        });
    }

    function bindDropdownChecklistClose() {
        if (dropdownChecklistCloseBound) {
            return;
        }

        document.addEventListener('click', function (event) {
            const target = event.target;

            if (target instanceof Element && target.closest('.js-dropdown-checklist')) {
                return;
            }

            closeDropdownChecklists(null);
        });

        dropdownChecklistCloseBound = true;
    }

    function initDropdownChecklists(root) {
        const scope = root instanceof Element ? root : document;

        bindDropdownChecklistClose();

        Array.from(scope.querySelectorAll('.js-dropdown-checklist')).forEach(function (dropdown) {
            if (dropdown.dataset.dropdownChecklistInitialized !== '1') {
                dropdown.dataset.dropdownChecklistInitialized = '1';

                const toggle = dropdown.querySelector('[data-dropdown-checklist-toggle]');

                if (toggle) {
                    toggle.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();

                        const shouldOpen = !dropdown.classList.contains('is-open');
                        closeDropdownChecklists(dropdown);
                        dropdown.classList.toggle('is-open', shouldOpen);
                    });
                }

                dropdown.addEventListener('change', function (event) {
                    const target = event.target;

                    if (target instanceof HTMLInputElement && target.type === 'checkbox') {
                        updateDropdownChecklist(dropdown);
                    }
                });
            }

            updateDropdownChecklist(dropdown);
        });
    }

    function collectSelectedSectorIds(form) {
        return Array.from(form.querySelectorAll('input[name="sectors[]"]:checked')).map(function (checkbox) {
            return checkbox.value;
        });
    }

    function readSectorCatalog(sectorCategoryList) {
        return {};
    }

    function resetSectorSelection(sectorNameList, sectorIdInput) {
        if (!sectorNameList) {
            return;
        }

        Array.from(sectorNameList.querySelectorAll('input[type="checkbox"]')).forEach(function (checkbox) {
            checkbox.checked = false;
        });
    }

    function updateSectorSelection(sectorNameList, sectorIdInput) {
        return;
    }

    function populateSectorsByCategory(config) {
        const sectorCatalog = config.sectorCatalog || {};
        const sectorCategoryList = config.sectorCategoryList;
        const sectorNameList = config.sectorNameList;
        const sectorIdInput = config.sectorIdInput;
        const selectedSectorIds = Array.isArray(config.selectedSectorIds)
            ? config.selectedSectorIds.map(String)
            : [];

        if (!sectorCategoryList || !sectorNameList) {
            return;
        }

        const previouslySelectedIds = new Set(Array.from(sectorNameList.querySelectorAll('input[type="checkbox"]:checked')).map(function (checkbox) {
            return String(checkbox.value || '');
        }));
        const selectedIds = selectedSectorIds.length > 0 ? new Set(selectedSectorIds) : previouslySelectedIds;
        let selectedCategories = Array.from(sectorCategoryList.querySelectorAll('input[type="checkbox"]:checked')).map(function (checkbox) {
            return checkbox.value;
        });

        if (selectedCategories.length === 0 && sectorCategoryList.dataset.autoSelectAll === '1') {
            selectedCategories = Object.keys(sectorCatalog);
        }
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
                options.push({
                    group: category,
                    option: option
                });
            });
        });

        sectorNameList.innerHTML = '';

        if (options.length === 0) {
            sectorNameList.innerHTML = '<small class="text-muted">No sector names available for selected sector(s).</small>';
            updateSectorSelection(sectorNameList, sectorIdInput);

            return;
        }

        const groupedOptions = {};
        const groupOrder = [];

        options.forEach(function (item) {
            const group = String(item.group || '').trim();
            const groupLabel = group;

            if (!Object.prototype.hasOwnProperty.call(groupedOptions, groupLabel)) {
                groupedOptions[groupLabel] = [];
                groupOrder.push(groupLabel);
            }

            groupedOptions[groupLabel].push(item);
        });

        groupOrder.forEach(function (groupLabel) {
            const section = document.createElement('div');
            section.className = 'sector-name-section';

            const heading = document.createElement('div');
            heading.className = 'sector-name-section-title';
            heading.textContent = groupLabel;
            section.appendChild(heading);

            groupedOptions[groupLabel].forEach(function (item) {
            const option = item.option || {};
            const group = String(item.group || '').trim();
            const wrapper = document.createElement('label');
            wrapper.className = 'form-check mb-2 sector-name-option';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.name = 'sector_ids[]';
            checkbox.value = String(option.sectorID || '');
            checkbox.checked = selectedIds.has(checkbox.value);
            checkbox.dataset.name = String(option.name || '');
            checkbox.dataset.description = String(option.description || '');
            checkbox.dataset.group = group;

            const labelText = document.createElement('span');
            labelText.className = 'form-check-label sector-name-label';

            const sectorText = document.createElement('span');
            sectorText.className = 'sector-name-text';
            sectorText.textContent = String(option.shortcode || '').trim() !== ''
                ? String(option.shortcode || '').trim() + ' - ' + String(option.name || '').trim()
                : String(option.name || '').trim();

            labelText.appendChild(sectorText);
            wrapper.appendChild(checkbox);
            wrapper.appendChild(labelText);
                section.appendChild(wrapper);
            });

            sectorNameList.appendChild(section);
        });

        updateSectorSelection(sectorNameList, sectorIdInput);
    }

    function stepCopy(currentStep, totalSteps, entryType) {
        if (currentStep === 1) {
            return 'Step 1 of ' + totalSteps + ' - ' + (entryType === 'member' ? 'Member' : 'Record Head');
        }

        if (currentStep === 2) {
            return 'Step 2 of ' + totalSteps + ' - Sectors, services & programs';
        }

        return 'Step 3 of 3 - Members';
    }

    function setWizardStep(config) {
        const currentStep = config.currentStep;
        const totalSteps = config.totalSteps;

        config.panels.forEach(function (panel) {
            panel.classList.toggle('is-visible', Number(panel.dataset.step) === currentStep);
        });

        config.stepItems.forEach(function (item) {
            const stepTarget = Number(item.dataset.stepTarget);

            setHidden(item, stepTarget > totalSteps);
            item.classList.toggle('is-active', stepTarget === currentStep);
        });

        if (config.stepInfo) {
            config.stepInfo.textContent = stepCopy(currentStep, totalSteps, config.entryType);
        }

        setHidden(config.prevBtn, currentStep === 1);
        setHidden(config.nextBtn, currentStep === totalSteps);
        setHidden(config.submitBtn, currentStep !== totalSteps);
        setHidden(config.resetBtn, currentStep === totalSteps);

        if (currentStep === 3 && config.entryType === 'head' && typeof config.onHeadSummary === 'function') {
            config.onHeadSummary();
        }
    }

    function togglePanelFields(panel, enabled) {
        Array.from(panel.querySelectorAll('input, select, textarea')).forEach(function (field) {
            field.disabled = !enabled;
        });
    }

    function setEntryType(config) {
        const entryType = config.entryType === 'member' ? 'member' : 'head';

        if (config.entryTypeInput) {
            config.entryTypeInput.value = entryType;
        }

        config.entryButtons.forEach(function (button) {
            const isActive = button.dataset.entryType === entryType;

            button.classList.toggle('is-active', isActive);
            button.classList.toggle('btn-primary', isActive);
            button.classList.toggle('btn-outline-secondary', !isActive);
        });

        config.entryPanels.forEach(function (panel) {
            const isActive = panel.dataset.entryPanel === entryType;

            setHidden(panel, !isActive);
            togglePanelFields(panel, isActive);
        });

        if (config.memberRows) {
            togglePanelFields(config.memberRows, entryType === 'head');
        }

        if (config.addMemberBtn) {
            config.addMemberBtn.disabled = entryType !== 'head';
        }
    }

    function setMemberRowsEmptyState(memberRows, memberRowsEmpty) {
        if (!memberRows || !memberRowsEmpty) {
            return;
        }

        setHidden(memberRowsEmpty, memberRows.children.length !== 0);
    }

    function renderSummaryList(container, items) {
        if (!container) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            container.textContent = '-';

            return;
        }

        container.innerHTML = '<ul>' + items.map(function (item) {
            return '<li>' + escapeHtml(item) + '</li>';
        }).join('') + '</ul>';
    }

    function renderHeadSummary(form, targets) {
        const firstName = (form.querySelector('#head_firstname') || {}).value || '';
        const middleName = (form.querySelector('#head_middlename') || {}).value || '';
        const lastName = (form.querySelector('#head_lastname') || {}).value || '';
        const suffix = (form.querySelector('#head_suffix') || {}).value || '';
        const fullName = [firstName, middleName, lastName, suffix].filter(Boolean).join(' ').trim();

        if (targets.name) {
            targets.name.textContent = fullName !== '' ? fullName : '-';
        }

        [
            ['birthday', '#head_birthday'],
            ['sex', '#head_sex'],
            ['civil', '#head_civilstatus'],
            ['contact', '#head_contactnumber'],
            ['religion', '#head_religion'],
            ['education', '#head_education'],
            ['job', '#head_job']
        ].forEach(function (field) {
            const target = targets[field[0]];
            const element = form.querySelector(field[1]);
            const value = element instanceof HTMLSelectElement
                ? selectedFieldValue(element)
                : ((element || {}).value || '');

            if (target) {
                target.textContent = value !== '' ? value : '-';
            }
        });

        if (targets.income) {
            const salarySelect = form.querySelector('#head_salary');
            const label = salarySelect && salarySelect.selectedOptions.length > 0
                ? salarySelect.selectedOptions[0].textContent
                : '';

            targets.income.textContent = (label || '').trim() !== '' ? label.trim() : '-';
        }

        if (targets.address) {
            const addressValue = ((form.querySelector('#head_address') || {}).value || '').trim();
            const barangayValue = ((form.querySelector('#head_barangay') || {}).value || '').trim();
            const fullAddress = [addressValue, barangayValue].filter(Boolean).join(', ');

            targets.address.textContent = fullAddress !== '' ? fullAddress : '-';
        }

        const selectedSectors = Array.from(form.querySelectorAll('input[name="sector_ids[]"]:checked')).map(function (checkbox) {
            const dataLabel = String(checkbox.dataset.label || '').trim();

            if (dataLabel !== '') {
                return dataLabel;
            }

            const label = checkbox.closest('label');

            return label ? String(label.textContent || '').trim() : '';
        }).filter(function (value) {
            return value !== '';
        });
        const selectedServices = Array.from(form.querySelectorAll('input[name="service_ids[]"]:checked')).map(function (checkbox) {
            const label = checkbox.closest('label');
            const text = label ? label.textContent : '';

            return (text || '').trim();
        }).filter(function (value) {
            return value !== '';
        });

        renderSummaryList(targets.sectors, selectedSectors);
        renderSummaryList(targets.services, selectedServices);
    }

    function createMemberRow(config) {
        const memberTemplate = config.memberTemplate;
        const memberRows = config.memberRows;
        const memberIndex = Number(config.memberIndex || 0);
        const memberData = config.memberData;

        if (!memberTemplate || !memberRows) {
            return memberIndex;
        }

        const fragment = memberTemplate.content.cloneNode(true);
        const row = fragment.querySelector('.member-row');

        if (!row) {
            return memberIndex;
        }

        row.querySelectorAll('[data-name]').forEach(function (input) {
            const fieldName = input.getAttribute('data-name') || '';

            if (fieldName === '') {
                return;
            }

            const isArrayField = fieldName.endsWith('[]');
            const baseName = isArrayField ? fieldName.slice(0, -2) : fieldName;

            if (isArrayField) {
                input.setAttribute('name', 'members[' + memberIndex + '][' + baseName + '][]');
            } else {
                input.setAttribute('name', 'members[' + memberIndex + '][' + fieldName + ']');
            }

            if (!memberData || typeof memberData !== 'object') {
                return;
            }

            const value = Object.prototype.hasOwnProperty.call(memberData, baseName)
                ? memberData[baseName]
                : null;

            if (isArrayField) {
                const arrayValue = Array.isArray(value) ? value.map(String) : [];

                if (input instanceof HTMLSelectElement) {
                    Array.from(input.options).forEach(function (option) {
                        option.selected = arrayValue.includes(option.value);
                    });
                }

                if (input instanceof HTMLInputElement && input.type === 'checkbox') {
                    input.checked = arrayValue.includes(input.value);
                }

                return;
            }

            if (value === null || typeof value === 'undefined') {
                return;
            }

            if (input instanceof HTMLSelectElement && input.classList.contains('js-other-select')) {
                setSelectValueWithOther(input, String(value));

                return;
            }

            if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
                input.value = String(value);
            }
        });

        syncOtherControls(row);
        initDropdownChecklists(row);

        memberRows.appendChild(fragment);

        return memberIndex + 1;
    }

    window.FamilyFormUI = {
        applyOtherValues: applyOtherValues,
        collectSelectedSectorIds: collectSelectedSectorIds,
        createMemberRow: createMemberRow,
        initDropdownChecklists: initDropdownChecklists,
        readSectorCatalog: readSectorCatalog,
        renderHeadSummary: renderHeadSummary,
        populateSectorsByCategory: populateSectorsByCategory,
        resetSectorSelection: resetSectorSelection,
        selectedFieldValue: selectedFieldValue,
        setEntryType: setEntryType,
        setHidden: setHidden,
        setMemberRowsEmptyState: setMemberRowsEmptyState,
        syncOtherControl: syncOtherControl,
        syncOtherControls: syncOtherControls,
        setWizardStep: setWizardStep,
        updateSectorSelection: updateSectorSelection
    };
})(window);
