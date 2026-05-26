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
        return;
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

        const selectedSectors = Array.from(form.querySelectorAll('input[name="sectors[]"]:checked')).map(function (checkbox) {
            return String(checkbox.dataset.name || '').trim();
        }).filter(function (value) {
            return value !== '';
        });
        const selectedServices = Array.from(form.querySelectorAll('input[name="services[]"]:checked')).map(function (checkbox) {
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
                    input.checked = arrayValue.includes(String(input.value || ''));
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
