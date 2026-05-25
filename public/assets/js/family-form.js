// Wires the family wizard events while keeping rendering helpers reusable.
(function (window, document) {
    function parseJsonNode(node, fallbackValue) {
        if (!node) {
            return fallbackValue;
        }

        try {
            return JSON.parse(node.textContent || 'null') || fallbackValue;
        } catch (error) {
            return fallbackValue;
        }
    }

    function normalizeIds(values) {
        if (!Array.isArray(values)) {
            return [];
        }

        return values.map(function (id) {
            return String(id || '').trim();
        }).filter(function (id) {
            return id !== '';
        });
    }

    function initFamilyForm(rootElement) {
        const ui = window.FamilyFormUI || {};
        const q = function (root, selector) {
            return root.querySelector(selector);
        };
        const qa = function (root, selector) {
            return Array.from(root.querySelectorAll(selector));
        };

        const root = rootElement instanceof HTMLElement ? rootElement : document;
        const form = q(root, '#familyForm');

        if (!form || form.dataset.familyFormInitialized === '1') {
            return;
        }

        form.dataset.familyFormInitialized = '1';

        const wizardCard = form.closest('.family-wizard-card');
        const isEditMode = form.dataset.editMode === '1';
        const formAlert = q(form, '#familyFormAlert');
        let isSubmitting = false;
        const uiRoot = wizardCard || root;
        const panels = qa(form, '.family-step-panel');
        const stepItems = qa(uiRoot, '.family-wizard-steps .wizard-step');
        const nextBtn = q(form, '#nextStepBtn');
        const prevBtn = q(form, '#prevStepBtn');
        const submitBtn = q(form, '#submitFamilyBtn');
        const resetBtn = q(form, '#resetFamilyBtn');
        const addMemberBtn = q(form, '#addMemberBtn');
        const memberRows = q(form, '#memberRows');
        const memberTemplate = q(root, '#memberTemplate');
        const memberRowsEmpty = q(form, '#memberRowsEmpty');
        const stepInfo = q(uiRoot, '.wizard-header-left small');
        const entryTypeInput = q(form, '#entryType');
        const entryButtons = qa(form, '[data-entry-type]');
        const entryPanels = qa(form, '[data-entry-panel]');
        const sectorCategoryList = q(form, '#sectorCategoryList');
        const sectorNameList = q(form, '#sectorNameList');
        const sectorIdInput = q(form, '#sectorID');
        const sectorCatalogNode = q(form, '#sectorCatalogData');
        const selectedSectorIdsNode = q(form, '#selectedSectorIdsData');
        const initialFamilyDataNode = q(root, '#initialFamilyData');
        const summaryTargets = {
            name: q(form, '#headSummaryName'),
            birthday: q(form, '#headSummaryBirthday'),
            sex: q(form, '#headSummarySex'),
            civil: q(form, '#headSummaryCivil'),
            contact: q(form, '#headSummaryContact'),
            education: q(form, '#headSummaryEducation'),
            job: q(form, '#headSummaryJob'),
            income: q(form, '#headSummaryIncome'),
            sectors: q(form, '#headSummarySectors'),
            services: q(form, '#headSummaryServices')
        };
        let currentStep = 1;
        let memberIndex = 0;
        let entryType = entryTypeInput ? entryTypeInput.value : 'head';
        let sectorCatalog = parseJsonNode(sectorCatalogNode, {});
        const initialFamilyData = parseJsonNode(initialFamilyDataNode, {});
        const state = {
            selectedSectorIds: normalizeIds(parseJsonNode(selectedSectorIdsNode, initialFamilyData.selectedSectorIds || [])),
        };

        function totalSteps() {
            return entryType === 'member' ? 2 : 3;
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

        function setFormAlert(type, message) {
            if (!formAlert) {
                return;
            }

            if (!message) {
                formAlert.innerHTML = '';
                return;
            }

            formAlert.innerHTML = '<div class="alert alert-' + type + '">' + escapeHtml(message) + '</div>';
        }

        function updateCsrfToken(token) {
            if (!token) {
                return;
            }

            // Keep CSRF in sync with FamilyController::store AJAX responses.
            qa(form, 'input[type="hidden"][name]').forEach(function (input) {
                if (String(input.name || '').toLowerCase().indexOf('csrf') !== -1) {
                    input.value = token;
                }
            });
        }

        function resetFamilyForm(clearAlert) {
            window.setTimeout(function () {
                if (memberRows) {
                    memberRows.innerHTML = '';
                }

                memberIndex = 0;
                setEntryType('head');
                resetSectorSelection();

                if (typeof ui.setMemberRowsEmptyState === 'function') {
                    ui.setMemberRowsEmptyState(memberRows, memberRowsEmpty);
                }

                updateHeadSummary();
                setStep(1);

                if (clearAlert) {
                    setFormAlert('', '');
                }
            }, 0);
        }

        function setHidden(element, hidden) {
            if (typeof ui.setHidden === 'function') {
                ui.setHidden(element, hidden);

                return;
            }

            if (element) {
                element.classList.toggle('family-form-hidden', hidden);
            }
        }

        function updateHeadSummary() {
            if (typeof ui.renderHeadSummary === 'function') {
                ui.renderHeadSummary(form, summaryTargets);
            }
        }

        function setStep(step) {
            currentStep = Math.max(1, Math.min(totalSteps(), Number(step) || 1));

            if (typeof ui.setWizardStep === 'function') {
                ui.setWizardStep({
                    currentStep: currentStep,
                    entryType: entryType,
                    panels: panels,
                    stepItems: stepItems,
                    stepInfo: stepInfo,
                    prevBtn: prevBtn,
                    nextBtn: nextBtn,
                    submitBtn: submitBtn,
                    resetBtn: resetBtn,
                    totalSteps: totalSteps(),
                    onHeadSummary: updateHeadSummary
                });

                return;
            }

            panels.forEach(function (panel) {
                panel.classList.toggle('is-visible', Number(panel.dataset.step) === currentStep);
            });

            stepItems.forEach(function (item) {
                item.classList.toggle('is-active', Number(item.dataset.stepTarget) === currentStep);
            });

            setHidden(prevBtn, currentStep === 1);
            setHidden(nextBtn, currentStep === totalSteps());
            setHidden(submitBtn, currentStep !== totalSteps());
            setHidden(resetBtn, currentStep === totalSteps());
        }

        function setEntryType(nextEntryType) {
            entryType = nextEntryType === 'member' ? 'member' : 'head';

            if (entryTypeInput) {
                entryTypeInput.value = entryType;
            }

            if (typeof ui.setEntryType === 'function') {
                ui.setEntryType({
                    addMemberBtn: addMemberBtn,
                    entryButtons: entryButtons,
                    entryPanels: entryPanels,
                    entryType: entryType,
                    entryTypeInput: entryTypeInput,
                    memberRows: memberRows
                });
            }

            setStep(Math.min(currentStep, totalSteps()));
        }

        function resetSectorSelection() {
            state.selectedSectorIds = [];

            if (typeof ui.resetSectorSelection === 'function') {
                ui.resetSectorSelection(sectorNameList, sectorIdInput);
            }
        }

        function updateSectorSelection() {
            state.selectedSectorIds = typeof ui.collectSelectedSectorIds === 'function'
                ? ui.collectSelectedSectorIds(form)
                : qa(form, '#sectorNameList input[type="checkbox"]:checked').map(function (checkbox) {
                    return checkbox.value;
                });

            if (typeof ui.updateSectorSelection === 'function') {
                ui.updateSectorSelection(sectorNameList, sectorIdInput);
            }
        }

        function populateSectorsByCategory() {
            if (typeof ui.populateSectorsByCategory !== 'function') {
                return;
            }

            ui.populateSectorsByCategory({
                sectorCatalog: sectorCatalog,
                sectorCategoryList: sectorCategoryList,
                sectorNameList: sectorNameList,
                sectorIdInput: sectorIdInput,
                selectedSectorIds: state.selectedSectorIds
            });
        }

        function createMemberRow(memberData) {
            if ((!memberData || typeof memberData !== 'object') && entryType !== 'head') {
                return;
            }

            if (typeof ui.createMemberRow === 'function') {
                memberIndex = ui.createMemberRow({
                    memberTemplate: memberTemplate,
                    memberRows: memberRows,
                    memberIndex: memberIndex,
                    memberData: memberData
                });
            }

            if (typeof ui.setMemberRowsEmptyState === 'function') {
                ui.setMemberRowsEmptyState(memberRows, memberRowsEmpty);
            }
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
            addMemberBtn.addEventListener('click', function () {
                createMemberRow();
            });
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
                    }

                    if (typeof ui.setMemberRowsEmptyState === 'function') {
                        ui.setMemberRowsEmptyState(memberRows, memberRowsEmpty);
                    }
                }
            });
        }

        [
            '#head_firstname',
            '#head_middlename',
            '#head_lastname',
            '#head_suffix',
            '#head_birthday',
            '#head_sex',
            '#head_civilstatus',
            '#head_contactnumber',
            '#head_education',
            '#head_job',
            '#head_salary'
        ].forEach(function (selector) {
            const element = q(form, selector);

            if (element) {
                element.addEventListener('input', updateHeadSummary);
                element.addEventListener('change', updateHeadSummary);
            }
        });

        if (sectorCategoryList) {
            sectorCategoryList.addEventListener('change', function (event) {
                const target = event.target;

                if (target instanceof HTMLInputElement && target.type === 'checkbox') {
                    updateSectorSelection();
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
                resetFamilyForm(true);
            });
        }

        form.addEventListener('submit', function (event) {
            // Submit via AJAX so validation errors stay inside the family modal.
            event.preventDefault();

            if (isSubmitting) {
                return;
            }

            isSubmitting = true;
            setFormAlert('', '');

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.text().then(function (text) {
                        let data = {};

                        try {
                            data = JSON.parse(text || '{}');
                        } catch (error) {
                            data = {};
                        }

                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    const data = result.data || {};

                    updateCsrfToken(data.csrf);

                    if (!result.ok || data.status === 'error') {
                        setFormAlert('danger', data.message || 'Please review the required fields.');
                        return;
                    }

                    setFormAlert('success', data.message || 'Family data saved successfully.');

                    // Suggestion #1: clear the form after a successful add.
                    if (!isEditMode) {
                        form.reset();
                        resetFamilyForm(false);
                    }
                })
                .catch(function () {
                    setFormAlert('danger', 'Unable to save the family data. Please try again.');
                })
                .finally(function () {
                    isSubmitting = false;
                });
        });

        form.addEventListener('submit', function (event) {
            // Submit via AJAX so validation stays inside the Add Family modal.
            event.preventDefault();

            if (isSubmitting) {
                return;
            }

            isSubmitting = true;
            setFormAlert('', '');

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    }).catch(function () {
                        return { ok: false, data: { message: 'Unable to save the family data. Please try again.' } };
                    });
                })
                .then(function (result) {
                    const data = result.data || {};

                    updateCsrfToken(data.csrf);

                    if (!result.ok || data.status === 'error') {
                        setFormAlert('danger', data.message || 'Please review the required fields.');
                        return;
                    }

                    setFormAlert('success', data.message || 'Family data saved successfully.');

                    // Suggestion #1: auto-clear the form and return to Step 1 after save.
                    if (resetBtn) {
                        resetBtn.click();
                    }
                })
                .catch(function () {
                    setFormAlert('danger', 'Unable to save the family data. Please try again.');
                })
                .finally(function () {
                    isSubmitting = false;
                });
        });

        if (state.selectedSectorIds.length > 0) {
            populateSectorsByCategory();
        } else {
            resetSectorSelection();
        }

        if (Array.isArray(initialFamilyData.existingMembers)) {
            initialFamilyData.existingMembers.forEach(function (member) {
                createMemberRow(member);
            });
        }

        if (typeof ui.setMemberRowsEmptyState === 'function') {
            ui.setMemberRowsEmptyState(memberRows, memberRowsEmpty);
        }

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
