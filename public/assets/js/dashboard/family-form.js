(function (window, document) {
    function initFamilyForm(rootElement) {
        const root = rootElement instanceof HTMLElement ? rootElement : document;
        const form = root.querySelector('#familyForm');

        if (!form || form.dataset.familyFormInitialized === '1') {
            return;
        }

        form.dataset.familyFormInitialized = '1';

        const wizardCard = form.closest('.family-wizard-card');
        const panels = Array.from(form.querySelectorAll('.family-step-panel'));
        const stepItems = Array.from((wizardCard || root).querySelectorAll('.family-wizard-steps .wizard-step'));
        const nextBtn = form.querySelector('#nextStepBtn');
        const prevBtn = form.querySelector('#prevStepBtn');
        const submitBtn = form.querySelector('#submitFamilyBtn');
        const resetBtn = form.querySelector('#resetFamilyBtn');
        const addMemberBtn = form.querySelector('#addMemberBtn');
        const memberRows = form.querySelector('#memberRows');
        const memberTemplate = root.querySelector('#memberTemplate');
        const memberRowsEmpty = form.querySelector('#memberRowsEmpty');
        const stepInfo = (wizardCard || root).querySelector('.wizard-header-left small');
        const sectorCategoryList = form.querySelector('#sectorCategoryList');
        const sectorNameList = form.querySelector('#sectorNameList');
        const sectorIdInput = form.querySelector('#sectorID');
        const sectorCatalogNode = form.querySelector('#sectorCatalogData');
        const entryTypeInput = form.querySelector('#entryType');
        const entryButtons = Array.from(form.querySelectorAll('.entry-type-btn'));
        const entryPanels = Array.from(form.querySelectorAll('[data-entry-panel]'));
        const headSummaryName = form.querySelector('#headSummaryName');
        const headSummaryBirthday = form.querySelector('#headSummaryBirthday');
        const headSummarySex = form.querySelector('#headSummarySex');
        const headSummaryCivil = form.querySelector('#headSummaryCivil');
        const headSummaryContact = form.querySelector('#headSummaryContact');
        const headSummaryEducation = form.querySelector('#headSummaryEducation');
        const headSummaryJob = form.querySelector('#headSummaryJob');
        const headSummaryIncome = form.querySelector('#headSummaryIncome');
        const headSummarySectors = form.querySelector('#headSummarySectors');
        const headSummaryServices = form.querySelector('#headSummaryServices');
        let memberIndex = 0;
        let currentStep = 1;
        let entryType = entryTypeInput ? entryTypeInput.value : 'head';
        let sectorCatalog = {};

        if (sectorCatalogNode) {
            try {
                sectorCatalog = JSON.parse(sectorCatalogNode.textContent || '{}');
            } catch (error) {
                sectorCatalog = {};
            }
        }

        function totalSteps() {
            return entryType === 'member' ? 2 : 3;
        }

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

            const checkedBoxes = Array.from(sectorNameList.querySelectorAll('input[type="checkbox"]:checked'));
            const selectedItems = checkedBoxes.map(function (checkbox) {
                return {
                    sectorID: checkbox.value,
                    name: checkbox.dataset.name || '',
                    description: checkbox.dataset.description || ''
                };
            });

            if (sectorIdInput) {
                sectorIdInput.value = selectedItems.length > 0 ? String(selectedItems[0].sectorID || '') : '';
                sectorIdInput.setCustomValidity(selectedItems.length > 0 ? '' : 'Please select at least one sector name.');
            }
        }

        function populateSectorsByCategory() {
            if (!sectorCategoryList || !sectorNameList) {
                return;
            }

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

        function setStep(step) {
            currentStep = Math.max(1, Math.min(totalSteps(), step));

            panels.forEach(function (panel) {
                panel.classList.toggle('is-visible', Number(panel.dataset.step) === currentStep);
            });

            stepItems.forEach(function (item) {
                const stepTarget = Number(item.dataset.stepTarget);

                item.style.display = stepTarget > totalSteps() ? 'none' : '';
                item.classList.toggle('is-active', stepTarget === currentStep);
            });

            if (stepInfo) {
                if (currentStep === 1) {
                    stepInfo.textContent = 'Step 1 of ' + totalSteps() + ' - Person details';
                } else if (currentStep === 2) {
                    stepInfo.textContent = 'Step 2 of ' + totalSteps() + ' - Sector & services';
                } else {
                    stepInfo.textContent = 'Step 3 of 3 - Family members';
                }
            }

            if (prevBtn) {
                prevBtn.style.display = currentStep === 1 ? 'none' : '';
            }

            if (nextBtn) {
                nextBtn.style.display = currentStep === totalSteps() ? 'none' : '';
            }

            if (submitBtn) {
                submitBtn.style.display = currentStep === totalSteps() ? '' : 'none';
            }

            if (resetBtn) {
                resetBtn.style.display = currentStep === totalSteps() ? 'none' : '';
            }

            if (currentStep === 3 && entryType === 'head') {
                updateHeadSummary();
            }
        }

        function togglePanelFields(panel, enabled) {
            Array.from(panel.querySelectorAll('input, select, textarea')).forEach(function (field) {
                field.disabled = !enabled;
            });
        }

        function setEntryType(nextEntryType) {
            entryType = nextEntryType === 'member' ? 'member' : 'head';

            if (entryTypeInput) {
                entryTypeInput.value = entryType;
            }

            entryButtons.forEach(function (button) {
                const isActive = button.dataset.entryType === entryType;

                button.classList.toggle('is-active', isActive);
                button.classList.toggle('btn-primary', isActive);
                button.classList.toggle('btn-outline-secondary', !isActive);
            });

            entryPanels.forEach(function (panel) {
                const isActive = panel.dataset.entryPanel === entryType;

                panel.style.display = isActive ? '' : 'none';
                togglePanelFields(panel, isActive);
            });

            if (memberRows) {
                togglePanelFields(memberRows, entryType === 'head');
            }

            if (addMemberBtn) {
                addMemberBtn.disabled = entryType !== 'head';
            }

            setStep(Math.min(currentStep, totalSteps()));
        }

        function setMemberRowsEmptyState() {
            if (!memberRows || !memberRowsEmpty) {
                return;
            }

            memberRowsEmpty.style.display = memberRows.children.length === 0 ? '' : 'none';
        }

        function updateHeadSummary() {
            function renderSummaryList(container, items) {
                if (!container) {
                    return;
                }

                if (!Array.isArray(items) || items.length === 0) {
                    container.textContent = '-';

                    return;
                }

                const listItems = items.map(function (item) {
                    return '<li>' + item.replace(/[&<>"']/g, function (char) {
                        return {
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#039;'
                        }[char] || char;
                    }) + '</li>';
                }).join('');

                container.innerHTML = '<ul>' + listItems + '</ul>';
            }

            const firstName = (form.querySelector('#head_firstname') || {}).value || '';
            const middleName = (form.querySelector('#head_middlename') || {}).value || '';
            const lastName = (form.querySelector('#head_lastname') || {}).value || '';
            const suffix = (form.querySelector('#head_suffix') || {}).value || '';
            const fullName = [firstName, middleName, lastName, suffix].filter(Boolean).join(' ').trim();

            if (headSummaryName) {
                headSummaryName.textContent = fullName !== '' ? fullName : '-';
            }

            if (headSummaryBirthday) {
                const value = (form.querySelector('#head_birthday') || {}).value || '';
                headSummaryBirthday.textContent = value !== '' ? value : '-';
            }

            if (headSummarySex) {
                const value = (form.querySelector('#head_sex') || {}).value || '';
                headSummarySex.textContent = value !== '' ? value : '-';
            }

            if (headSummaryCivil) {
                const value = (form.querySelector('#head_civilstatus') || {}).value || '';
                headSummaryCivil.textContent = value !== '' ? value : '-';
            }

            if (headSummaryContact) {
                const value = (form.querySelector('#head_contactnumber') || {}).value || '';
                headSummaryContact.textContent = value !== '' ? value : '-';
            }

            if (headSummaryEducation) {
                const value = (form.querySelector('#head_education') || {}).value || '';
                headSummaryEducation.textContent = value !== '' ? value : '-';
            }

            if (headSummaryJob) {
                const value = (form.querySelector('#head_job') || {}).value || '';
                headSummaryJob.textContent = value !== '' ? value : '-';
            }

            if (headSummaryIncome) {
                const salarySelect = form.querySelector('#head_salary');
                const label = salarySelect && salarySelect.selectedOptions.length > 0
                    ? salarySelect.selectedOptions[0].textContent
                    : '';
                headSummaryIncome.textContent = (label || '').trim() !== '' ? label.trim() : '-';
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

            renderSummaryList(headSummarySectors, selectedSectors);
            renderSummaryList(headSummaryServices, selectedServices);
        }

        function createMemberRow() {
            if (!memberTemplate || !memberRows || entryType !== 'head') {
                return;
            }

            const fragment = memberTemplate.content.cloneNode(true);
            const row = fragment.querySelector('.member-row');

            if (!row) {
                return;
            }

            row.querySelectorAll('[data-name]').forEach(function (input) {
                const fieldName = input.getAttribute('data-name') || '';

                if (fieldName === '') {
                    return;
                }

                if (fieldName.endsWith('[]')) {
                    const baseName = fieldName.slice(0, -2);
                    input.setAttribute('name', 'members[' + memberIndex + '][' + baseName + '][]');

                    return;
                }

                input.setAttribute('name', 'members[' + memberIndex + '][' + fieldName + ']');
            });

            memberRows.appendChild(fragment);
            memberIndex += 1;
            setMemberRowsEmptyState();
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                setStep(currentStep + 1);
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                setStep(currentStep - 1);
            });
        }

        if (addMemberBtn) {
            addMemberBtn.addEventListener('click', createMemberRow);
        }

        entryButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setEntryType(button.dataset.entryType);
            });
        });

        if (memberRows) {
            memberRows.addEventListener('click', function (event) {
                const target = event.target;

                if (target instanceof HTMLElement && target.classList.contains('remove-member')) {
                    const row = target.closest('.member-row');

                    if (row) {
                        row.remove();
                        setMemberRowsEmptyState();
                    }
                }
            });
        }

        ['#head_firstname', '#head_middlename', '#head_lastname', '#head_suffix', '#head_birthday', '#head_sex', '#head_civilstatus', '#head_contactnumber', '#head_education', '#head_job', '#head_salary'].forEach(function (selector) {
            const element = form.querySelector(selector);

            if (element) {
                element.addEventListener('input', updateHeadSummary);
                element.addEventListener('change', updateHeadSummary);
            }
        });

        if (sectorCategoryList) {
            sectorCategoryList.addEventListener('change', function (event) {
                const target = event.target;

                if (target instanceof HTMLInputElement && target.type === 'checkbox') {
                    populateSectorsByCategory();
                    updateHeadSummary();
                }
            });
        }

        if (sectorNameList) {
            sectorNameList.addEventListener('change', function (event) {
                const target = event.target;

                if (target instanceof HTMLInputElement && target.type === 'checkbox') {
                    updateSectorSelection();
                    updateHeadSummary();
                }
            });
        }

        form.addEventListener('change', function (event) {
            const target = event.target;

            if (target instanceof HTMLInputElement && target.name === 'service_ids[]') {
                updateHeadSummary();
            }
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                window.setTimeout(function () {
                    if (memberRows) {
                        memberRows.innerHTML = '';
                    }

                    memberIndex = 0;
                    setEntryType('head');
                    resetSectorSelection();
                    setMemberRowsEmptyState();
                    updateHeadSummary();
                    setStep(1);
                }, 0);
            });
        }

        resetSectorSelection();
        setMemberRowsEmptyState();
        updateHeadSummary();

        stepItems.forEach(function (item) {
            item.addEventListener('click', function () {
                setStep(Number(item.dataset.stepTarget));
            });
        });

        setEntryType(entryType);
        setStep(1);
    }

    window.initFamilyForm = initFamilyForm;

    document.addEventListener('DOMContentLoaded', function () {
        initFamilyForm(document);
    });
})(window, document);
