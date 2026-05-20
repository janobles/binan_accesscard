/*
 * Family form UI behavior.
 * This file controls wizard step navigation while PHP controllers handle saving.
 */
(function () {
    const form = document.getElementById('familyForm');

    if (! form) {
        return;
    }

    const panels = Array.from(form.querySelectorAll('.family-step-panel'));
    const stepItems = Array.from(document.querySelectorAll('.family-wizard-steps .wizard-step'));
    const nextBtn = document.getElementById('nextStepBtn');
    const prevBtn = document.getElementById('prevStepBtn');
    const submitBtn = document.getElementById('submitFamilyBtn');
    const resetBtn = document.getElementById('resetFamilyBtn');
    const addMemberBtn = document.getElementById('addMemberBtn');
    const memberRows = document.getElementById('memberRows');
    const memberTemplate = document.getElementById('memberTemplate');
    const stepInfo = document.querySelector('.wizard-header-left small');
    const entryTypeInput = document.getElementById('entryType');
    const entryButtons = Array.from(document.querySelectorAll('.entry-type-btn'));
    const entryPanels = Array.from(document.querySelectorAll('[data-entry-panel]'));
    let currentStep = 1;
    let entryType = entryTypeInput ? entryTypeInput.value : 'head';

    function totalSteps() {
        return entryType === 'member' ? 2 : 3;
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
    }

    function togglePanelFields(panel, enabled) {
        Array.from(panel.querySelectorAll('input, select, textarea')).forEach(function (field) {
            field.disabled = ! enabled;
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
            button.classList.toggle('btn-outline-secondary', ! isActive);
        });

        entryPanels.forEach(function (panel) {
            const isActive = panel.dataset.entryPanel === entryType;
            panel.style.display = isActive ? '' : 'none';
            togglePanelFields(panel, isActive);
        });

        if (memberRows) {
            togglePanelFields(memberRows, entryType === 'head');
        }

        setStep(Math.min(currentStep, totalSteps()));
    }

    function refreshMemberNames() {
        if (! memberRows) {
            return;
        }

        Array.from(memberRows.querySelectorAll('.member-row')).forEach(function (row, index) {
            Array.from(row.querySelectorAll('[data-name]')).forEach(function (field) {
                field.name = 'members[' + index + '][' + field.dataset.name + ']';
            });
        });
    }

    function addMemberRow() {
        if (! memberRows || ! memberTemplate) {
            return;
        }

        const fragment = memberTemplate.content.cloneNode(true);
        const row = fragment.querySelector('.member-row');

        if (! row) {
            return;
        }

        row.querySelector('.remove-member')?.addEventListener('click', function () {
            row.remove();
            refreshMemberNames();
        });

        memberRows.appendChild(row);
        refreshMemberNames();
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

    stepItems.forEach(function (item) {
        item.addEventListener('click', function () {
            setStep(Number(item.dataset.stepTarget));
        });
    });

    entryButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setEntryType(button.dataset.entryType);
        });
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            window.setTimeout(function () {
                if (memberRows) {
                    memberRows.innerHTML = '';
                }

                setEntryType('head');
                setStep(1);
            }, 0);
        });
    }

    if (addMemberBtn) {
        addMemberBtn.addEventListener('click', addMemberRow);
    }

    setEntryType(entryType);
    setStep(1);
})();
