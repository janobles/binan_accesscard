(function (window, $) {
    function getModalInstance($modalEl) {
        if (! $modalEl.length) {
            return null;
        }

        return bootstrap.Modal.getOrCreateInstance($modalEl[0]);
    }

    function bindCloseFallback() {
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

        window.__dashboardModalCloseBound = true;
    }

    window.registerDashboardModal = function (config) {
        const $modalEl = $('#familyModal');
        const $body = $('#familyModalBody');
        const $title = $('#familyModalLabel');

        if (! $modalEl.length || !config || !config.triggerSelector) {
            return;
        }

        bindCloseFallback();

        const namespace = config.namespace || 'default';
        const eventName = 'click.dashboardModalOpen_' + namespace;

        $(document).off(eventName, config.triggerSelector).on(eventName, config.triggerSelector, function (event) {
            event.preventDefault();

            const url = $(this).data('modalUrl');
            const title = $(this).data('modalTitle') || config.defaultTitle || 'Details';

            if (!url) {
                return;
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
                $body.html(response);

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
