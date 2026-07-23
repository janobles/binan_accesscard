// Registers the family record modals (View + Add/Update) with the shared
// dashboard loader, drives the two-step Bootstrap form (Head -> Members),
// captures repeatable family members, and submits Add/Update over AJAX to the
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
            el.classList.toggle('family-form-hidden', !!hidden);
        }
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value || '');

        return div.innerHTML;
    }

    function textValue(form, selector) {
        var field = form.querySelector(selector);

        if (!field) {
            return '';
        }

        if (field.tagName === 'SELECT') {
            if (String(field.value || '').trim() === '') {
                return '';
            }

            return field.options[field.selectedIndex] ? field.options[field.selectedIndex].text.trim() : '';
        }

        return String(field.value || '').trim();
    }

    // ---- "Other" freetext selects -----------------------------------------

    function isOtherValue(value) {
        var normalized = String(value || '').trim().toLowerCase();

        return normalized === 'other' || normalized === 'others' || normalized === '__other__';
    }

    function cleanOtherValue(value) {
        return String(value || '')
            .replace(/[^\p{L}\p{N}\s.,'\-/&()]/gu, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase()
            .replace(/(^|[^\p{L}])(\p{L})/gu, function (match, boundary, letter) {
                return boundary + letter.toUpperCase();
            });
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
        renderHeadSummary(root);
    }

    // ---- field error helper ------------------------------------------------

    function setFieldError(field, message) {
        if (!field) {
            return;
        }

        var wrapper = field.closest('[class*="col-"]') || field.parentElement;
        var feedback = wrapper ? wrapper.querySelector('[data-family-field-error]') : null;

        field.classList.toggle('is-invalid', message !== '');

        if (!feedback && wrapper && message !== '') {
            feedback = document.createElement('div');
            feedback.className = 'family-field-error';
            feedback.setAttribute('data-family-field-error', '');
            wrapper.appendChild(feedback);
        }

        if (feedback) {
            if (message !== '') {
                feedback.textContent = message;
            }

            feedback.hidden = message === '';
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

    // ---- QR number availability -------------------------------------------

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

        field.setCustomValidity('Checking whether this QR number already exists.');

        qrCheckTimer = window.setTimeout(function () {
            var url = new URL(field.dataset.qrCheckUrl, window.location.href);
            var headId = root.querySelector('[name="head_id"]');
            url.searchParams.set('control_no', field.value);
            url.searchParams.set('head_id', headId ? headId.value : '0');

            window.fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                if (sequence !== qrCheckSequence || !field.isConnected) {
                    return;
                }

                var message = result.ok && result.data.available
                    ? ''
                    : (result.data.message || 'The QR number could not be validated.');
                field.setCustomValidity(message);
                setFieldError(field, message);
            }).catch(function () {
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

    function saveDraftNow(form) {
        try {
            window.localStorage.setItem(DRAFT_KEY, JSON.stringify(snapshotForm(form)));
        } catch (error) {
            /* storage unavailable / quota */
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

        renderHeadSummary(root);
        refreshAllAgeEligibility(root);
    }

    // ---- summary -----------------------------------------------------------

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

    // Resolves a field's display value, returning the "Other" freetext when chosen.
    function fieldDisplayValue(form, selector) {
        var field = form.querySelector(selector);

        if (!field) {
            return '';
        }

        if (field.tagName === 'SELECT' && field.classList.contains('js-other-select') && isOtherValue(field.value)) {
            var otherInput = findOtherInput(field);
            var typed = otherInput ? String(otherInput.value || '').trim() : '';

            if (typed !== '') {
                return typed;
            }
        }

        return textValue(form, selector);
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
        setSummary(container, 'civil', fieldDisplayValue(form, '[data-summary="civil"]'));
        setSummary(container, 'contact', textValue(form, '[data-summary="contact"]'));
        setSummary(container, 'religion', fieldDisplayValue(form, '[data-summary="religion"]'));
        setSummary(container, 'education', fieldDisplayValue(form, '[data-summary="education"]'));
        setSummary(container, 'job', fieldDisplayValue(form, '[data-summary="job"]'));
        setSummary(container, 'income', textValue(form, '[data-summary="income"]'));
        setSummary(container, 'address', [textValue(form, '[data-summary="address"]'), textValue(form, '[data-summary="barangay"]')].filter(Boolean).join(', '));
        setSummaryList(container, 'sectors', checkedLabels(form, 'input[name="sector_ids[]"]'));
        setSummaryList(container, 'services', checkedLabels(form, 'input[name="service_ids[]"]'));
    }

    // ---- sector-linked program suggestions ----------------------------------
    // A service group is "linked" to a sector when its category name matches the
    // sector's name (same convention the server uses for archive cascades).
    // Checked sectors float their linked groups into the "Suggested" callout;
    // nothing is ever hidden — groups only relocate.

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

        refreshSuggestions(scopeEl);
    }

    function refreshAllAgeEligibility(root) {
        refreshAgeEligibility(root);
        root.querySelectorAll('[data-family-member-row]').forEach(function (row) {
            refreshAgeEligibility(row);
        });
    }

    function stampGroupOrder(box) {
        box.querySelectorAll('.family-option-group[data-service-category]').forEach(function (group, index) {
            if (!group.dataset.suggestOrder) {
                group.dataset.suggestOrder = String(index + 1);
            }
        });
    }

    // Reinsert a group at its original slot among the non-suggested groups.
    function returnGroupHome(box, suggestedContainer, group) {
        var order = parseInt(group.dataset.suggestOrder || '0', 10);
        var next = Array.from(box.querySelectorAll('.family-option-group[data-service-category]')).find(function (other) {
            return other !== group
                && !suggestedContainer.contains(other)
                && parseInt(other.dataset.suggestOrder || '0', 10) > order;
        });

        if (next) {
            box.insertBefore(group, next);
        } else {
            box.appendChild(group);
        }
    }

    // scopeEl: a member row, or the modal root (head section).
    function refreshSuggestions(scopeEl) {
        if (!scopeEl || !scopeEl.querySelectorAll) {
            return;
        }

        var row = scopeEl.matches && scopeEl.matches('[data-family-member-row]') ? scopeEl : null;
        var container = row
            ? row.querySelector('[data-family-suggested]')
            : Array.from(scopeEl.querySelectorAll('[data-family-suggested]')).find(function (el) {
                return !el.closest('[data-family-member-row]');
            });

        if (!container) {
            return;
        }

        var box = container.closest('.family-option-box');
        var holder = container.querySelector('[data-family-suggested-groups]');
        var reasonEl = container.querySelector('[data-family-suggested-reason]');

        if (!box || !holder) {
            return;
        }

        stampGroupOrder(box);

        var searchRoot = row || scopeEl;
        var sectorSelector = row ? 'input[name$="[sector_ids][]"]:checked' : 'input[name="sector_ids[]"]:checked';
        var checkedNames = [];
        var checkedKeys = {};

        searchRoot.querySelectorAll(sectorSelector).forEach(function (input) {
            var name = String(input.dataset.sectorName || '').trim();
            var key = normName(name);

            if (name === '' || checkedKeys[key]) {
                return;
            }

            checkedKeys[key] = true;
            checkedNames.push(name);
        });

        var groups = Array.from(box.querySelectorAll('.family-option-group[data-service-category]'));
        var matched = [];
        var groupKeys = {};

        groups.forEach(function (group) {
            groupKeys[normName(group.dataset.serviceCategory)] = true;

            if (checkedKeys[normName(group.dataset.serviceCategory)]) {
                matched.push(group);
            } else if (container.contains(group)) {
                returnGroupHome(box, container, group);
            }
        });

        var beforeKeys = Array.from(holder.children).map(function (group) {
            return normName(group.dataset.serviceCategory);
        }).join('|');

        matched.sort(function (a, b) {
            return parseInt(a.dataset.suggestOrder || '0', 10) - parseInt(b.dataset.suggestOrder || '0', 10);
        }).forEach(function (group) {
            holder.appendChild(group);
        });

        var afterKeys = Array.from(holder.children).map(function (group) {
            return normName(group.dataset.serviceCategory);
        }).join('|');

        container.hidden = matched.length === 0;

        if (reasonEl) {
            var matchingNames = checkedNames.filter(function (name) {
                return groupKeys[normName(name)];
            });

            reasonEl.textContent = matchingNames.length
                ? 'Showing: ' + matchingNames.join(', ') + '. All other programs are still shown below.'
                : '';
        }

        // Gentle pulse + scroll to top when the suggested set actually changed.
        if (!container.hidden && beforeKeys !== afterKeys) {
            var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (!reduceMotion) {
                container.classList.remove('is-updated');
                void container.offsetWidth;
                container.classList.add('is-updated');
                window.setTimeout(function () {
                    container.classList.remove('is-updated');
                }, 700);
                box.scrollTop = 0;
            }
        }
    }

    // ---- step navigation + validation --------------------------------------

    function validateHeadStep(container) {
        var headTrigger = container.querySelector('[data-family-step-target="head"]');
        var headTarget = headTrigger ? headTrigger.getAttribute('data-family-step-pane') : '';
        var headPane = headTarget ? container.querySelector(headTarget) : null;
        var requiredFields = headPane ? Array.from(headPane.querySelectorAll('[required]')) : [];
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

    // ---- repeatable members ------------------------------------------------

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

        initOtherSelects(row);
        refreshAgeEligibility(row);

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
        if (!validateHeadStep(root)) {
            showStep(root, 'head');
            return;
        }

        var badContact = validateMemberContacts(form);

        if (badContact) {
            showStep(root, 'members');
            badContact.focus();
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
        var entryTitle = root.querySelector('.family-entry-title');

        if (title && entryTitle) {
            title.textContent = entryTitle.textContent.trim() || 'New Family Record';
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

        // Live input: contact strip, Other reveal, required-clear, summary, draft.
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

            renderHeadSummary(root);
            scheduleSave(root);
        });

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
                    if (target.matches(SECTOR_INPUT_SELECTOR)) {
                        refreshSuggestions(target.closest('[data-family-member-row]') || root);
                    }

                    renderHeadSummary(root);
                    scheduleSave(root);
                });
            }

            if (target && target.matches(SECTOR_INPUT_SELECTOR)) {
                refreshSuggestions(target.closest('[data-family-member-row]') || root);
            }

            if (target && (target.name === 'head_birthday' || /\[birthday\]$/.test(target.name || ''))) {
                refreshAgeEligibility(target.closest('[data-family-member-row]') || root);
            }

            renderHeadSummary(root);
            scheduleSave(root);
        });

        // Add / remove repeatable family members.
        root.addEventListener('click', function (event) {
            if (event.target.closest('[data-family-add-member]')) {
                event.preventDefault();
                addMemberRow(root);
                scheduleSave(root);
                return;
            }

            var removeButton = event.target.closest('[data-family-member-remove]');

            if (removeButton) {
                event.preventDefault();
                var row = removeButton.closest('[data-family-member-row]');

                if (row) {
                    row.remove();
                    scheduleSave(root);
                }

                return;
            }

            var setHeadButton = event.target.closest('[data-family-set-head]');

            if (setHeadButton) {
                event.preventDefault();
                var headRow = setHeadButton.closest('[data-family-member-row]');

                if (headRow) {
                    promoteMemberToHead(root, headRow);
                    scheduleSave(root);
                }
            }
        });

        if (formEl) {
            formEl.addEventListener('reset', function () {
                window.setTimeout(function () {
                    clearMemberRows(root);
                    initOtherSelects(root);
                    renderHeadSummary(root);
                    refreshAllAgeEligibility(root);
                    showStep(root, 'head');

                    if (isCreateForm(root)) {
                        clearDraft();
                    }
                }, 0);
            });

            formEl.addEventListener('submit', function (event) {
                event.preventDefault();
                submitFamilyForm(root, formEl);
            });
        }

        renderHeadSummary(root);
        refreshAllAgeEligibility(root);
        showStep(root, 'head');

        // Restore-on-reopen prompt (create mode only).
        if (isCreateForm(root) && formEl) {
            var draft = readDraft();

            if (!draftIsEmpty(draft)) {
                askRestoreDraft(formEl, draft.savedAt).then(function (restore) {
                    if (restore) {
                        restoreDraftIntoForm(root, draft);
                    } else {
                        clearDraft();
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
