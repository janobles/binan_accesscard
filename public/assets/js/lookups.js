// Handles lookup tab interactions and modal form submissions for sectors/services.
(function (window, document) {
    const qs = function (selector, root) {
        return (root || document).querySelector(selector);
    };

    const qsa = function (selector, root) {
        return Array.from((root || document).querySelectorAll(selector));
    };

    function updateCsrfToken(token) {
        if (!token) {
            return;
        }

        qsa('input[name*="csrf"]', document).forEach(function (input) {
            input.value = token;
        });
    }

    function showToast(message, variant) {
        const container = qs('#toastContainer');

        if (!container) {
            return;
        }

        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-bg-' + (variant || 'success') + ' border-0';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        toast.innerHTML =
            '<div class="d-flex">' +
            '<div class="toast-body">' + String(message || '') + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '</div>';

        container.appendChild(toast);

        const toastInstance = new window.bootstrap.Toast(toast, { delay: 3000 });
        toastInstance.show();

        toast.addEventListener('hidden.bs.toast', function () {
            toast.remove();
        });
    }

    function clearValidation(form) {
        if (!form) {
            return;
        }

        qsa('.invalid-feedback', form).forEach(function (node) {
            node.remove();
        });

        qsa('.is-invalid', form).forEach(function (node) {
            node.classList.remove('is-invalid');
        });
    }

    function renderErrors(form, errors) {
        if (!form || !errors) {
            return;
        }

        Object.keys(errors).forEach(function (field) {
            const input = qs('[name="' + field + '"]', form);

            if (!input) {
                return;
            }

            input.classList.add('is-invalid');

            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block';
            feedback.textContent = String(errors[field] || 'Invalid value.');

            input.parentNode.appendChild(feedback);
        });
    }

    function postForm(form) {
        const formData = new FormData(form);

        return fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            }).catch(function () {
                return { ok: false, data: { message: 'Unable to process the request.' } };
            });
        });
    }

    function postAction(url) {
        const csrfInput = qs('input[name*="csrf"]');
        const formData = new FormData();

        if (csrfInput) {
            formData.append(csrfInput.name, csrfInput.value);
        }

        return fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            }).catch(function () {
                return { ok: false, data: { message: 'Unable to process the request.' } };
            });
        });
    }

    function bindEditButtons() {
        qsa('.js-sector-edit').forEach(function (button) {
            button.addEventListener('click', function () {
                const card = button.closest('.sector-card');
                const modal = qs('#modalSectorEdit');
                const form = qs('form', modal);

                if (!card || !modal || !form) {
                    return;
                }

                const sectorId = card.dataset.sectorId || '';
                const baseAction = form.dataset.baseAction || '';

                form.action = baseAction + '/' + sectorId;
                qs('#sectorIdEdit', modal).value = sectorId;
                qs('#sectorShortcodeEdit', modal).value = card.dataset.shortcode || '';
                qs('#sectorNameEdit', modal).value = card.dataset.name || '';
                qs('#sectorDescriptionEdit', modal).value = card.dataset.description || '';
            });
        });

        qsa('.js-service-edit').forEach(function (button) {
            button.addEventListener('click', function () {
                const row = button.closest('tr');
                const modal = qs('#modalServiceEdit');
                const form = qs('form', modal);

                if (!row || !modal || !form) {
                    return;
                }

                const serviceId = row.dataset.serviceId || '';
                const baseAction = form.dataset.baseAction || '';

                form.action = baseAction + '/' + serviceId;
                qs('#serviceIdEdit', modal).value = serviceId;
                qs('#serviceCategoryEdit', modal).value = row.dataset.category || '';
                qs('#serviceNameEdit', modal).value = row.dataset.name || '';
                qs('#serviceDescriptionEdit', modal).value = row.dataset.description || '';
            });
        });

        qsa('.js-service-add-category').forEach(function (button) {
            button.addEventListener('click', function () {
                const modal = qs('#modalServiceAdd');
                const categoryInput = qs('#serviceCategoryAdd', modal);

                if (categoryInput) {
                    categoryInput.value = button.dataset.category || '';
                }
            });
        });
    }

    function bindLookupForms() {
        qsa('.js-lookup-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                clearValidation(form);

                postForm(form).then(function (result) {
                    const data = result.data || {};
                    updateCsrfToken(data.csrf);

                    if (!result.ok || data.success === false) {
                        renderErrors(form, data.errors || {});
                        showToast(data.message || 'Please review the highlighted fields.', 'danger');
                        return;
                    }

                    showToast(data.message || 'Saved successfully.', 'success');

                    const modal = form.closest('.modal');
                    const instance = modal ? window.bootstrap.Modal.getInstance(modal) : null;

                    if (instance) {
                        instance.hide();
                    }

                    window.location.reload();
                });
            });
        });
    }

    function bindArchiveActions() {
        const modal = qs('#modalArchiveConfirm');
        const message = qs('#archiveConfirmMessage', modal);
        const warning = qs('#archiveConfirmWarning', modal);
        const confirmButton = qs('#archiveConfirmButton', modal);
        let archiveUrl = '';

        function openConfirm(label, assignments, url) {
            if (!modal || !confirmButton) {
                return;
            }

            archiveUrl = url || '';
            message.textContent = 'Archive ' + label + '?';
            warning.textContent = '';

            if (assignments > 0) {
                warning.textContent = 'Warning: ' + assignments + ' active member record(s) are assigned to this item.';
            }

            const instance = new window.bootstrap.Modal(modal);
            instance.show();
        }

        qsa('.js-sector-archive').forEach(function (button) {
            button.addEventListener('click', function () {
                const card = button.closest('.sector-card');
                const label = card ? card.dataset.name || 'sector' : 'sector';
                const assignments = card ? Number(card.dataset.assignments || 0) : 0;
                openConfirm(label, assignments, button.dataset.archiveUrl || '');
            });
        });

        qsa('.js-service-archive').forEach(function (button) {
            button.addEventListener('click', function () {
                const row = button.closest('tr');
                const label = row ? row.dataset.name || 'service' : 'service';
                const assignments = row ? Number(row.dataset.assignments || 0) : 0;
                openConfirm(label, assignments, button.dataset.archiveUrl || '');
            });
        });

        if (confirmButton) {
            confirmButton.addEventListener('click', function () {
                if (!archiveUrl) {
                    return;
                }

                postAction(archiveUrl).then(function (result) {
                    const data = result.data || {};
                    updateCsrfToken(data.csrf);

                    if (!result.ok || data.success === false) {
                        showToast(data.message || 'Unable to archive item.', 'danger');
                        return;
                    }

                    showToast(data.message || 'Item archived successfully.', 'success');
                    window.location.reload();
                });
            });
        }
    }

    function bindRestoreActions() {
        qsa('.js-sector-restore').forEach(function (button) {
            button.addEventListener('click', function () {
                const url = button.dataset.restoreUrl || '';

                if (!url) {
                    return;
                }

                postAction(url).then(function (result) {
                    const data = result.data || {};
                    updateCsrfToken(data.csrf);

                    if (!result.ok || data.success === false) {
                        showToast(data.message || 'Unable to restore sector.', 'danger');
                        return;
                    }

                    showToast(data.message || 'Sector restored successfully.', 'success');
                    window.location.reload();
                });
            });
        });

        qsa('.js-service-restore').forEach(function (button) {
            button.addEventListener('click', function () {
                const url = button.dataset.restoreUrl || '';

                if (!url) {
                    return;
                }

                postAction(url).then(function (result) {
                    const data = result.data || {};
                    updateCsrfToken(data.csrf);

                    if (!result.ok || data.success === false) {
                        showToast(data.message || 'Unable to restore service.', 'danger');
                        return;
                    }

                    showToast(data.message || 'Service restored successfully.', 'success');
                    window.location.reload();
                });
            });
        });
    }

    function bindTabPersistence() {
        const tabButtons = qsa('#lookupTabs [data-bs-toggle="tab"]');
        const hash = window.location.hash;

        if (hash === '#sectors' || hash === '#services') {
            const targetId = hash === '#services' ? '#services-tab' : '#sectors-tab';
            const tabButton = qs(targetId);

            if (tabButton) {
                new window.bootstrap.Tab(tabButton).show();
            }
        }

        tabButtons.forEach(function (button) {
            button.addEventListener('shown.bs.tab', function (event) {
                const target = event.target.getAttribute('data-bs-target');

                if (target === '#tabSectors') {
                    history.replaceState(null, '', '#sectors');
                } else if (target === '#tabServices') {
                    history.replaceState(null, '', '#services');
                }
            });
        });
    }

    function bindArchivedToggles() {
        qsa('.js-toggle-archived').forEach(function (button) {
            button.addEventListener('click', function () {
                const targetKey = button.dataset.target || '';
                const rows = qs('[data-archived-body="' + targetKey + '"]');

                if (!rows) {
                    return;
                }

                rows.classList.toggle('d-none');

                if (rows.classList.contains('d-none')) {
                    button.textContent = 'Show archived (' + (button.dataset.count || '0') + ')';
                } else {
                    button.textContent = 'Hide archived';
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindEditButtons();
        bindLookupForms();
        bindArchiveActions();
        bindRestoreActions();
        bindTabPersistence();
        bindArchivedToggles();
    });
})(window, document);
