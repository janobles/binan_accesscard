// Wires the 2-step family record wizard (Head of Family + Sectors & Services → Members).
// Handles step navigation, step-1 client-side validation, member row add/remove,
// sector/service selection, "other" select syncing, choice modals, and the head
// summary panel shown on step 2. Rendering helpers live in family-form-ui.js.
// On submit, applyOtherValues() swaps generated <option> values so the custom
// text is posted instead of the literal "Other" / "Others" option value.
//
// Step-1 validation (validateStep1):
//   - Required: First name, Last name, Date of birth, Sex
//   - Optional but digits-only: Contact number (letters stripped live on input)
//   - Inline is-invalid / invalid-feedback without page refresh
//
// Connected to:
//   - family-form-ui.js    : window.FamilyFormUI (all rendering helpers)
//   - manage-family-modal.js: calls window.initFamilyForm() after AJAX load
//   - Backend : POST families            (FamilyController::store)
//               POST {base}/update/:id   (FamilyController::update)
//   - Views   : Family/form.php (form shell)
//               Family/head-fields.php (step 1 fields)
//               Lookups/picker.php (inside step 1)
//               Family/member-summary.php, Family/member-fields.php (step 2)
// Wires the family wizard events while keeping rendering helpers reusable.
(function (window, document) {
    function parseJsonNode(node, fallbackValue) {
        if (!node) {
            return fallbackValue;
        }

        try {
            const raw = node.dataset && typeof node.dataset.json !== 'undefined'
                ? node.dataset.json
                : (typeof node.value !== 'undefined' ? node.value : node.textContent);
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

    // ---- Draft auto-save (new records only) ----------------------------------
    // The in-progress "Add Record" wizard is mirrored to localStorage so an
    // accidental refresh, a closed modal, or a brief network drop never loses
    // typed input. The draft is cleared only after a confirmed successful save
    // (signalled by #familyDraftSavedMarker on the page we land on), so a submit
    // that fails mid-flight still leaves the data recoverable.
    const FAMILY_DRAFT_KEY = 'binan_family_draft_v1';

    function readDraft() {
        try {
            const raw = window.localStorage.getItem(FAMILY_DRAFT_KEY);

            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    }

    function clearDraft() {
        try {
            window.localStorage.removeItem(FAMILY_DRAFT_KEY);
        } catch (error) {
            // localStorage unavailable (private mode / disabled) — nothing to clear.
        }
    }

    function draftIsEmpty(snapshot) {
        if (!snapshot || typeof snapshot !== 'object') {
            return true;
        }

        const head = snapshot.head || {};
        const hasHead = Object.keys(head).some(function (id) {
            return String(head[id] || '').trim() !== '';
        });

        return !hasHead
            && (snapshot.members || []).length === 0
            && (snapshot.sector_ids || []).length === 0
            && (snapshot.service_ids || []).length === 0;
    }

    function formatDraftAge(savedAt) {
        const ts = Number(savedAt) || 0;

        if (ts <= 0) {
            return 'a moment ago';
        }

        const mins = Math.floor((Date.now() - ts) / 60000);

        if (mins < 1) { return 'just now'; }
        if (mins < 60) { return mins + (mins === 1 ? ' minute ago' : ' minutes ago'); }

        const hrs = Math.floor(mins / 60);

        if (hrs < 24) { return hrs + (hrs === 1 ? ' hour ago' : ' hours ago'); }

        const days = Math.floor(hrs / 24);

        return days + (days === 1 ? ' day ago' : ' days ago');
    }

    function askFamilyFormDialog(form, options) {
        return new Promise(function (resolve) {
            const host = form.closest('#familyModalBody') || form;
            const overlay = document.createElement('div');
            const dialog = document.createElement('div');
            const icon = document.createElement('div');
            const iconGlyph = document.createElement('i');
            const copy = document.createElement('div');
            const title = document.createElement('h3');
            const message = document.createElement('p');
            const actions = document.createElement('div');
            const cancelButton = document.createElement('button');
            const confirmButton = document.createElement('button');

            overlay.className = 'family-draft-dialog-backdrop';
            overlay.setAttribute('role', 'presentation');

            dialog.className = 'family-draft-dialog';
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', 'familyFormDialogTitle');
            dialog.setAttribute('aria-describedby', 'familyFormDialogText');

            icon.className = 'family-draft-dialog-icon' + (options.tone === 'warning' ? ' is-warning' : '');
            icon.setAttribute('aria-hidden', 'true');
            iconGlyph.className = options.iconClass || 'bi bi-question-lg';
            icon.appendChild(iconGlyph);

            copy.className = 'family-draft-dialog-copy';
            title.id = 'familyFormDialogTitle';
            title.textContent = options.title || 'Confirm action';
            message.id = 'familyFormDialogText';
            message.textContent = options.message || '';
            copy.appendChild(title);
            copy.appendChild(message);

            actions.className = 'family-draft-dialog-actions';
            cancelButton.type = 'button';
            cancelButton.className = 'btn btn-outline-secondary';
            cancelButton.dataset.dialogAction = 'cancel';
            cancelButton.textContent = options.cancelLabel || 'Cancel';
            confirmButton.type = 'button';
            confirmButton.className = options.confirmClass || 'btn btn-success';
            confirmButton.dataset.dialogAction = 'confirm';
            confirmButton.textContent = options.confirmLabel || 'Confirm';
            actions.appendChild(cancelButton);
            actions.appendChild(confirmButton);

            dialog.appendChild(icon);
            dialog.appendChild(copy);
            dialog.appendChild(actions);
            overlay.appendChild(dialog);

            const finish = function (shouldRestore) {
                overlay.remove();
                resolve(shouldRestore);
            };

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    finish(false);
                    return;
                }

                const button = event.target.closest('[data-dialog-action]');
                if (!button) {
                    return;
                }

                finish(button.dataset.dialogAction === 'confirm');
            });

            overlay.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    finish(false);
                }
            });

            host.appendChild(overlay);

            if (confirmButton) {
                confirmButton.focus();
            }
        });
    }

    function askRestoreDraft(form, savedAt) {
        return askFamilyFormDialog(form, {
            title: 'Restore unsaved record?',
            message: 'You have an unsaved record from ' + formatDraftAge(savedAt) + '. Restore it?',
            iconClass: 'bi bi-arrow-counterclockwise',
            cancelLabel: 'Discard',
            confirmLabel: 'Restore',
            confirmClass: 'btn btn-success'
        });
    }

    function askRemoveArchivedItem(form, label) {
        return askFamilyFormDialog(form, {
            title: 'Remove archived item?',
            message: '"' + label + '" is archived. If you remove it, this person loses the benefit and it can\'t be added back later.',
            iconClass: 'bi bi-exclamation-triangle',
            tone: 'warning',
            cancelLabel: 'Keep',
            confirmLabel: 'Remove',
            confirmClass: 'btn btn-danger'
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
        const formAlert = q(form, '#familyFormAlert');
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
        let firstInvalidMemberField = null;
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
            return 2;
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

        function setFieldError(field, message) {
            if (!field) { return; }
            field.classList.toggle('is-invalid', message !== '');
            const feedback = field.parentElement ? field.parentElement.querySelector('.invalid-feedback') : null;
            // Only overwrite when showing an error so the template's default message survives.
            if (feedback && message !== '') { feedback.textContent = message; }
        }

        function showFormAlert(message) {
            if (!formAlert) { return; }

            const alert = document.createElement('div');
            alert.className = 'alert alert-danger mb-0';
            alert.setAttribute('role', 'alert');
            alert.textContent = message;
            formAlert.innerHTML = '';
            formAlert.appendChild(alert);
        }

        function clearFormAlert() {
            if (formAlert) { formAlert.innerHTML = ''; }
        }

        // `silent: true` returns the boolean without painting errors — used to compute
        // step-lock state on load without flashing red on an untouched new form.
        function validateStep1(options) {
            const silent = !!(options && options.silent);
            let valid = true;
            const rules = [
                { id: '#head_firstname',   msg: 'First name is required.' },
                { id: '#head_lastname',    msg: 'Last name is required.' },
                { id: '#head_birthday',    msg: 'Date of birth is required.' },
                { id: '#head_sex',         msg: 'Sex is required.' },
                { id: '#head_civilstatus', msg: 'Civil status is required.' },
                { id: '#head_education',   msg: 'Education is required.' },
                { id: '#head_job',         msg: 'Job is required.' },
                { id: '#head_salary',      msg: 'Monthly income is required.' },
                { id: '#head_address',     msg: 'Address is required.' },
                { id: '#head_barangay',    msg: 'Barangay is required.' }
            ];

            rules.forEach(function (rule) {
                const field = q(form, rule.id);
                if (!field) { return; }
                const empty = field.value.trim() === '';
                if (!silent) { setFieldError(field, empty ? rule.msg : ''); }
                if (empty) { valid = false; }
            });

            const contact = q(form, '#head_contactnumber');
            if (contact && contact.value.trim() !== '' && /[^0-9]/.test(contact.value)) {
                if (!silent) { setFieldError(contact, 'Contact number must contain digits only.'); }
                valid = false;
            } else if (contact && !silent) {
                setFieldError(contact, '');
            }

            return valid;
        }

        // Step 1 must be valid before Members (2) can be entered.
        function canEnterStep(target) {
            if (Number(target) <= 1) { return true; }

            return validateStep1({ silent: true });
        }

        function updateStepLocks() {
            const unlocked = validateStep1({ silent: true });

            stepItems.forEach(function (item) {
                const target = Number(item.dataset.stepTarget);
                const locked = target > 1 && !unlocked;

                item.classList.toggle('is-locked', locked);
                item.setAttribute('aria-disabled', locked ? 'true' : 'false');
            });
        }

        // Members validate exactly like the head: first/last name, birthday and sex are
        // required on every row; contact number is optional but digits-only when present.
        function validateMembers() {
            let valid = true;
            let firstInvalid = null;
            const rows = memberRows ? qa(memberRows, '.member-row') : [];

            rows.forEach(function (row) {
                const checks = [
                    { sel: '[name$="[firstname]"]',   msg: 'First name is required.' },
                    { sel: '[name$="[lastname]"]',    msg: 'Last name is required.' },
                    { sel: '[name$="[birthday]"]',    msg: 'Date of birth is required.' },
                    { sel: '[name$="[sex]"]',         msg: 'Sex is required.' },
                    { sel: '[name$="[civilstatus]"]', msg: 'Civil status is required.' },
                    { sel: '[name$="[education]"]',   msg: 'Education is required.' },
                    { sel: '[name$="[job]"]',         msg: 'Job is required.' },
                    { sel: '[name$="[salary]"]',      msg: 'Monthly income is required.' }
                ];

                checks.forEach(function (check) {
                    const field = q(row, check.sel);
                    if (!field) { return; }
                    const empty = String(field.value || '').trim() === '';
                    setFieldError(field, empty ? check.msg : '');
                    if (empty) {
                        valid = false;
                        if (!firstInvalid) { firstInvalid = field; }
                    }
                });

                const contact = q(row, '[name$="[contactnumber]"]');
                if (contact && String(contact.value || '').trim() !== '' && /[^0-9]/.test(contact.value)) {
                    setFieldError(contact, 'Contact number must contain digits only.');
                    valid = false;
                    if (!firstInvalid) { firstInvalid = contact; }
                } else if (contact) {
                    setFieldError(contact, '');
                }
            });

            firstInvalidMemberField = firstInvalid;

            return valid;
        }

        function focusFirstInvalidMember() {
            if (firstInvalidMemberField && typeof firstInvalidMemberField.focus === 'function') {
                firstInvalidMemberField.focus();
            }
        }

        const contactInput = q(form, '#head_contactnumber');
        if (contactInput) {
            contactInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
                setFieldError(this, '');
            });
        }

        [
            '#head_firstname', '#head_lastname', '#head_birthday', '#head_sex',
            '#head_civilstatus', '#head_education', '#head_job', '#head_salary',
            '#head_address', '#head_barangay'
        ].forEach(function (selector) {
            const field = q(form, selector);
            if (field) {
                field.addEventListener('input', function () { if (this.value.trim() !== '') { setFieldError(this, ''); } });
                field.addEventListener('change', function () { if (this.value.trim() !== '') { setFieldError(this, ''); } });
            }
        });

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (currentStep === 1 && !validateStep1()) {
                    showFormAlert('Complete the Head of Family fields before continuing.');
                    return;
                }
                clearFormAlert();
                setStep(currentStep + 1);
                updateStepLocks();
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

            // Member fields are dynamic, so digit-strip and error-clearing are delegated.
            memberRows.addEventListener('input', function (event) {
                const target = event.target;

                if (!(target instanceof HTMLInputElement)) { return; }

                const name = target.getAttribute('name') || '';

                if (/\[contactnumber\]$/.test(name)) {
                    target.value = target.value.replace(/[^0-9]/g, '');
                    setFieldError(target, '');

                    return;
                }

                if (/\[(firstname|lastname|birthday)\]$/.test(name) && String(target.value || '').trim() !== '') {
                    setFieldError(target, '');
                }
            });

            memberRows.addEventListener('change', function (event) {
                const target = event.target;

                if (!(target instanceof HTMLElement)) { return; }

                const name = target.getAttribute('name') || '';

                if (/\[(firstname|lastname|birthday|sex|civilstatus|education|job|salary)\]$/.test(name) && String(target.value || '').trim() !== '') {
                    setFieldError(target, '');
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
            '#head_address',
            '#head_barangay'
        ].forEach(function (selector) {
            const element = q(form, selector);

            if (element) {
                element.addEventListener('input', function () { updateHeadSummary(); updateStepLocks(); });
                element.addEventListener('change', function () { updateHeadSummary(); updateStepLocks(); });
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

            // Archived sector/service guard: an archived item only appears here when it
            // was already assigned to this family. Unticking it permanently removes a
            // grandfathered benefit — archived items can't be re-selected — so confirm
            // first and re-tick on cancel. Covers head (name="sector_ids[]") and member
            // (name="members[i][sector_ids][]") checkboxes, which both bubble here.
            if (
                target instanceof HTMLInputElement
                && target.type === 'checkbox'
                && target.dataset.archived === '1'
                && !target.checked
            ) {
                const archivedLabel = String(target.dataset.label || '').trim() || 'This item';

                target.checked = true;
                askRemoveArchivedItem(form, archivedLabel).then(function (shouldRemove) {
                    if (shouldRemove) {
                        target.checked = false;
                    }

                    updateSectorSelection();
                    updateHeadSummary();
                });

                return;
            }

            if (target instanceof HTMLSelectElement && target.classList.contains('js-other-select')) {
                if (typeof ui.syncOtherControl === 'function') {
                    ui.syncOtherControl(target);
                }

                updateHeadSummary();
            }

            if (target instanceof HTMLInputElement && (target.name === 'service_ids[]' || target.name === 'sector_ids[]')) {
                updateHeadSummary();
            }
        });

        form.addEventListener('input', function (event) {
            const target = event.target;

            if (target instanceof HTMLInputElement && target.classList.contains('js-other-input')) {
                updateHeadSummary();
            }
        });

        form.addEventListener('submit', function (event) {
            const headOk = validateStep1();
            const membersOk = validateMembers();

            // Block invalid submits client-side so the page never reloads with errors;
            // the offending step is shown with the missing fields highlighted inline.
            if (!headOk) {
                event.preventDefault();
                setStep(1);
                updateStepLocks();
                showFormAlert('Complete the Head of Family fields before saving.');

                return;
            }

            if (!membersOk) {
                event.preventDefault();
                setStep(2);
                focusFirstInvalidMember();
                showFormAlert('Complete the highlighted member fields before saving.');

                return;
            }

            clearFormAlert();

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
                const target = Number(item.dataset.stepTarget);

                if (!canEnterStep(target)) {
                    validateStep1();
                    showFormAlert('Complete the Head of Family fields before continuing.');
                    setStep(1);
                    updateStepLocks();

                    return;
                }

                clearFormAlert();
                setStep(target);
            });
        });

        setEntryType(entryType);
        setStep(1);
        updateStepLocks();

        // ---- Draft auto-save / restore (new records only) -------------------
        let isRestoring = false;
        let saveTimer = null;

        function collectHeadSnapshot() {
            const head = {};
            const step1 = q(form, '.family-step-panel[data-step="1"]');

            if (step1) {
                qa(step1, 'input, select, textarea').forEach(function (field) {
                    if (field.id) {
                        head[field.id] = field.value;
                    }
                });
            }

            return head;
        }

        function collectCheckedValues(name) {
            return qa(form, 'input[name="' + name + '"]:checked').map(function (checkbox) {
                return checkbox.value;
            });
        }

        function collectMembersSnapshot() {
            if (!memberRows) {
                return [];
            }

            return qa(memberRows, '.member-row').map(function (row) {
                const data = {};

                qa(row, '[data-name]').forEach(function (input) {
                    const fieldName = input.getAttribute('data-name') || '';

                    if (fieldName === '') {
                        return;
                    }

                    if (fieldName.endsWith('[]')) {
                        const base = fieldName.slice(0, -2);

                        if (!Array.isArray(data[base])) {
                            data[base] = [];
                        }

                        if (input instanceof HTMLInputElement && input.type === 'checkbox') {
                            if (input.checked) {
                                data[base].push(input.value);
                            }
                        } else if (input instanceof HTMLSelectElement) {
                            Array.from(input.selectedOptions).forEach(function (option) {
                                data[base].push(option.value);
                            });
                        }

                        return;
                    }

                    if (input instanceof HTMLSelectElement && typeof ui.selectedFieldValue === 'function') {
                        data[fieldName] = ui.selectedFieldValue(input);
                    } else {
                        data[fieldName] = input.value;
                    }
                });

                return data;
            });
        }

        function saveDraft() {
            if (isRestoring) {
                return;
            }

            const snapshot = {
                v: 1,
                entryType: entryType,
                step: currentStep,
                head: collectHeadSnapshot(),
                sector_ids: collectCheckedValues('sector_ids[]'),
                service_ids: collectCheckedValues('service_ids[]'),
                members: collectMembersSnapshot(),
                savedAt: Date.now()
            };

            if (draftIsEmpty(snapshot)) {
                clearDraft();

                return;
            }

            try {
                window.localStorage.setItem(FAMILY_DRAFT_KEY, JSON.stringify(snapshot));
            } catch (error) {
                // Storage full or unavailable — skip this save silently.
            }
        }

        function scheduleSave() {
            if (isRestoring) {
                return;
            }

            if (saveTimer) {
                window.clearTimeout(saveTimer);
            }

            saveTimer = window.setTimeout(saveDraft, 400);
        }

        function restoreChecked(name, values) {
            const wanted = Array.isArray(values) ? values.map(String) : [];

            qa(form, 'input[name="' + name + '"]').forEach(function (checkbox) {
                checkbox.checked = wanted.indexOf(String(checkbox.value)) !== -1;
            });
        }

        function restoreDraft(snapshot) {
            isRestoring = true;

            try {
                const head = snapshot.head || {};

                Object.keys(head).forEach(function (id) {
                    const field = document.getElementById(id);

                    // Only touch fields that belong to this form instance.
                    if (field && form.contains(field)) {
                        field.value = head[id];
                    }
                });

                if (typeof ui.syncOtherControls === 'function') {
                    ui.syncOtherControls(form);
                }

                restoreChecked('sector_ids[]', snapshot.sector_ids);
                restoreChecked('service_ids[]', snapshot.service_ids);

                if (memberRows) {
                    memberRows.innerHTML = '';
                    memberIndex = 0;
                }

                (snapshot.members || []).forEach(function (memberData) {
                    createMemberRow(memberData);
                });

                if (typeof ui.setMemberRowsEmptyState === 'function') {
                    ui.setMemberRowsEmptyState(memberRows, memberRowsEmpty);
                }

                setEntryType(snapshot.entryType === 'member' ? 'member' : 'head');
                updateHeadSummary();
                updateStepLocks();
                setStep(snapshot.step);
            } finally {
                isRestoring = false;
            }
        }

        // Edit forms load real saved data — never auto-save or restore over them.
        if (form.dataset.editMode !== '1') {
            const existingDraft = readDraft();

            if (!draftIsEmpty(existingDraft)) {
                askRestoreDraft(form, existingDraft.savedAt).then(function (shouldRestore) {
                    if (shouldRestore) {
                        restoreDraft(existingDraft);
                    } else {
                        clearDraft();
                    }
                });
            }

            form.addEventListener('input', scheduleSave);
            form.addEventListener('change', scheduleSave);

            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    clearDraft();
                });
            }
        }
    }

    window.initFamilyForm = initFamilyForm;

    document.addEventListener('DOMContentLoaded', function () {
        // Rendered only after a confirmed successful save — the draft is now
        // safely persisted server-side, so discard the local copy.
        if (document.getElementById('familyDraftSavedMarker')) {
            clearDraft();
        }

        initFamilyForm(document);
    });
})(window, document);
