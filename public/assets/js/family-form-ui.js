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

    function collectSelectedSectorIds(form) {
        return Array.from(form.querySelectorAll('#sectorNameList input[type="checkbox"]:checked')).map(function (checkbox) {
            return checkbox.value;
        });
    }

    function readSectorCatalog(sectorCategoryList) {
        if (!sectorCategoryList) {
            return {};
        }

        try {
            return JSON.parse(sectorCategoryList.dataset.sectorCatalog || '{}');
        } catch (error) {
            return {};
        }
    }

    function resetSectorSelection(sectorNameList, sectorIdInput) {
        if (!sectorNameList) {
            return;
        }

        sectorNameList.innerHTML = '<small class="text-muted">Select one or more sector categories first.</small>';

        if (sectorIdInput) {
            sectorIdInput.value = '';
            sectorIdInput.setCustomValidity('Please select at least one sector name.');
        }
    }

    function updateSectorSelection(sectorNameList, sectorIdInput) {
        if (!sectorNameList) {
            return;
        }

        const checkedBoxes = Array.from(sectorNameList.querySelectorAll('input[type="checkbox"]:checked'));
        const selectedIds = checkedBoxes.map(function (checkbox) {
            return String(checkbox.value || '').trim();
        }).filter(function (value) {
            return value !== '';
        });

        if (sectorIdInput) {
            sectorIdInput.value = selectedIds.length > 0 ? '[' + selectedIds.join(',') + ']' : '';
            sectorIdInput.setCustomValidity(selectedIds.length > 0 ? '' : 'Please select at least one sector name.');
        }
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
        const selectedCategories = Array.from(sectorCategoryList.querySelectorAll('input[type="checkbox"]:checked')).map(function (checkbox) {
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
            updateSectorSelection(sectorNameList, sectorIdInput);

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
            checkbox.checked = selectedIds.has(checkbox.value);
            checkbox.dataset.name = String(option.name || '');
            checkbox.dataset.description = String(option.description || '');

            const labelText = document.createElement('span');
            labelText.className = 'form-check-label';
            labelText.textContent = String(option.description || '').trim() !== ''
                ? String(option.name || '') + ' - ' + String(option.description || '').trim()
                : String(option.name || '');

            wrapper.appendChild(checkbox);
            wrapper.appendChild(labelText);
            sectorNameList.appendChild(wrapper);
        });

        updateSectorSelection(sectorNameList, sectorIdInput);
    }

    function stepCopy(currentStep, totalSteps, entryType) {
        if (currentStep === 1) {
            return 'Step 1 of ' + totalSteps + ' - ' + (entryType === 'member' ? 'Family Member' : 'Head of the Family');
        }

        if (currentStep === 2) {
            return 'Step 2 of ' + totalSteps + ' - Sector & services';
        }

        return 'Step 3 of 3 - Family members';
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
            ['education', '#head_education'],
            ['job', '#head_job']
        ].forEach(function (field) {
            const target = targets[field[0]];
            const value = (form.querySelector(field[1]) || {}).value || '';

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

        const selectedSectors = Array.from(form.querySelectorAll('#sectorNameList input[type="checkbox"]:checked')).map(function (checkbox) {
            return String(checkbox.dataset.name || '').trim();
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

                return;
            }

            if (value === null || typeof value === 'undefined') {
                return;
            }

            if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
                input.value = String(value);
            }
        });

        memberRows.appendChild(fragment);

        return memberIndex + 1;
    }

    window.FamilyFormUI = {
        collectSelectedSectorIds: collectSelectedSectorIds,
        createMemberRow: createMemberRow,
        readSectorCatalog: readSectorCatalog,
        renderHeadSummary: renderHeadSummary,
        populateSectorsByCategory: populateSectorsByCategory,
        resetSectorSelection: resetSectorSelection,
        setEntryType: setEntryType,
        setHidden: setHidden,
        setMemberRowsEmptyState: setMemberRowsEmptyState,
        setWizardStep: setWizardStep,
        updateSectorSelection: updateSectorSelection
    };
})(window);
