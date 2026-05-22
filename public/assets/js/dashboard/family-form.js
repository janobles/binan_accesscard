// Wires the family wizard events while delegating rendering helpers to FamilyFormUI.
(function (window, document) {
    function initFamilyForm(rootElement) {
        const utils = window.familyFormUtils || {};
        const q = utils.q;
        const qa = utils.qa;

        if (typeof q !== 'function' || typeof qa !== 'function') {
            return;
        }

        const root = rootElement instanceof HTMLElement ? rootElement : document;
        const form = q(root, '#familyForm');

        if (!ui || !form || form.dataset.familyFormInitialized === '1') {
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
        const memberRows = q(form, '#memberRows');
        const memberTemplate = q(root, '#memberTemplate');
        const memberRowsEmpty = q(form, '#memberRowsEmpty');
        const stepInfo = q(uiRoot, '.wizard-header-left small');
        const sectorCategoryList = q(form, '#sectorCategoryList');
        const sectorNameList = q(form, '#sectorNameList');
        const sectorIdInput = q(form, '#sectorID');
        const sectorCatalogNode = q(form, '#sectorCatalogData');
        const headSummaryName = q(form, '#headSummaryName');
        const headSummaryBirthday = q(form, '#headSummaryBirthday');
        const headSummarySex = q(form, '#headSummarySex');
        const headSummaryCivil = q(form, '#headSummaryCivil');
        const headSummaryContact = q(form, '#headSummaryContact');
        const headSummaryEducation = q(form, '#headSummaryEducation');
        const headSummaryJob = q(form, '#headSummaryJob');
        const headSummaryIncome = q(form, '#headSummaryIncome');
        const headSummarySectors = q(form, '#headSummarySectors');
        const headSummaryServices = q(form, '#headSummaryServices');
        let currentStep = 1;
        let entryType = entryTypeInput ? entryTypeInput.value : 'head';
        const state = {
            selectedSectorIds: [],
        };

        function totalSteps() {
            return entryType === 'member' ? 2 : 3;
        }

        function updateHeadSummary() {
            ui.renderHeadSummary(form, summaryTargets);
        }

        function setStep(step) {
            currentStep = Math.max(1, Math.min(totalSteps(), step));

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
        }

        function setEntryType(nextEntryType) {
            entryType = nextEntryType === 'member' ? 'member' : 'head';

            ui.setEntryType({
                addMemberBtn: addMemberBtn,
                entryButtons: entryButtons,
                entryPanels: entryPanels,
                entryType: entryType,
                entryTypeInput: entryTypeInput,
                memberRows: memberRows
            });

            setStep(Math.min(currentStep, totalSteps()));
        }

        function resetSectorSelection() {
            selectedSectorIds = [];
            ui.resetSectorSelection(sectorNameList, sectorIdInput);
        }

        function updateSectorSelection() {
            ui.updateSectorSelection(sectorNameList, sectorIdInput);
        }

        function populateSectorsByCategory() {
            ui.populateSectorsByCategory({
                sectorCatalog: sectorCatalog,
                sectorCategoryList: sectorCategoryList,
                sectorNameList: sectorNameList,
                sectorIdInput: sectorIdInput,
                selectedSectorIds: selectedSectorIds
            });
        }

        function createMemberRow(memberData) {
            if ((!memberData || typeof memberData !== 'object') && entryType !== 'head') {
                return;
            }

            memberIndex = ui.createMemberRow({
                memberTemplate: memberTemplate,
                memberRows: memberRows,
                memberIndex: memberIndex,
                memberData: memberData
            });
            ui.setMemberRowsEmptyState(memberRows, memberRowsEmpty);
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
                        ui.setMemberRowsEmptyState(memberRows, memberRowsEmpty);
                    }
                }
            });
        }

        ['#head_firstname', '#head_middlename', '#head_lastname', '#head_suffix', '#head_birthday', '#head_sex', '#head_civilstatus', '#head_contactnumber', '#head_education', '#head_job', '#head_salary'].forEach(function (selector) {
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
                    selectedSectorIds = ui.collectSelectedSectorIds(form);
                    populateSectorsByCategory();
                    updateHeadSummary();
                }
            });
        }

        if (sectorNameList) {
            sectorNameList.addEventListener('change', function (event) {
                const target = event.target;

                if (target instanceof HTMLInputElement && target.type === 'checkbox') {
                    selectedSectorIds = ui.collectSelectedSectorIds(form);
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
                    ui.setMemberRowsEmptyState(memberRows, memberRowsEmpty);
                    updateHeadSummary();
                    setStep(1);
                }, 0);
            });
        }

        if (typeof window.initManageFamilyForm === 'function') {
            window.initManageFamilyForm({
                form: form,
                createMemberRow: memberHandlers.createMemberRow,
                setSelectedSectorIds: function (ids) {
                    state.selectedSectorIds = Array.isArray(ids)
                        ? ids.map(function (id) {
                            return String(id || '').trim();
                        }).filter(function (id) {
                            return id !== '';
                        })
                        : [];
                },
                populateSectorsByCategory: populateSectorsByCategory,
                resetSectorSelection: resetSectorSelection
            });
        } else {
            sectorHandlers.resetSectorSelection();
        }

        ui.setMemberRowsEmptyState(memberRows, memberRowsEmpty);
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
