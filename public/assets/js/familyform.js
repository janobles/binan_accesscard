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
    const stepInfo = document.querySelector('.wizard-header-left small');
    let currentStep = 1;
    const totalSteps = 3;

    function setStep(step) {
        currentStep = Math.max(1, Math.min(totalSteps, step));

        panels.forEach(function (panel) {
            panel.classList.toggle('is-visible', Number(panel.dataset.step) === currentStep);
        });

        stepItems.forEach(function (item) {
            item.classList.toggle('is-active', Number(item.dataset.stepTarget) === currentStep);
        });

        if (stepInfo) {
            if (currentStep === 1) {
                stepInfo.textContent = 'Step 1 of 3 - Head of the Family';
            } else if (currentStep === 2) {
                stepInfo.textContent = 'Step 2 of 3 - Sector & services';
            } else {
                stepInfo.textContent = 'Step 3 of 3 - Family members';
            }
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

    setStep(1);
})();
