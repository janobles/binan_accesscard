(function () {
    var body = document.body;
    if (!body) {
        return;
    }

    var timeoutMs = parseInt(body.getAttribute('data-session-timeout-ms') || '0', 10);
    var redirectUrl = body.getAttribute('data-session-timeout-redirect') || '';
    var modal = document.getElementById('session-timeout-modal');

    if (!timeoutMs || !redirectUrl || !modal) {
        return;
    }

    var redirectDelayMs = 3000;
    var timerId = null;

    var resetTimer = function () {
        if (timerId) {
            clearTimeout(timerId);
        }

        timerId = setTimeout(function () {
            modal.classList.add('is-visible');

            setTimeout(function () {
                window.location.href = redirectUrl;
            }, redirectDelayMs);
        }, timeoutMs);
    };

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function (eventName) {
        window.addEventListener(eventName, resetTimer, { passive: true });
    });

    resetTimer();
})();
