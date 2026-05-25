// Loads dashboard partials into modals and manages Back/Close modal navigation.
(function (window, $) {
    const modalHistory = [];

    function getModalInstance($modalEl) {
        if (! $modalEl.length) {
            return null;
        }

        return bootstrap.Modal.getOrCreateInstance($modalEl[0], {
            backdrop: 'static',
            keyboard: false
        });
    }

    function cleanBodyMarkup(markup) {
        return String(markup || '').replace(/\sdata-family-form-initialized="1"/g, '');
    }

    function bindModalControls() {
        if (window.__dashboardModalCloseBound) {
            return;
        }

        $(document).on('click.dashboardModalClose', '#familyModal [data-bs-dismiss="modal"]', function () {
            const $modalEl = $('#familyModal');
            const modalInstance = getModalInstance($modalEl);

            if (modalInstance) {
                modalInstance.hide();
            }
        });

        $(document).on('click.dashboardModalBack', '#familyModal .js-family-modal-back', function () {
            const $modalEl = $('#familyModal');
            const $body = $('#familyModalBody');
            const $title = $('#familyModalLabel');
            const modalInstance = getModalInstance($modalEl);
            const previous = modalHistory.pop();

            if (!previous) {
                if (modalInstance) {
                    modalInstance.hide();
                }

                return;
            }

            if ($title.length) {
                $title.text(previous.title || 'Manage Family');
            }

            $body.html(cleanBodyMarkup(previous.body));
            $body.find('script').each(function () {
                $.globalEval(this.text || this.textContent || this.innerHTML || '');
            });

            if (typeof previous.onLoaded === 'function') {
                previous.onLoaded($body[0], previous.body);
            }
        });

        $('#familyModal').on('hidden.bs.modal', function () {
            modalHistory.length = 0;
        });

        window.__dashboardModalCloseBound = true;
    }

    window.registerDashboardModal = function (config) {
        const $modalEl = $('#familyModal');
        const $body = $('#familyModalBody');
        const $title = $('#familyModalLabel');

        if (! $modalEl.length || !config || !config.triggerSelector) {
            return;
        }

        bindModalControls();

        const namespace = config.namespace || 'default';
        const eventName = 'click.dashboardModalOpen_' + namespace;

        $(document).off(eventName, config.triggerSelector).on(eventName, config.triggerSelector, function (event) {
            event.preventDefault();

            const url = $(this).data('modalUrl');
            const title = $(this).data('modalTitle') || config.defaultTitle || 'Details';

            if (!url) {
                return;
            }

            if ($modalEl.hasClass('show') && $body.children().length) {
                modalHistory.push({
                    body: cleanBodyMarkup($body.html()),
                    onLoaded: config.onLoaded,
                    title: $title.text()
                });
            } else {
                modalHistory.length = 0;
            }

            if ($title.length) {
                $title.text(title);
            }

            $body.html(config.loadingMarkup || '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading...</span></div>');
            const modalInstance = getModalInstance($modalEl);

            if (modalInstance) {
                modalInstance.show();
            }

            $.ajax({
                url: url,
                method: 'GET',
                cache: false
            }).done(function (response) {
                $body.html(cleanBodyMarkup(response));

                $body.find('script').each(function () {
                    $.globalEval(this.text || this.textContent || this.innerHTML || '');
                });

                if (typeof config.onLoaded === 'function') {
                    config.onLoaded($body[0], response);
                }
            }).fail(function () {
                $body.html(config.errorMarkup || '<div class="alert alert-danger mb-0">Unable to load data. Please try again.</div>');
            });
        });
    };
})(window, jQuery);
