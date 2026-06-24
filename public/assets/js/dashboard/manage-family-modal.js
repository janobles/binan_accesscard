// Registers family record modals with the shared dashboard loader.
(function (window, document) {
    'use strict';

    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    function textValue(form, selector) {
        var field = form.querySelector(selector);

        if (!field) {
            return '';
        }

        if (field.tagName === 'SELECT') {
            return field.options[field.selectedIndex] ? field.options[field.selectedIndex].text.trim() : '';
        }

        return String(field.value || '').trim();
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value || '');

        return div.innerHTML;
    }

    function checkedLabels(form, selector) {
        return Array.from(form.querySelectorAll(selector + ':checked')).map(function (input) {
            var label = input.closest('label');

            return input.dataset.label || (label ? label.textContent.trim() : '') || input.value;
        }).filter(Boolean);
    }

    function setSummary(container, key, value) {
        var target = container.querySelector('[data-head-summary="' + key + '"]');

        if (target) {
            target.textContent = value || '-';
        }
    }

    function setSummaryList(container, key, labels) {
        var target = container.querySelector('[data-head-summary="' + key + '"]');

        if (!target) {
            return;
        }

        if (!labels.length) {
            target.textContent = '-';
            return;
        }

        target.innerHTML = '<ul class="mb-0">' + labels.map(function (label) {
            return '<li>' + escapeHtml(label) + '</li>';
        }).join('') + '</ul>';
    }

    function renderHeadSummary(container) {
        var form = container.querySelector('form');

        if (!form) {
            return;
        }

        var fullName = [
            textValue(form, '[data-summary="name-first"]'),
            textValue(form, '[data-summary="name-middle"]'),
            textValue(form, '[data-summary="name-last"]'),
            textValue(form, '[data-summary="name-suffix"]')
        ].filter(Boolean).join(' ');

        setSummary(container, 'name', fullName);
        setSummary(container, 'birthday', textValue(form, '[data-summary="birthday"]'));
        setSummary(container, 'sex', textValue(form, '[data-summary="sex"]'));
        setSummary(container, 'civil', textValue(form, '[data-summary="civil"]'));
        setSummary(container, 'contact', textValue(form, '[data-summary="contact"]'));
        setSummary(container, 'religion', textValue(form, '[data-summary="religion"]'));
        setSummary(container, 'education', textValue(form, '[data-summary="education"]'));
        setSummary(container, 'job', textValue(form, '[data-summary="job"]'));
        setSummary(container, 'income', textValue(form, '[data-summary="income"]'));
        setSummary(container, 'address', [textValue(form, '[data-summary="address"]'), textValue(form, '[data-summary="barangay"]')].filter(Boolean).join(', '));
        setSummaryList(container, 'sectors', checkedLabels(form, 'input[name="sector_ids[]"]'));
        setSummaryList(container, 'services', checkedLabels(form, 'input[name="service_ids[]"]'));
    }

    function validateHeadStep(container) {
        var headTrigger = container.querySelector('[data-family-step-target="head"]');
        var headTarget = headTrigger ? headTrigger.getAttribute('data-family-step-pane') : '';
        var headPane = headTarget ? container.querySelector(headTarget) : null;
        var requiredFields = headPane ? Array.from(headPane.querySelectorAll('[required]')) : [];
        var firstInvalid = requiredFields.find(function (field) {
            return String(field.value || '').trim() === '';
        });

        requiredFields.forEach(function (field) {
            var isInvalid = String(field.value || '').trim() === '';
            var wrapper = field.closest('[class*="col-"]') || field.parentElement;
            var feedback = wrapper ? wrapper.querySelector('[data-family-field-error]') : null;

            field.classList.toggle('is-invalid', isInvalid);

            if (!feedback && wrapper) {
                feedback = document.createElement('div');
                feedback.className = 'family-field-error';
                feedback.setAttribute('data-family-field-error', '');
                feedback.textContent = 'This field is required.';
                wrapper.appendChild(feedback);
            }

            if (feedback) {
                feedback.hidden = !isInvalid;
            }
        });

        if (!firstInvalid) {
            return true;
        }

        firstInvalid.focus();

        return false;
    }

    function clearFieldError(field) {
        var wrapper = field.closest('[class*="col-"]') || field.parentElement;
        var feedback = wrapper ? wrapper.querySelector('[data-family-field-error]') : null;

        if (String(field.value || '').trim() !== '') {
            field.classList.remove('is-invalid');

            if (feedback) {
                feedback.hidden = true;
            }
        }
    }

    function showStep(container, step) {
        if (step === 'members' && !validateHeadStep(container)) {
            return;
        }

        var trigger = container.querySelector('[data-family-step-target="' + step + '"]');
        var stepTriggers = container.querySelectorAll('[data-family-step-target]');
        var panes = container.querySelectorAll('.tab-pane');
        var targetSelector = trigger ? trigger.getAttribute('data-family-step-pane') : '';

        stepTriggers.forEach(function (button) {
            var isActive = button === trigger;

            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panes.forEach(function (pane) {
            var isActive = targetSelector && pane.matches(targetSelector);

            pane.classList.toggle('active', isActive);
            pane.classList.toggle('show', isActive);
        });

        var isMembers = step === 'members';
        var prev = container.querySelector('[data-family-prev]');
        var next = container.querySelector('[data-family-next]');
        var save = container.querySelector('[data-family-save]');

        if (prev) prev.hidden = !isMembers;
        if (next) next.hidden = isMembers;
        if (save) save.hidden = !isMembers;

        if (isMembers) {
            renderHeadSummary(container);
        }
    }

    function initFamilyEntryModal(container) {
        var root = container.querySelector('[data-family-entry-form]');

        if (!root || root.dataset.familyEntryReady === '1') {
            return;
        }

        var modal = root.closest('#familyModal');

        if (modal) {
            modal.classList.add('is-family-entry-modal');
        }

        var title = modal ? modal.querySelector('#familyModalLabel') : null;
        var entryTitle = root.querySelector('.family-entry-title');

        if (title && entryTitle) {
            title.textContent = entryTitle.textContent.trim() || 'New Family Record';
        }

        root.dataset.familyEntryReady = '1';

        root.querySelectorAll('[data-family-step-target]').forEach(function (stepTrigger) {
            stepTrigger.addEventListener('click', function (event) {
                event.preventDefault();
                showStep(root, stepTrigger.dataset.familyStepTarget === 'members' ? 'members' : 'head');
            });
        });

        var nextButton = root.querySelector('[data-family-next]');

        if (nextButton) {
            nextButton.addEventListener('click', function (event) {
                event.preventDefault();
                showStep(root, 'members');
            });
        }

        var previousButton = root.querySelector('[data-family-prev]');

        if (previousButton) {
            previousButton.addEventListener('click', function (event) {
                event.preventDefault();
                showStep(root, 'head');
            });
        }

        root.addEventListener('change', function () {
            renderHeadSummary(root);
        });

        root.addEventListener('input', function (event) {
            if (event.target && event.target.matches('[required]')) {
                clearFieldError(event.target);
            }

            renderHeadSummary(root);
        });

        root.addEventListener('change', function (event) {
            if (event.target && event.target.matches('[required]')) {
                clearFieldError(event.target);
            }
        });

        renderHeadSummary(root);
        showStep(root, 'head');
    }

    document.addEventListener('click', function (event) {
        var nextButton = event.target.closest('[data-family-next]');
        var previousButton = event.target.closest('[data-family-prev]');
        var stepTrigger = event.target.closest('[data-family-step-target]');
        var control = nextButton || previousButton || stepTrigger;

        if (!control) {
            return;
        }

        var root = control.closest('[data-family-entry-form]');

        if (!root) {
            return;
        }

        event.preventDefault();

        if (nextButton) {
            showStep(root, 'members');
            return;
        }

        if (previousButton) {
            showStep(root, 'head');
            return;
        }

        showStep(root, stepTrigger.dataset.familyStepTarget === 'members' ? 'members' : 'head');
    });

    window.registerDashboardModal({
        namespace: 'family',
        triggerSelector: '.js-open-family-view-modal',
        defaultTitle: 'View Record',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading record...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the record. Please try again.</div>'
    });

    window.registerDashboardModal({
        namespace: 'familyAdd',
        triggerSelector: '.js-open-family-add-modal',
        defaultTitle: 'New Family Record',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading form...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the form. Please try again.</div>',
        onLoaded: initFamilyEntryModal
    });
})(window, document);
