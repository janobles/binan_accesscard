// Registers the family record modals (View + Add/Update) with the shared
// dashboard loader, drives the single-page Bootstrap entry form (head card plus
// member rows), captures repeatable family members, and submits Add/Update over AJAX to the
// existing FamilyController::store()/update() endpoints, refreshing the
// server-side DataTable on success.
//
// Modal behavior:
//   - "Other" freetext selects (reveal + submit-time option swap)
//   - Contact number digits-only / exactly-11 validation
//   - Archived (grandfather) badge un-tick warning
//   - localStorage draft auto-save + restore-on-reopen + keep/discard-on-close
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - family-datatable.js       : window.reloadFamilyDataTable()
//   - Views  : Family/family-modal.php, Family/view.php, the #familyModal shell
//   - Backend: POST families (store), POST {role}/manage-family/update/:id
(function (window, document) {
    'use strict';

    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    var DRAFT_KEY = 'binan_family_modal_draft_v1';
    var saveTimer = null;
    var qrCheckTimer = null;
    var qrCheckSequence = 0;

    // ---- small DOM helpers -------------------------------------------------

    function setHidden(el, hidden) {
        if (el) {
            el.classList.toggle('d-none', !!hidden);
        }
    }

    // ---- "Other" freetext selects -----------------------------------------

    function isOtherValue(value) {
        var normalized = String(value || '').trim().toLowerCase();

        return normalized === 'other' || normalized === 'others' || normalized === '__other__';
    }

    // The "Other" freetext is typed by the worker, so it is stored uppercase like
    // names and addresses. Values picked from a dropdown keep their stored casing.
    function cleanOtherValue(value) {
        return String(value || '')
            .replace(/[^\p{L}\p{N}\s.,'\-/&()]/gu, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toUpperCase();
    }

    function findOtherInput(select) {
        if (!select) {
            return null;
        }

        var direct = select.dataset.otherInput || '';

        if (direct !== '') {
            return document.querySelector(direct);
        }

        var field = select.dataset.otherField || '';
        var container = select.closest('[class*="col-"]') || select.parentElement;

        return field !== '' && container
            ? container.querySelector('[data-other-for="' + field + '"]')
            : null;
    }

    function selectedFieldValue(select) {
        var otherInput = findOtherInput(select);

        if (isOtherValue(select.value) && otherInput) {
            var otherValue = String(otherInput.value || '').trim();

            return otherValue !== '' ? otherValue : select.value;
        }

        return select.value;
    }

    function syncOtherControl(select) {
        var otherInput = findOtherInput(select);

        if (!otherInput) {
            return;
        }

        var shouldShow = isOtherValue(select.value);

        setHidden(otherInput, !shouldShow);
        otherInput.required = shouldShow;

        if (!shouldShow) {
            otherInput.value = '';
        }
    }

    function optionExists(select, value) {
        return Array.from(select.options).some(function (option) {
            return option.value === value;
        });
    }

    // Sets a select to a stored value: picks the matching option, or selects the
    // "Other" option and fills the freetext when the value is a custom one.
    function setSelectValueWithOther(select, value) {
        var normalized = String(value || '');

        if (normalized === '' || optionExists(select, normalized)) {
            select.value = normalized;
            syncOtherControl(select);

            return;
        }

        var otherOption = Array.from(select.options).find(function (option) {
            return isOtherValue(option.value);
        });

        if (otherOption) {
            otherOption.selected = true;
            var otherInput = findOtherInput(select);

            if (otherInput) {
                otherInput.value = normalized;
            }

            syncOtherControl(select);
        } else {
            select.value = '';
        }
    }

    function applyOtherValues(root) {
        Array.from(root.querySelectorAll('.js-other-select')).forEach(function (select) {
            var otherInput = findOtherInput(select);

            if (!otherInput || !isOtherValue(select.value)) {
                return;
            }

            var otherValue = cleanOtherValue(otherInput.value);

            if (otherValue === '') {
                return;
            }

            var generated = Array.from(select.options).find(function (option) {
                return option.dataset.generatedOther === '1';
            });

            if (!generated) {
                generated = document.createElement('option');
                generated.dataset.generatedOther = '1';
                select.appendChild(generated);
            }

            generated.value = otherValue;
            generated.textContent = otherValue;
            generated.selected = true;
        });
    }

    function initOtherSelects(root) {
        Array.from(root.querySelectorAll('.js-other-select')).forEach(function (select) {
            var initial = typeof select.dataset.initialValue !== 'undefined' ? select.dataset.initialValue : select.value;
            setSelectValueWithOther(select, initial);
        });
    }

    // The personal fields shared by the head block (head_*) and each member row (members[i][*]).
    var PERSON_FIELDS = ['lastname', 'firstname', 'middlename', 'suffix', 'birthday', 'sex',
        'civilstatus', 'contactnumber', 'religion', 'education', 'job', 'salary'];

    function readPersonField(control) {
        if (!control) {
            return '';
        }

        return control.matches('.js-other-select') ? selectedFieldValue(control) : control.value;
    }

    function writePersonField(control, value) {
        if (!control) {
            return;
        }

        if (control.matches('.js-other-select')) {
            setSelectValueWithOther(control, value);
        } else {
            control.value = value;
        }
    }

    // "Set as Head": swap the person fields between the head block and this member row.
    // Address / barangay / QR stay with the head position (they describe the household); the
    // demoted head becomes a member whose relationship is cleared for the operator to re-pick.
    function promoteMemberToHead(root, row) {
        var form = root.querySelector('form');
        var prefix = row.dataset.memberFieldPrefix;

        if (!form || !prefix) {
            return;
        }

        var memberField = function (key) {
            var name = (prefix + '[' + key + ']').replace(/(["\\])/g, '\\$1');

            return row.querySelector('[name="' + name + '"]');
        };

        PERSON_FIELDS.forEach(function (key) {
            var headCtrl = form.querySelector('[name="head_' + key + '"]');
            var memberCtrl = memberField(key);

            if (!headCtrl || !memberCtrl) {
                return;
            }

            var headVal = readPersonField(headCtrl);
            var memberVal = readPersonField(memberCtrl);
            writePersonField(headCtrl, memberVal);
            writePersonField(memberCtrl, headVal);
        });

        writePersonField(memberField('relationship'), '');

        refreshAllAgeEligibility(root);
        refreshAllServiceCategories(root);
        refreshChoicesSummary(root);
        renumberMembers(root);

        // The swap rewrites who holds the QR card, so put the result in front of the
        // worker rather than leaving it below the fold.
        var headField = root.querySelector('[name="head_lastname"]');

        if (headField) {
            headField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // ---- field error helper ------------------------------------------------

    // Writes into the .invalid-feedback the view renders next to each control.
    // Bootstrap reveals it only while the control carries .is-invalid, so that class
    // toggle is the single switch and the div needs no hiding of its own.
    function setFieldError(field, message) {
        if (!field) {
            return;
        }

        var scope = field.closest('.input-group') || field.closest('[class*="col-"]') || field.parentElement;
        var feedback = scope ? scope.querySelector('[data-family-field-error]') : null;

        field.classList.toggle('is-invalid', message !== '');

        if (feedback) {
            feedback.textContent = message;
        }
    }

    // ---- contact number validation (ported) --------------------------------

    function isContactField(el) {
        return el && (el.name === 'head_contactnumber' || /\[contactnumber\]$/.test(el.name || ''));
    }

    function enforceContactDigits(el) {
        el.value = String(el.value || '').replace(/[^0-9]/g, '').slice(0, 11);

        if (el.value === '' || el.value.length === 11) {
            setFieldError(el, '');
        }
    }

    function validateContact(el) {
        if (!el) {
            return true;
        }

        var value = String(el.value || '').trim();

        if (value !== '' && value.length !== 11) {
            setFieldError(el, 'Contact number must be exactly 11 digits.');

            return false;
        }

        setFieldError(el, '');

        return true;
    }

    // ---- control number availability -------------------------------------------

    function scheduleQrAvailabilityCheck(root, field) {
        if (!field || field.readOnly || !field.dataset.qrCheckUrl) {
            return;
        }

        if (qrCheckTimer) {
            window.clearTimeout(qrCheckTimer);
        }

        var sequence = ++qrCheckSequence;
        field.setCustomValidity('');
        setFieldError(field, '');

        if (String(field.value || '').trim() === '' || !field.checkValidity()) {
            return;
        }

        field.setCustomValidity('Checking whether this control number already exists.');

        qrCheckTimer = window.setTimeout(function () {
            var url = new URL(field.dataset.qrCheckUrl, window.location.href);
            var headId = root.querySelector('[name="head_id"]');
            url.searchParams.set('control_no', field.value);
            url.searchParams.set('head_id', headId ? headId.value : '0');

            // A hung request must never leave setCustomValidity parked: that blocks the
            // save with no visible message. Release it and let the server decide.
            var release = window.setTimeout(function () {
                if (sequence === qrCheckSequence && field.isConnected) {
                    field.setCustomValidity('');
                    setFieldError(field, '');
                }
            }, 5000);

            window.fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                window.clearTimeout(release);

                if (sequence !== qrCheckSequence || !field.isConnected) {
                    return;
                }

                var available = result.ok && result.data.available;
                var message = available ? '' : (result.data.message || 'The control number could not be validated.');
                field.setCustomValidity(message);
                setFieldError(field, message);
            }).catch(function () {
                window.clearTimeout(release);

                if (sequence === qrCheckSequence && field.isConnected) {
                    field.setCustomValidity('');
                    setFieldError(field, '');
                }
            });
        }, 350);
    }

    // ---- confirm dialog ----------------------------------------------------

    function askModalDialog(form, options) {
        return new Promise(function (resolve) {
            var host = form.closest('#familyModalBody') || form.closest('.modal-content') || form;
            var overlay = document.createElement('div');
            var dialog = document.createElement('div');
            var icon = document.createElement('div');
            var iconGlyph = document.createElement('i');
            var copy = document.createElement('div');
            var title = document.createElement('h3');
            var message = document.createElement('p');
            var actions = document.createElement('div');
            var cancelButton = document.createElement('button');
            var confirmButton = document.createElement('button');

            overlay.className = 'family-draft-dialog-backdrop';
            overlay.setAttribute('role', 'presentation');
            dialog.className = 'family-draft-dialog';
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');

            icon.className = 'family-draft-dialog-icon' + (options.tone === 'warning' ? ' is-warning' : '');
            icon.setAttribute('aria-hidden', 'true');
            iconGlyph.className = options.iconClass || 'bi bi-question-lg';
            icon.appendChild(iconGlyph);

            copy.className = 'family-draft-dialog-copy';
            title.textContent = options.title || 'Confirm action';
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

            var finish = function (confirmed) {
                overlay.remove();
                resolve(confirmed);
            };

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    finish(false);
                    return;
                }

                var button = event.target.closest('[data-dialog-action]');

                if (button) {
                    finish(button.dataset.dialogAction === 'confirm');
                }
            });

            overlay.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    finish(false);
                }
            });

            host.appendChild(overlay);
            confirmButton.focus();
        });
    }

    function askRemoveArchivedItem(form, label) {
        return askModalDialog(form, {
            title: 'Remove archived item?',
            message: '"' + label + '" is archived. If you remove it, this person loses the benefit and it can\'t be added back later.',
            iconClass: 'bi bi-exclamation-triangle',
            tone: 'warning',
            cancelLabel: 'Keep',
            confirmLabel: 'Remove',
            confirmClass: 'btn btn-danger'
        });
    }

    function askPromoteToHead(form, name) {
        return askModalDialog(form, {
            title: 'Make this person the head?',
            message: (name !== '' ? name + ' ' : 'This member ') + 'will take the head position and hold the QR card. The current head becomes a member, and you will need to set their relationship.',
            iconClass: 'bi bi-person-up',
            tone: 'warning',
            cancelLabel: 'Cancel',
            confirmLabel: 'Make head',
            confirmClass: 'btn btn-primary'
        });
    }

    function askRestoreDraft(form, savedAt) {
        return askModalDialog(form, {
            title: 'Restore unsaved record?',
            message: 'You have an unsaved record from ' + formatDraftAge(savedAt) + '. Restore it?',
            iconClass: 'bi bi-arrow-counterclockwise',
            cancelLabel: 'Discard',
            confirmLabel: 'Restore',
            confirmClass: 'btn btn-success'
        });
    }

    function askKeepOrDiscard(form) {
        return askModalDialog(form, {
            title: 'Keep your progress?',
            message: 'You have an unsaved record. Keep it to continue later, or discard it?',
            iconClass: 'bi bi-save',
            cancelLabel: 'Discard',
            confirmLabel: 'Keep',
            confirmClass: 'btn btn-success'
        });
    }

    // Shown when the server reports a max_input_vars cutoff (code FORM_TRUNCATED):
    // nothing was saved, but the draft holds everything. Both buttons just dismiss —
    // the modal stays open with the data intact either way.
    function askTruncationNotice(form) {
        return askModalDialog(form, {
            title: 'Form too large to send',
            message: 'There were too many entries to send all at once, so nothing was saved yet. Don\'t worry — all your entries are kept on this computer. Remove a few members and Save again, or close and reopen Add Family to restore everything.',
            iconClass: 'bi bi-exclamation-triangle',
            tone: 'warning',
            cancelLabel: 'Close',
            confirmLabel: 'Keep editing',
            confirmClass: 'btn btn-success'
        });
    }

    // ---- draft persistence (create mode only) ------------------------------

    function isCreateForm(root) {
        var modeInput = root.querySelector('[name="form_mode"]');

        return !modeInput || modeInput.value !== 'update';
    }

    function readDraft() {
        try {
            var raw = window.localStorage.getItem(DRAFT_KEY);

            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    }

    function clearDraft() {
        try {
            window.localStorage.removeItem(DRAFT_KEY);
        } catch (error) {
            /* storage unavailable */
        }
    }

    function draftIsEmpty(snapshot) {
        if (!snapshot || typeof snapshot !== 'object') {
            return true;
        }

        var head = snapshot.head || {};
        var hasHead = Object.keys(head).some(function (key) {
            return String(head[key] || '').trim() !== '';
        });

        return !hasHead
            && (snapshot.members || []).length === 0
            && (snapshot.sector_ids || []).length === 0
            && (snapshot.service_ids || []).length === 0;
    }

    function formatDraftAge(savedAt) {
        var ts = Number(savedAt) || 0;

        if (ts <= 0) {
            return 'a moment ago';
        }

        var mins = Math.floor((Date.now() - ts) / 60000);

        if (mins < 1) { return 'just now'; }
        if (mins < 60) { return mins + (mins === 1 ? ' minute ago' : ' minutes ago'); }

        var hrs = Math.floor(mins / 60);

        if (hrs < 24) { return hrs + (hrs === 1 ? ' hour ago' : ' hours ago'); }

        var days = Math.floor(hrs / 24);

        return days + (days === 1 ? ' day ago' : ' days ago');
    }

    function checkedValues(scope, selector) {
        return Array.from(scope.querySelectorAll(selector + ':checked')).map(function (input) {
            return input.value;
        });
    }

    function snapshotForm(form) {
        var head = {};

        Array.from(form.querySelectorAll('[name^="head_"]')).forEach(function (field) {
            head[field.name] = field.classList.contains('js-other-select') ? selectedFieldValue(field) : field.value;
        });

        var members = Array.from(form.querySelectorAll('[data-family-member-row]')).map(function (row) {
            var data = { sector_ids: [], service_ids: [] };

            Array.from(row.querySelectorAll('input, select')).forEach(function (field) {
                var match = /members\[\d+\]\[([a-z_]+)\](\[\])?$/.exec(field.name || '');

                if (!match) {
                    return;
                }

                var key = match[1];

                if (key === 'sector_ids' || key === 'service_ids') {
                    if (field.checked) {
                        data[key].push(field.value);
                    }

                    return;
                }

                data[key] = field.classList.contains('js-other-select') ? selectedFieldValue(field) : field.value;
            });

            return data;
        });

        return {
            v: 1,
            head: head,
            sector_ids: checkedValues(form, 'input[name="sector_ids[]"]'),
            service_ids: checkedValues(form, 'input[name="service_ids[]"]'),
            members: members,
            savedAt: Date.now()
        };
    }

    // Closing is safe because the draft is kept, so the footer says so outright rather
    // than leaving the worker to infer it from a button color.
    function setDraftStatus(form, text) {
        var root = form.closest('[data-family-entry-form]') || form;
        var status = root.querySelector('[data-family-draft-status]');

        if (status) {
            status.textContent = text;
        }
    }

    function saveDraftNow(form) {
        try {
            window.localStorage.setItem(DRAFT_KEY, JSON.stringify(snapshotForm(form)));
            setDraftStatus(form, 'Draft saved');
        } catch (error) {
            /* storage unavailable / quota */
            setDraftStatus(form, '');
        }
    }

    function scheduleSave(root) {
        var form = root.querySelector('form');

        if (!form || !isCreateForm(root)) {
            return;
        }

        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(function () {
            saveDraftNow(form);
        }, 400);
    }

    function checkBoxes(scope, name, values) {
        var wanted = (values || []).map(String);

        Array.from(scope.querySelectorAll('input[name="' + name + '"]')).forEach(function (box) {
            box.checked = wanted.indexOf(String(box.value)) !== -1;
        });
    }

    function restoreDraftIntoForm(root, snapshot) {
        var form = root.querySelector('form');

        if (!form || !snapshot) {
            return;
        }

        var head = snapshot.head || {};

        Object.keys(head).forEach(function (name) {
            var field = form.querySelector('[name="' + name + '"]');

            if (!field) {
                return;
            }

            if (field.classList.contains('js-other-select')) {
                setSelectValueWithOther(field, head[name]);
            } else {
                field.value = head[name];
            }
        });

        checkBoxes(form, 'sector_ids[]', snapshot.sector_ids);
        checkBoxes(form, 'service_ids[]', snapshot.service_ids);

        clearMemberRows(root);

        (snapshot.members || []).forEach(function (member) {
            var row = addMemberRow(root);

            if (!row) {
                return;
            }

            Object.keys(member).forEach(function (key) {
                if (key === 'sector_ids' || key === 'service_ids') {
                    checkBoxes(row, row.dataset.memberFieldPrefix + '[' + key + '][]', member[key]);

                    return;
                }

                var field = row.querySelector('[name="' + row.dataset.memberFieldPrefix + '[' + key + ']"]');

                if (!field) {
                    return;
                }

                if (field.classList.contains('js-other-select')) {
                    setSelectValueWithOther(field, member[key]);
                } else {
                    field.value = member[key];
                }
            });
        });
        refreshAllAgeEligibility(root);
        refreshAllServiceCategories(root);
        refreshChoicesSummary(root);
        renumberMembers(root);
    }

    // ---- member row headers -------------------------------------------------

    // Each row says which person it is, so the head card and the member cards are
    // never confused for each other. Numbering is recomputed rather than baked into
    // the markup, because removing a row renumbers everything after it.
    function renumberMembers(root) {
        Array.from(root.querySelectorAll('[data-family-member-row]')).forEach(function (row, index) {
            var title = row.querySelector('[data-family-member-title]');

            if (!title) {
                return;
            }

            var last = row.querySelector('[name$="[lastname]"]');
            var first = row.querySelector('[name$="[firstname]"]');
            var name = [
                last ? String(last.value || '').trim() : '',
                first ? String(first.value || '').trim() : ''
            ].filter(Boolean).join(', ');

            title.textContent = 'Member ' + (index + 1) + (name !== '' ? ' — ' + name : '');
        });
    }

    // ---- sectors and services ------------------------------------------------

    var SECTOR_INPUT_SELECTOR = 'input[name="sector_ids[]"], input[name$="[sector_ids][]"]';
    var SERVICE_INPUT_SELECTOR = 'input[name="service_ids[]"], input[name$="[service_ids][]"]';

    function normName(value) {
        return String(value || '').trim().toLowerCase();
    }

    function completedAge(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));

        if (!match) {
            return null;
        }

        var year = parseInt(match[1], 10);
        var month = parseInt(match[2], 10);
        var day = parseInt(match[3], 10);
        var birthday = new Date(year, month - 1, day);
        var today = new Date();

        if (birthday.getFullYear() !== year || birthday.getMonth() !== month - 1 || birthday.getDate() !== day
            || birthday > today) {
            return null;
        }

        var age = today.getFullYear() - year;

        if (today.getMonth() < month - 1 || (today.getMonth() === month - 1 && today.getDate() < day)) {
            age -= 1;
        }

        return age;
    }

    // A person's birthday controls only that person's age-specific choices.
    function refreshAgeEligibility(scopeEl) {
        if (!scopeEl || !scopeEl.querySelectorAll) {
            return;
        }

        var row = scopeEl.matches && scopeEl.matches('[data-family-member-row]') ? scopeEl : null;
        var birthday = row
            ? row.querySelector('input[name$="[birthday]"]')
            : scopeEl.querySelector('input[name="head_birthday"]');
        var age = birthday ? completedAge(birthday.value) : null;
        var selector = row
            ? 'input[name$="[sector_ids][]"], input[name$="[service_ids][]"]'
            : 'input[name="sector_ids[]"], input[name="service_ids[]"]';

        scopeEl.querySelectorAll(selector).forEach(function (input) {
            var group = '';

            if (input.matches(SECTOR_INPUT_SELECTOR)) {
                var code = String(input.dataset.sectorCode || '').trim().toUpperCase();
                group = code === 'B' ? 'child' : (code === 'SC' ? 'senior' : '');
            } else if (input.matches(SERVICE_INPUT_SELECTOR)) {
                var serviceGroup = input.closest('[data-service-category]');
                var category = normName(serviceGroup ? serviceGroup.dataset.serviceCategory : '');
                group = category === 'bata (children)' ? 'child' : (category === 'senior citizen' ? 'senior' : '');
            }

            if (group === '') {
                return;
            }

            var allowed = age !== null && (group === 'child' ? age < 18 : age >= 60);
            var message = age === null
                ? 'Enter a valid date of birth to determine eligibility.'
                : (group === 'child'
                    ? 'Available only to persons below 18 years old.'
                    : 'Available only to persons 60 years old and above.');
            var choice = input.closest('.family-choice');

            input.disabled = !allowed;

            if (!allowed) {
                input.checked = false;
            }

            if (choice) {
                choice.title = allowed ? '' : message;
            }
        });

    }

    function refreshAllAgeEligibility(root) {
        refreshAgeEligibility(root);
        root.querySelectorAll('[data-family-member-row]').forEach(function (row) {
            refreshAgeEligibility(row);
        });
    }

    // ---- service accordion state --------------------------------------------
    // A category opens when it matches a ticked sector or already holds a tick, and
    // closes again when neither is true any more. Panels the worker opened by hand
    // are left alone: their click is an explicit instruction, so nothing auto-closes
    // it. That flag is what keeps a cleared filter or an unticked box from leaving
    // every category hanging open.

    function servicePanelsIn(scopeEl) {
        var row = scopeEl.matches && scopeEl.matches('[data-family-member-row]') ? scopeEl : null;

        return Array.from(scopeEl.querySelectorAll('[data-family-service-panel]')).filter(function (panel) {
            return row ? true : !panel.closest('[data-family-member-row]');
        });
    }

    // Drives a collapse panel without needing bootstrap.Collapse to be instantiated
    // first, and keeps the header button's aria-expanded in step.
    function setServicePanelOpen(panel, open) {
        var item = panel.closest('.accordion-item');
        var button = item ? item.querySelector('.accordion-button') : null;

        // Only the panel gets skipped when it is already where we want it. The
        // header still gets synced, so markup that arrives with the two out of
        // step (an import-fix fragment, say) is corrected instead of left alone.
        if (panel.classList.contains('show') !== open) {
            if (window.bootstrap && window.bootstrap.Collapse) {
                window.bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false })[open ? 'show' : 'hide']();
            } else {
                panel.classList.toggle('show', open);
            }
        }

        if (button) {
            button.classList.toggle('collapsed', !open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function refreshServiceCategories(scopeEl) {
        if (!scopeEl || !scopeEl.querySelectorAll) {
            return;
        }

        var row = scopeEl.matches && scopeEl.matches('[data-family-member-row]') ? scopeEl : null;
        var searchRoot = row || scopeEl;
        var sectorSelector = row ? 'input[name$="[sector_ids][]"]:checked' : 'input[name="sector_ids[]"]:checked';
        var checkedKeys = {};

        searchRoot.querySelectorAll(sectorSelector).forEach(function (input) {
            checkedKeys[normName(input.dataset.sectorName)] = true;
        });

        servicePanelsIn(searchRoot).forEach(function (panel) {
            if (panel.dataset.familyUserToggled === '1') {
                return;
            }

            var matched = !!checkedKeys[normName(panel.dataset.serviceCategory)];
            var hasChecked = panel.querySelector('input[type="checkbox"]:checked') !== null;

            setServicePanelOpen(panel, matched || hasChecked);
        });
    }

    function refreshAllServiceCategories(root) {
        refreshServiceCategories(root);
        root.querySelectorAll('[data-family-member-row]').forEach(function (row) {
            refreshServiceCategories(row);
        });
    }

    // Type-to-narrow over one accordion: non-matching choices hide, a category with no
    // match drops out, and matching categories open while the search runs. Clearing the
    // box hands the open state back to the sector-and-tick rule above, so the accordion
    // does not stay fully expanded afterwards.
    function filterServiceAccordion(input) {
        var accordion = input.parentElement ? input.parentElement.nextElementSibling : null;

        if (!accordion || !accordion.matches('[data-family-service-accordion]')) {
            return;
        }

        var term = String(input.value || '').trim().toLowerCase();
        var scope = input.closest('[data-family-member-row]') || input.closest('[data-family-entry-form]');

        accordion.querySelectorAll('[data-family-service-item]').forEach(function (item) {
            var panel = item.querySelector('[data-family-service-panel]');
            var matches = 0;

            item.querySelectorAll('.family-choice').forEach(function (choice) {
                var label = choice.querySelector('.form-check-label');
                var hit = term === '' || (label ? label.textContent.toLowerCase().indexOf(term) !== -1 : false);
                var cell = choice.closest('.col') || choice;

                cell.classList.toggle('d-none', !hit);

                if (hit) {
                    matches++;
                }
            });

            item.classList.toggle('d-none', term !== '' && matches === 0);

            if (!panel) {
                return;
            }

            if (term !== '') {
                // A search result the worker can't see is useless, so matches open for
                // the duration. This is the filter's doing, not theirs, so it does not
                // count as a manual toggle.
                setServicePanelOpen(panel, matches > 0);
            }
        });

        if (term === '' && scope) {
            refreshServiceCategories(scope);
        }
    }

    // The collapse toggle doubles as the summary of what is ticked, so a collapsed
    // block (edit mode) still says what this person is categorised as.
    function refreshChoicesSummary(root) {
        var target = root.querySelector('[data-family-choices-summary]');

        if (!target) {
            return;
        }

        var codes = Array.from(root.querySelectorAll('input[name="sector_ids[]"]:checked')).map(function (input) {
            return String(input.dataset.sectorCode || input.dataset.label || '').trim();
        }).filter(Boolean);
        var services = root.querySelectorAll('input[name="service_ids[]"]:checked').length;

        if (codes.length === 0 && services === 0) {
            target.textContent = 'Nothing selected yet';
            return;
        }

        target.textContent = 'Sectors: ' + (codes.length ? codes.join(', ') : 'none')
            + ' (' + services + ' program' + (services === 1 ? '' : 's') + ')';
    }

    // ---- head validation ---------------------------------------------------

    // The head fields are always visible now, so "the head section" is simply the
    // head-scoped required controls. No pane lookup, no step gate.
    function validateHead(container) {
        var form = container.querySelector('form');
        var requiredFields = form
            ? Array.from(form.querySelectorAll('[required]')).filter(function (field) {
                return !field.closest('[data-family-member-row]');
            })
            : [];
        var firstInvalid = null;

        requiredFields.forEach(function (field) {
            var isEmpty = String(field.value || '').trim() === '';
            var invalid = !field.checkValidity();

            setFieldError(field, invalid ? (isEmpty ? 'This field is required.' : field.validationMessage) : '');

            if (invalid && !firstInvalid) {
                firstInvalid = field;
            }
        });

        var contact = container.querySelector('[name="head_contactnumber"]');

        if (contact && !validateContact(contact) && !firstInvalid) {
            firstInvalid = contact;
        }

        if (firstInvalid) {
            firstInvalid.focus();

            return false;
        }

        return true;
    }

    function clearFieldError(field) {
        if (String(field.value || '').trim() !== '') {
            setFieldError(field, '');
        }
    }

    // ---- repeatable members ------------------------------------------------

    function memberIndex(row) {
        var match = /^members\[(\d+)\]$/.exec((row && row.dataset.memberFieldPrefix) || '');

        return match ? match[1] : null;
    }

    // Mounts the field set into a row's editor slot. The template still carries the
    // __INDEX__ placeholder, so one replace covers both the posted names and the ids
    // the labels point at.
    function buildMemberEditor(root, row) {
        var template = root.querySelector('[data-family-member-editor-template]');
        var mount = row ? row.querySelector('[data-family-member-editor]') : null;
        var index = memberIndex(row);

        if (!template || !mount || index === null) {
            return null;
        }

        mount.innerHTML = (template.innerHTML || '').replace(/__INDEX__/g, index).trim();
        initOtherSelects(mount);

        return mount;
    }

    function addMemberRow(root) {
        var button = root.querySelector('[data-family-add-member]');
        var container = root.querySelector('[data-family-members]');
        var template = root.querySelector('[data-family-member-template]');

        if (!button || !container || !template) {
            return null;
        }

        var nextIndex = parseInt(button.dataset.nextIndex || '0', 10) || 0;
        var markup = (template.innerHTML || '').replace(/__INDEX__/g, String(nextIndex));
        var holder = document.createElement('div');
        holder.innerHTML = markup.trim();

        var row = holder.querySelector('[data-family-member-row]');

        if (!row) {
            return null;
        }

        row.dataset.memberFieldPrefix = 'members[' + nextIndex + ']';
        container.appendChild(row);
        button.dataset.nextIndex = String(nextIndex + 1);

        buildMemberEditor(root, row);
        row.dataset.familyMemberOpen = '1';
        refreshAgeEligibility(row);
        refreshServiceCategories(row);

        return row;
    }

    function clearMemberRows(root) {
        var container = root.querySelector('[data-family-members]');
        var button = root.querySelector('[data-family-add-member]');

        if (container) {
            container.innerHTML = '';
        }

        if (button) {
            button.dataset.nextIndex = '0';
        }
    }

    // ---- AJAX submit -------------------------------------------------------

    function findCsrfInput(form) {
        var known = ['entry_type', 'form_mode', 'head_id'];

        return Array.from(form.querySelectorAll('input[type="hidden"]')).find(function (input) {
            return known.indexOf(input.name) === -1;
        }) || null;
    }

    function updateCsrf(form, hash) {
        if (!hash) {
            return;
        }

        var input = findCsrfInput(form);

        if (input) {
            input.value = hash;
        }
    }

    function showFamilyToast(message, isError) {
        var toast = document.createElement('div');
        toast.className = 'alert ' + (isError ? 'alert-danger' : 'alert-success') + ' family-toast shadow';
        toast.setAttribute('role', 'status');
        toast.textContent = message;
        document.body.appendChild(toast);

        window.setTimeout(function () {
            toast.style.transition = 'opacity 200ms ease';
            toast.style.opacity = '0';
            window.setTimeout(function () { toast.remove(); }, 220);
        }, 3200);
    }

    function showFormError(root, message) {
        var form = root.querySelector('form');

        if (!form) {
            return;
        }

        var alert = form.querySelector('[data-family-form-error]');

        if (!alert) {
            alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.setAttribute('data-family-form-error', '');
            form.insertBefore(alert, form.firstChild);
        }

        alert.textContent = message;
        alert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function closeFamilyModal() {
        var modalEl = document.getElementById('familyModal');

        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            modalEl.dataset.familyCloseConfirmed = '1';
            var instance = window.bootstrap.Modal.getInstance(modalEl);

            if (instance) {
                instance.hide();
            }
        }
    }

    // Mirrors FamilyController::firstIncompleteMember() so the worker sees the gap at
    // the field instead of after a round-trip. Same six fields, and the same
    // skip-if-unnamed rule as hasMemberData(): a row with no name is an empty row, not
    // an incomplete one. The server keeps its own copy and stays authoritative.
    var MEMBER_REQUIRED_FIELDS = ['birthday', 'sex', 'civilstatus', 'education', 'job', 'salary'];

    function memberHasData(row) {
        return ['lastname', 'firstname'].some(function (key) {
            var field = row.querySelector('[name$="[' + key + ']"]');

            return field && String(field.value || '').trim() !== '';
        });
    }

    function validateMembers(form) {
        var firstInvalid = null;

        Array.from(form.querySelectorAll('[data-family-member-row]')).forEach(function (row) {
            var named = memberHasData(row);

            MEMBER_REQUIRED_FIELDS.forEach(function (key) {
                var field = row.querySelector('[name$="[' + key + ']"]');

                if (!field) {
                    return;
                }

                var empty = named && String(field.value || '').trim() === '';
                setFieldError(field, empty ? 'This field is required.' : '');

                if (empty && !firstInvalid) {
                    firstInvalid = field;
                }
            });
        });

        return firstInvalid;
    }

    function validateMemberContacts(form) {
        var firstInvalid = null;

        Array.from(form.querySelectorAll('[name$="[contactnumber]"]')).forEach(function (field) {
            if (!validateContact(field) && !firstInvalid) {
                firstInvalid = field;
            }
        });

        return firstInvalid;
    }

    function submitFamilyForm(root, form) {
        // Deliberately not was-validated: that also paints every untouched optional
        // field green, which reads as "confirmed" on a box the worker never filled.
        // setFieldError's .is-invalid toggle is the only signal we want.
        if (!validateHead(root)) {
            return;
        }

        var badMember = validateMembers(form) || validateMemberContacts(form);

        if (badMember) {
            badMember.focus();
            badMember.scrollIntoView({ block: 'center' });
            return;
        }

        // Swap "Other" selects to their typed value so the custom text posts.
        applyOtherValues(form);

        // Record how many member rows we are posting so the server can detect a
        // submission truncated by PHP's max_input_vars (trailing members dropped).
        var memberCountField = form.querySelector('[data-members-count]');
        if (memberCountField) {
            memberCountField.value = String(form.querySelectorAll('[data-family-member-row]').length);
        }

        // Persist the exact state we are about to send so nothing is lost even if the
        // request dies mid-flight (browser/tab crash) before the 400ms auto-save fires.
        // Create mode only — edit mode keeps no draft (the draft key is global).
        if (isCreateForm(root)) {
            saveDraftNow(form);
        }

        var saveButton = root.querySelector('[data-family-save]');
        var originalLabel = saveButton ? saveButton.textContent : '';

        if (saveButton) {
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';
        }

        window.fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            }).catch(function () {
                return { ok: response.ok, data: {} };
            });
        }).then(function (result) {
            var data = result.data || {};

            updateCsrf(form, data.csrf);

            if (result.ok && data.status === 'success') {
                if (isCreateForm(root)) {
                    clearDraft();
                }

                closeFamilyModal();

                if (typeof window.reloadFamilyDataTable === 'function') {
                    window.reloadFamilyDataTable();
                }

                // Import-fix context (Review Import screen): the save returns a fresh review
                // report — hand it back so the screen re-renders without a reload.
                if (data.review && typeof window.importReviewApply === 'function') {
                    window.importReviewApply(data.review, data.csrf);
                }

                showFamilyToast(data.message || 'Family record saved successfully.', false);
                return;
            }

            if (saveButton) {
                saveButton.disabled = false;
                saveButton.textContent = originalLabel;
            }

            if (data.code === 'FORM_TRUNCATED') {
                // Cutoff: the server saved nothing. Guarantee the typed data is in the
                // draft (create mode) and reassure the worker — their entries are safe
                // and the resume prompt will rebuild them on reopen. Not a normal error.
                if (isCreateForm(root)) {
                    saveDraftNow(form);
                }

                showFormError(root, data.message || 'The form was too large to send all at once. Nothing was saved, but your entries are kept on this computer.');
                askTruncationNotice(form);
                return;
            }

            showFormError(root, data.message || 'The family record could not be saved. Please review the form and try again.');
        }).catch(function () {
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.textContent = originalLabel;
            }

            showFormError(root, 'A network error occurred. Please try again.');
        });
    }

    // ---- per-load initialisation -------------------------------------------

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

        if (title) {
            title.textContent = (root.dataset.familyModalTitle || '').trim() || 'New Family Record';
        }

        root.dataset.familyEntryReady = '1';

        var formEl = root.querySelector('form');

        // Mark existing member rows (Update mode) with their posting prefix.
        Array.from(root.querySelectorAll('[data-family-member-row]')).forEach(function (row, index) {
            if (!row.dataset.memberFieldPrefix) {
                row.dataset.memberFieldPrefix = 'members[' + index + ']';
            }
        });

        initOtherSelects(root);

        // Live input: contact strip, Other reveal, required-clear, draft.
        root.addEventListener('input', function (event) {
            var target = event.target;

            if (isContactField(target)) {
                enforceContactDigits(target);
            }

            if (target && target.matches('[required]')) {
                clearFieldError(target);
            }

            if (target && target.name === 'qr_control_no') {
                scheduleQrAvailabilityCheck(root, target);
            }

            if (target && (target.name === 'head_birthday' || /\[birthday\]$/.test(target.name || ''))) {
                refreshAgeEligibility(target.closest('[data-family-member-row]') || root);
            }

            if (target && target.matches('[data-family-service-filter]')) {
                filterServiceAccordion(target);
            }

            if (target && /\[(lastname|firstname)\]$/.test(target.name || '')) {
                renumberMembers(root);
            }

            scheduleSave(root);
        });

        // Per-field validation on blur, so an error lands at the field the worker just
        // left instead of only after a submit. blur does not bubble, hence capture.
        root.addEventListener('blur', function (event) {
            var target = event.target;

            if (!target || !target.matches || !target.matches('input, select, textarea')) {
                return;
            }

            if (isContactField(target)) {
                validateContact(target);
                return;
            }

            if (target.matches('[required]')) {
                var isEmpty = String(target.value || '').trim() === '';
                var invalid = !target.checkValidity();

                setFieldError(target, invalid ? (isEmpty ? 'This field is required.' : target.validationMessage) : '');
            }
        }, true);

        root.addEventListener('change', function (event) {
            var target = event.target;

            if (target && target.matches('.js-other-select')) {
                syncOtherControl(target);
            }

            // Archived (grandfather) un-tick warning.
            if (target && target.matches('input[type="checkbox"][data-archived="1"]') && !target.checked) {
                askRemoveArchivedItem(formEl || root, target.dataset.label || 'this item').then(function (remove) {
                    if (!remove) {
                        target.checked = true;
                    }

                    // "Keep" re-checks asynchronously, after the sync refresh below already ran.
                    refreshServiceCategories(target.closest('[data-family-member-row]') || root);
                    refreshChoicesSummary(root);
                    renumberMembers(root);
                    scheduleSave(root);
                });
            }

            if (target && (target.name === 'head_birthday' || /\[birthday\]$/.test(target.name || ''))) {
                refreshAgeEligibility(target.closest('[data-family-member-row]') || root);
            }

            if (target && (target.matches(SECTOR_INPUT_SELECTOR) || target.matches(SERVICE_INPUT_SELECTOR))) {
                refreshServiceCategories(target.closest('[data-family-member-row]') || root);
            }

            refreshChoicesSummary(root);
            renumberMembers(root);
            scheduleSave(root);
        });

        // Clicking a category header is an explicit instruction, so that panel stops
        // following the sector-and-tick rule and stays where the worker put it.
        root.addEventListener('click', function (event) {
            var header = event.target.closest('.accordion-button');

            if (!header) {
                return;
            }

            // An empty selector makes querySelector throw, so bail before asking.
            var selector = header.getAttribute('data-bs-target') || '';

            if (selector === '') {
                return;
            }

            var panel = root.querySelector(selector);

            if (panel) {
                panel.dataset.familyUserToggled = '1';
            }
        }, true);

        // Add / remove repeatable family members.
        root.addEventListener('click', function (event) {
            if (event.target.closest('[data-family-add-member]')) {
                event.preventDefault();
                addMemberRow(root);
                renumberMembers(root);
                scheduleSave(root);
                return;
            }

            var removeButton = event.target.closest('[data-family-member-remove]');

            if (removeButton) {
                event.preventDefault();
                var row = removeButton.closest('[data-family-member-row]');

                if (row) {
                    row.remove();
                    renumberMembers(root);
                    scheduleSave(root);
                }

                return;
            }

            var setHeadButton = event.target.closest('[data-family-set-head]');

            if (setHeadButton) {
                event.preventDefault();
                var headRow = setHeadButton.closest('[data-family-member-row]');

                if (!headRow || !formEl) {
                    return;
                }

                var firstName = headRow.querySelector('[name$="[firstname]"]');
                var lastName = headRow.querySelector('[name$="[lastname]"]');
                var memberName = [
                    firstName ? String(firstName.value || '').trim() : '',
                    lastName ? String(lastName.value || '').trim() : ''
                ].filter(Boolean).join(' ');

                askPromoteToHead(formEl, memberName).then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    promoteMemberToHead(root, headRow);
                    scheduleSave(root);
                });
            }
        });

        if (formEl) {
            formEl.addEventListener('reset', function () {
                window.setTimeout(function () {
                    clearMemberRows(root);
                    initOtherSelects(root);
                    refreshAllAgeEligibility(root);
                    refreshAllServiceCategories(root);
                    refreshChoicesSummary(root);
                    renumberMembers(root);

                    if (isCreateForm(root)) {
                        clearDraft();
                        setDraftStatus(formEl, '');
                    }
                }, 0);
            });

            formEl.addEventListener('submit', function (event) {
                event.preventDefault();
                submitFamilyForm(root, formEl);
            });
        }
        refreshAllAgeEligibility(root);
        refreshAllServiceCategories(root);
        refreshChoicesSummary(root);
        renumberMembers(root);

        // Restore-on-reopen prompt (create mode only).
        if (isCreateForm(root) && formEl) {
            var draft = readDraft();

            if (!draftIsEmpty(draft)) {
                askRestoreDraft(formEl, draft.savedAt).then(function (restore) {
                    if (restore) {
                        restoreDraftIntoForm(root, draft);
                    } else {
                        clearDraft();
                        setDraftStatus(formEl, '');
                    }
                });
            }
        }
    }

    // Intercept modal close: when an Add (create) form has unsaved work, ask the
    // worker to keep (save draft) or discard before the modal actually hides.
    function bindCloseGuard() {
        var modalEl = document.getElementById('familyModal');

        if (!modalEl || modalEl.dataset.familyCloseGuardBound === '1') {
            return;
        }

        modalEl.dataset.familyCloseGuardBound = '1';

        modalEl.addEventListener('hide.bs.modal', function (event) {
            if (modalEl.dataset.familyCloseConfirmed === '1') {
                delete modalEl.dataset.familyCloseConfirmed;
                return;
            }

            var root = modalEl.querySelector('[data-family-entry-form]');
            var form = root ? root.querySelector('form') : null;

            if (!root || !form || !isCreateForm(root)) {
                return;
            }

            var snapshot = snapshotForm(form);

            if (draftIsEmpty(snapshot)) {
                return;
            }

            event.preventDefault();

            askKeepOrDiscard(form).then(function (keep) {
                if (keep) {
                    saveDraftNow(form);
                } else {
                    clearDraft();
                }

                closeFamilyModal();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindCloseGuard);
    } else {
        bindCloseGuard();
    }

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

    // Import Review "Fix family" modal: the SAME family form, prefilled from the staged
    // import rows and pointed at the staging-save endpoint. Reuses initFamilyEntryModal
    // wholesale; the only difference is the form's action (server-rendered) and that its
    // success payload carries a fresh review report (handled in submitFamilyForm above).
    function initImportFixModal(container) {
        initFamilyEntryModal(container);
        applyImportFieldIssues(container);
    }

    // Flags each errored input (red = blocking, amber = warning) with the message beneath it,
    // from the data-family-import-field-issues JSON the builder emits on the form root.
    function applyImportFieldIssues(container) {
        var root = container.querySelector('[data-family-entry-form]');

        if (!root || !root.dataset.familyImportFieldIssues) {
            return;
        }

        var issues;

        try {
            issues = JSON.parse(root.dataset.familyImportFieldIssues);
        } catch (e) {
            return;
        }

        var form = root.querySelector('form');

        if (!form || !Array.isArray(issues)) {
            return;
        }

        issues.forEach(function (issue) {
            if (!issue || !issue.name) {
                return;
            }

            var field = form.querySelector('[name="' + String(issue.name).replace(/(["\\])/g, '\\$1') + '"]');

            if (field) {
                markImportField(field, issue);
            }
        });
    }

    function markImportField(field, issue) {
        var blocking = issue.severity === 'blocking';

        field.classList.add('import-field-flagged', blocking ? 'import-field-error' : 'import-field-warn');

        var note = document.createElement('div');
        note.className = 'small import-field-note ' + (blocking ? 'import-field-note-error' : 'import-field-note-warn');
        note.textContent = issue.message || '';

        // Sit the note below the field — or below its "Other" freetext input when there is one.
        var anchor = field;
        var other = field.parentNode ? field.parentNode.querySelector('.js-other-input') : null;
        if (other) {
            anchor = other;
        }
        anchor.insertAdjacentElement('afterend', note);

        // Clear the flag once the worker touches the field, so a corrected box stops shouting.
        var clear = function () {
            field.classList.remove('import-field-flagged', 'import-field-error', 'import-field-warn');
            if (note.parentNode) {
                note.parentNode.removeChild(note);
            }
            field.removeEventListener('input', clear);
            field.removeEventListener('change', clear);
        };

        field.addEventListener('input', clear);
        field.addEventListener('change', clear);
    }

    window.registerDashboardModal({
        namespace: 'importFix',
        triggerSelector: '.js-import-fix-edit',
        defaultTitle: 'Fix Family Record',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading form...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the form. Please try again.</div>',
        onLoaded: initImportFixModal
    });
})(window, document);
