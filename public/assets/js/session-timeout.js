(function () {
    const script = document.currentScript;
    const timeoutSeconds = Number(script?.dataset.timeoutSeconds || 60);
    const logoutUrl = script?.dataset.logoutUrl || 'logout?timeout=1';
    const keepAliveUrl = script?.dataset.keepAliveUrl || '';
    const timeoutMs = Math.max(1, timeoutSeconds) * 1000;
    const storageKey = 'binan_accesscard_last_activity';
    let isLoggingOut = false;
    let lastWrite = 0;
    let lastKeepAlive = 0;

    function now() {
        return Date.now();
    }

    function getLastActivity() {
        const stored = Number(localStorage.getItem(storageKey));

        return Number.isFinite(stored) && stored > 0 ? stored : now();
    }

    function markActivity() {
        const currentTime = now();

        if ((currentTime - lastWrite) < 1000) {
            return;
        }

        lastWrite = currentTime;
        localStorage.setItem(storageKey, String(currentTime));
        keepAlive();
    }

    function keepAlive() {
        const currentTime = now();
        const intervalMs = Math.max(10000, Math.floor(timeoutMs / 3));

        if (! keepAliveUrl || (currentTime - lastKeepAlive) < intervalMs) {
            return;
        }

        lastKeepAlive = currentTime;

        fetch(keepAliveUrl, { credentials: 'same-origin' })
            .then(function (response) {
                if (response.status === 401) {
                    logout();
                }
            })
            .catch(function () {});
    }

    function isExpired() {
        return (now() - getLastActivity()) >= timeoutMs;
    }

    function logout() {
        if (isLoggingOut) {
            return;
        }

        isLoggingOut = true;
        localStorage.removeItem(storageKey);
        window.location.href = logoutUrl;
    }

    function checkTimeout() {
        if (isExpired()) {
            logout();
        }
    }

    function handleActivity() {
        if (isExpired()) {
            logout();
            return;
        }

        markActivity();
    }

    if (! localStorage.getItem(storageKey)) {
        markActivity();
    }

    ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function (eventName) {
        window.addEventListener(eventName, handleActivity, { passive: true });
    });

    window.addEventListener('focus', checkTimeout);
    document.addEventListener('visibilitychange', function () {
        if (! document.hidden) {
            checkTimeout();
        }
    });

    window.addEventListener('storage', function (event) {
        if (event.key === storageKey) {
            checkTimeout();
        }
    });

    window.setInterval(checkTimeout, 5000);
})();
