// Wires the family wizard events while keeping rendering helpers reusable.
(function (window, document) {
    function parseJsonNode(node, fallbackValue) {
        if (!node) {
            return fallbackValue;
        }

        try {
            const raw = typeof node.value !== 'undefined' ? node.value : node.textContent;
            return JSON.parse(raw || 'null') || fallbackValue;
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
        const uiRoot = wizardCard || root;
        const panels = qa(form, '.family-step-panel');
        const stepItems = qa(uiRoot, '.family-wizard-steps .wizard-step');
        const nextBtn = q(form, '#nextStepBtn');
        const prevBtn = q(form, '#prevStepBtn');
        const submitBtn = q(form, '#submitFamilyBtn');
        const resetBtn = q(form, '#resetFamilyBtn');
        const addMemberBtn = q(form, '#addMemberBtn');
        const addMemberStickyBtn = q(form, '#addMemberStickyBtn');
        const memberRows = q(form, '#memberRows');
        const memberTemplate = q(root, '#memberTemplate');
        const memberRowsEmpty = q(form, '#memberRowsEmpty');
        const choiceModal = q(root, '#familyChoiceModal');
        const choiceModalTitle = q(root, '#familyChoiceModalLabel');
        const choiceModalBody = q(root, '#familyChoiceModalBody');
        const stepInfo = q(uiRoot, '.wizard-header-left small');
        const entryTypeInput = q(form, '#entryType');
        const entryButtons = qa(form, '[data-entry-type]');
        const entryPanels = qa(form, '[data-entry-panel]');
        const sectorCategoryList = q(form, '#sectorCategoryList');
        const sectorNameList = q(form, '#sectorNameList');
        const sectorCatalogNode = q(form, '#sectorCatalogData');
        const selectedSectorIdsNode = q(form, '#selectedSectorIdsData');
        const initialFamilyDataNode = q(root, '#initialFamilyData');
        const summaryTargets = {
            name: q(form, '#headSummaryName'),
            birthday: q(form, '#headSummaryBirthday'),
            sex: q(form, '#headSummarySex'),
            civil: q(form, '#headSummaryCivil'),
            contact: q(form, '#headSummaryContact'),
            religion: q(form, '#headSummaryReligion'),
            education: q(form, '#headSummaryEducation'),
            job: q(form, '#headSummaryJob'),
            income: q(form, '#headSummaryIncome'),
            address: q(form, '#headSummaryAddress'),
            sectors: q(form, '#headSummarySectors'),
            services: q(form, '#headSummaryServices')
        };
        let currentStep = 1;
        let memberIndex = 0;
        let entryType = entryTypeInput ? entryTypeInput.value : 'head';
        const initialFamilyData = parseJsonNode(initialFamilyDataNode, {});
        const sectorCatalog = parseJsonNode(sectorCatalogNode, {});
        const state = {
            selectedSectorIds: normalizeIds(parseJsonNode(selectedSectorIdsNode, initialFamilyData.selectedSectorIds || [])),
            activeChoiceSource: null,
            activeChoicePlaceholder: null,
        };
        const bootstrapChoiceModal = choiceModal && window.bootstrap && window.bootstrap.Modal
            ? new window.bootstrap.Modal(choiceModal)
            : null;

        function totalSteps() {
            return entryType === 'member' ? 2 : 3;
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
            if (typeof ui.resetSectorSelection === 'function') {
                ui.resetSectorSelection(sectorNameList, null);
            }
        }

        function updateSectorSelection() {
            if (typeof ui.updateSectorSelection === 'function') {
                ui.updateSectorSelection(sectorNameList, null);
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

            initMemberChoiceFields(memberRows);
        }

        function choiceLabel(input) {
            const dataLabel = String(input.dataset.label || '').trim();

            if (dataLabel !== '') {
                return dataLabel;
            }

            const label = input.closest('label');

            return label ? String(label.textContent || '').trim() : '';
        }

        function updateChoiceSummary(field) {
            if (!field) {
                return;
            }

            const summary = q(field, '[data-choice-summary]');
            const emptyText = String(field.dataset.choiceEmpty || 'No options selected');
            const labels = qa(field, 'input[type="checkbox"]:checked')
                .map(choiceLabel)
                .filter(function (label) {
                    return label !== '';
                });

            if (!summary) {
                return;
            }

            if (labels.length === 0) {
                summary.textContent = emptyText;
                summary.classList.add('text-muted');

                return;
            }

            summary.textContent = labels.length <= 2 ? labels.join(', ') : labels.length + ' selected';
            summary.title = labels.join(', ');
            summary.classList.remove('text-muted');
        }

        function returnChoiceSource() {
            if (!state.activeChoiceSource || !state.activeChoicePlaceholder) {
                return;
            }

            state.activeChoicePlaceholder.replaceWith(state.activeChoiceSource);
            state.activeChoiceSource.classList.add('family-form-hidden');
            updateChoiceSummary(state.activeChoiceSource.closest('[data-choice-field]'));
            state.activeChoiceSource = null;
            state.activeChoicePlaceholder = null;
        }

        function openChoiceModal(field) {
            const source = q(field, '[data-choice-source]');

            if (!source || !choiceModalBody) {
                return;
            }

            returnChoiceSource();

            const placeholder = document.createComment('family choice source');

            source.replaceWith(placeholder);
            source.classList.remove('family-form-hidden');
            choiceModalBody.appendChild(source);
            state.activeChoiceSource = source;
            state.activeChoicePlaceholder = placeholder;

            if (choiceModalTitle) {
                choiceModalTitle.textContent = String(field.dataset.choiceTitle || 'Select options');
            }

            if (bootstrapChoiceModal) {
                bootstrapChoiceModal.show();
            }
        }

        function initMemberChoiceFields(scope) {
            const choiceScope = scope instanceof Element ? scope : form;

            qa(choiceScope, '[data-choice-field]').forEach(function (field) {
                if (field.dataset.choiceInitialized !== '1') {
                    field.dataset.choiceInitialized = '1';
                    const openBtn = q(field, '[data-choice-open]');

                    if (openBtn) {
                        openBtn.addEventListener('click', function () {
                            openChoiceModal(field);
                        });
                    }

                    field.addEventListener('change', function (event) {
                        const target = event.target;

                        if (target instanceof HTMLInputElement && target.type === 'checkbox') {
                            updateChoiceSummary(field);
                        }
                    });
                }

                updateChoiceSummary(field);
            });
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

        if (addMemberStickyBtn) {
            addMemberStickyBtn.addEventListener('click', function () {
                createMemberRow();
            });
        }

        if (choiceModal) {
            choiceModal.addEventListener('hidden.bs.modal', returnChoiceSource);
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
            '#head_religion',
            '#head_education',
            '#head_job',
            '#head_salary',
            '#head_address'
        ].forEach(function (selector) {
            const element = q(form, selector);

            if (element) {
                element.addEventListener('input', updateHeadSummary);
                element.addEventListener('change', updateHeadSummary);
            }
        });

        if (sectorNameList) {
            sectorNameList.addEventListener('change', function (event) {
                const target = event.target;

                if (target instanceof HTMLInputElement && target.type === 'checkbox') {
                    updateSectorSelection();
                    updateHeadSummary();
                }
            });
        }

        if (sectorCategoryList) {
            sectorCategoryList.addEventListener('change', function (event) {
                const target = event.target;

                if (target instanceof HTMLInputElement && target.type === 'checkbox') {
                    populateSectorsByCategory();
                    updateHeadSummary();
                }
            });
        }

        form.addEventListener('change', function (event) {
            const target = event.target;

            if (target instanceof HTMLSelectElement && target.classList.contains('js-other-select')) {
                if (typeof ui.syncOtherControl === 'function') {
                    ui.syncOtherControl(target);
                }

                updateHeadSummary();
            }

            if (target instanceof HTMLInputElement && target.name === 'service_ids[]') {
                updateHeadSummary();
            }
        });

        form.addEventListener('input', function (event) {
            const target = event.target;

            if (target instanceof HTMLInputElement && target.classList.contains('js-other-input')) {
                updateHeadSummary();
            }
        });

        form.addEventListener('submit', function () {
            if (typeof ui.applyOtherValues === 'function') {
                ui.applyOtherValues(form);
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
                    populateSectorsByCategory();

                    if (typeof ui.setMemberRowsEmptyState === 'function') {
                        ui.setMemberRowsEmptyState(memberRows, memberRowsEmpty);
                    }

                    initMemberChoiceFields(memberRows);
                    updateHeadSummary();
                    setStep(1);
                }, 0);
            });
        }

        if (Object.keys(sectorCatalog).length > 0) {
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

        if (typeof ui.syncOtherControls === 'function') {
            ui.syncOtherControls(form);
        }

        if (typeof ui.initDropdownChecklists === 'function') {
            ui.initDropdownChecklists(form);
        }

        initMemberChoiceFields(memberRows);

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
