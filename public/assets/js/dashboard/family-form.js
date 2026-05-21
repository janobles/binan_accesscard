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
        const totalSteps = 3;
        let sectorCatalog = {};
        const state = {
            selectedSectorIds: [],
        };

        if (sectorCatalogNode) {
            try {
                sectorCatalog = JSON.parse(sectorCatalogNode.textContent || '{}');
            } catch (error) {
                sectorCatalog = {};
            }
        }

        const sectorHandlers = typeof window.createFamilyFormSectorHandlers === 'function'
            ? window.createFamilyFormSectorHandlers({
                q: q,
                qa: qa,
                sectorCategoryList: sectorCategoryList,
                sectorNameList: sectorNameList,
                sectorIdInput: sectorIdInput,
                sectorCatalog: sectorCatalog,
                state: state,
            })
            : {
                resetSectorSelection: function () {},
                updateSectorSelection: function () {},
                populateSectorsByCategory: function () {},
            };

        const memberHandlers = typeof window.createFamilyFormMemberHandlers === 'function'
            ? window.createFamilyFormMemberHandlers({
                memberTemplate: memberTemplate,
                memberRows: memberRows,
                memberRowsEmpty: memberRowsEmpty,
            })
            : {
                createMemberRow: function () {},
                setMemberRowsEmptyState: function () {},
                bindRemoveMember: function () {},
            };

        const updateHeadSummary = typeof window.createFamilyFormSummaryUpdater === 'function'
            ? window.createFamilyFormSummaryUpdater({
                form: form,
                q: q,
                qa: qa,
                textOrDash: utils.textOrDash,
                renderSummaryList: utils.renderSummaryList,
                headSummaryName: headSummaryName,
                headSummaryBirthday: headSummaryBirthday,
                headSummarySex: headSummarySex,
                headSummaryCivil: headSummaryCivil,
                headSummaryContact: headSummaryContact,
                headSummaryEducation: headSummaryEducation,
                headSummaryJob: headSummaryJob,
                headSummaryIncome: headSummaryIncome,
                headSummarySectors: headSummarySectors,
                headSummaryServices: headSummaryServices,
            })
            : function () {};

        function setStep(step) {
            currentStep = Math.max(1, Math.min(totalSteps, step));

            panels.forEach(function (panel) {
                panel.classList.toggle('is-visible', Number(panel.dataset.step) === currentStep);
            });

            stepItems.forEach(function (item) {
                item.classList.toggle('is-active', Number(item.dataset.stepTarget) === currentStep);
            });

            if (stepInfo) {
                stepInfo.textContent = [
                    'Step 1 of 3 - Head of the Family',
                    'Step 2 of 3 - Sector & services',
                    'Step 3 of 3 - Family members'
                ][currentStep - 1];
            }

            if (prevBtn) {
                prevBtn.style.display = currentStep === 1 ? 'none' : '';
            }

            if (nextBtn) {
                nextBtn.style.display = currentStep === totalSteps ? 'none' : '';
            }

            if (submitBtn) {
                submitBtn.style.display = currentStep === totalSteps ? '' : 'none';
            }

            if (resetBtn) {
                resetBtn.style.display = currentStep === totalSteps ? 'none' : '';
            }

            if (currentStep === 3) {
                updateHeadSummary();
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
                memberHandlers.createMemberRow();
            });
        }

        memberHandlers.bindRemoveMember();

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
                    state.selectedSectorIds = utils.collectSelectedSectorIds(form);
                    sectorHandlers.populateSectorsByCategory();
                    updateHeadSummary();
                }
            });
        }

        if (sectorNameList) {
            sectorNameList.addEventListener('change', function (event) {
                const target = event.target;

                if (target instanceof HTMLInputElement && target.type === 'checkbox') {
                    state.selectedSectorIds = utils.collectSelectedSectorIds(form);
                    sectorHandlers.updateSectorSelection();
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
                populateSectorsByCategory: sectorHandlers.populateSectorsByCategory,
                resetSectorSelection: sectorHandlers.resetSectorSelection,
            });
        } else {
            sectorHandlers.resetSectorSelection();
        }

        memberHandlers.setMemberRowsEmptyState();
        updateHeadSummary();

        stepItems.forEach(function (item) {
            item.addEventListener('click', function () {
                setStep(Number(item.dataset.stepTarget));
            });
        });

        setStep(1);
    }

    window.initFamilyForm = initFamilyForm;

    document.addEventListener('DOMContentLoaded', function () {
        initFamilyForm(document);
    });
})(window, document);
