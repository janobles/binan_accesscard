// Registers record management screens with the shared dashboard modal loader.
(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'family',
        triggerSelector: '.js-open-family-modal, .js-open-family-list, .js-open-family-view-modal, .js-open-family-edit-modal',
        defaultTitle: 'Manage Record',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading record form...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the record form. Please try again.</div>',
        onLoaded: function (container) {
            if (typeof window.initFamilyForm === 'function') {
                window.initFamilyForm(container);
            }
        }
    });
})(window);

(function (window, document) {
    function panelFor(target) {
        return target ? target.closest('[data-family-list-panel]') : null;
    }

    function urlWithPartial(url, panel) {
        const nextUrl = new URL(url, window.location.href);
        const partialBase = panel ? panel.dataset.familyListPartialBase : '';

        if (partialBase) {
            const partialUrl = new URL(partialBase, window.location.href);
            nextUrl.pathname = partialUrl.pathname;
        }

        nextUrl.searchParams.set('partial', '1');

        return nextUrl;
    }

    function setPanelLoading(panel, loading) {
        panel.classList.toggle('is-loading', loading);
        panel.querySelectorAll('button, input, select, a').forEach(function (control) {
            if (control.tagName === 'A') {
                control.classList.toggle('disabled', loading);
                control.setAttribute('aria-disabled', loading ? 'true' : 'false');
                return;
            }

            control.disabled = loading;
        });
    }

    function replacePanel(panel, html) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const replacement = template.content.querySelector('[data-family-list-panel]');

        if (!replacement) {
            window.location.reload();
            return null;
        }

        panel.replaceWith(replacement);
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');

        return replacement;
    }

    function loadFamilyList(panel, fullUrl, pushHistory) {
        const partialUrl = urlWithPartial(fullUrl, panel);

        setPanelLoading(panel, true);

        return window.fetch(partialUrl.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to load records.');
                }

                return response.text();
            })
            .then(function (html) {
                const nextPanel = replacePanel(panel, html);

                if (pushHistory && nextPanel) {
                    window.history.pushState({ familyList: true }, '', fullUrl);
                }

                return nextPanel;
            })
            .catch(function () {
                window.location.href = fullUrl;
            });
    }

    function closeModalFor(element) {
        const modal = element.closest('.modal');

        if (modal && window.bootstrap) {
            const instance = window.bootstrap.Modal.getInstance(modal);

            if (instance) {
                instance.hide();
            }
        }
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('[data-family-list-panel] form[method="get"]');

        if (!form) {
            return;
        }

        const panel = panelFor(form);

        if (!panel || !window.fetch || !window.history) {
            return;
        }

        event.preventDefault();

        closeModalFor(form);

        const fullUrl = new URL(form.action, window.location.href);
        const formData = new FormData(form);

        fullUrl.search = '';
        formData.forEach(function (value, key) {
            if (String(value).trim() !== '') {
                fullUrl.searchParams.append(key, value);
            }
        });

        loadFamilyList(panel, fullUrl.toString(), true);
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-family-list-panel] a[href]');

        if (!link || link.classList.contains('disabled')) {
            return;
        }

        const panel = panelFor(link);

        if (!panel || !window.fetch || !window.history) {
            return;
        }

        const href = link.getAttribute('href') || '';

        if (href === '' || href.startsWith('#')) {
            return;
        }

        event.preventDefault();
        loadFamilyList(panel, new URL(href, window.location.href).toString(), true);
    });

    window.addEventListener('popstate', function () {
        const panel = document.querySelector('[data-family-list-panel]');

        if (!panel || !window.fetch) {
            return;
        }

        loadFamilyList(panel, window.location.href, false);
    });

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('.js-family-record-action-form');

        if (!form) {
            return;
        }

        const familyName = (form.dataset.familyName || 'this family record').trim();
        const actionLabel = (form.dataset.actionLabel || 'Archive').trim();
        const actionPast = (form.dataset.actionPast || 'archived').trim();
        const fallback = actionLabel + ' ' + familyName + '? This keeps the record in the database, marks it as ' + actionPast + ', and hides it from active lists.';
        const message = (form.dataset.confirmMessage || '').trim() || fallback;

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
})(window, document);
