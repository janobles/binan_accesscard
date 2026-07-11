// Tracks user activity and auto-logs out after an idle period (default 900s).
// Keeps the server session alive by pinging session/keep-alive while the user
// is active. Cross-tab sync: if another tab logs out, this tab follows via the
// storage event without hitting the logout endpoint a second time.
//
// Connected to:
//   - Backend : GET  session/keep-alive  (Auth\AuthController::keepAlive)
//   - Backend : GET  logout?timeout=1    (Auth\AuthController::logout)
//   - login.js: clears the shared localStorage key on the login page
//   - Views   : admin_layout.php / Employee/layout.php — the <script> tag sets
//               data-timeout-seconds, data-logout-url, data-keep-alive-url,
//               and data-home-url on the script element itself
(function () {
    const script = document.currentScript;
    const timeoutSeconds = Number(script?.dataset.timeoutSeconds || 900);
    const logoutUrl = script?.dataset.logoutUrl || 'logout?timeout=1';
    const keepAliveUrl = script?.dataset.keepAliveUrl || '';
    // Where to send a tab whose session was already ended by ANOTHER tab. We go
    // here directly instead of re-hitting the logout endpoint, so we never record
    // a second (spurious) logout in the audit trail for a logout we didn't perform.
    const homeUrl = script?.dataset.homeUrl || '/';
    const timeoutMs = Math.max(1, timeoutSeconds) * 1000;
    const storageKey = 'binan_accesscard_last_activity';
    const activityEvents = ['pointerdown', 'keydown', 'mousemove', 'wheel', 'scroll', 'touchstart'];
    const resumeEvents = ['focus', 'pageshow', 'online'];
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

    function keepAlive(force = false) {
        const currentTime = now();
        const intervalMs = Math.max(10000, Math.floor(timeoutMs / 3));

        if (! keepAliveUrl || (! force && (currentTime - lastKeepAlive) < intervalMs)) {
            return;
        }

        lastKeepAlive = currentTime;

        fetch(keepAliveUrl, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (response) {
                if (response.status !== 401) {
                    return;
                }

                // A superseded session (logged in elsewhere) sends an explicit
                // redirect so we land on login with the correct reason. Anything
                // else (idle timeout) falls back to the default timeout logout.
                response.json().then(function (data) {
                    if (data && data.redirect) {
                        redirectAway(data.redirect);
                    } else {
                        logout();
                    }
                }).catch(function () {
                    logout();
                });
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

    // Navigate to a server-supplied URL (e.g. a displaced session redirected to
    // login) instead of the default timeout logout. Clearing the shared key pulls
    // sibling tabs along via the storage event.
    function redirectAway(url) {
        if (isLoggingOut) {
            return;
        }

        isLoggingOut = true;
        localStorage.removeItem(storageKey);
        window.location.href = url;
    }

    function clearActivity() {
        localStorage.removeItem(storageKey);
    }

    function checkTimeout(validateServer = false) {
        if (isExpired()) {
            logout();
            return;
        }

        if (validateServer) {
            keepAlive(true);
        }
    }

    function handleActivity() {
        if (isExpired()) {
            logout();
            return;
        }

        markActivity();
    }

    markActivity();

    activityEvents.forEach(function (eventName) {
        window.addEventListener(eventName, handleActivity, { passive: true });
    });

    document.querySelectorAll('.js-logout-link').forEach(function (link) {
        link.addEventListener('click', clearActivity);
    });

    resumeEvents.forEach(function (eventName) {
        window.addEventListener(eventName, function () {
            checkTimeout(true);
        });
    });

    document.addEventListener('visibilitychange', function () {
        if (! document.hidden) {
            checkTimeout(true);
        }
    });

    window.addEventListener('storage', function (event) {
        if (event.key === storageKey) {
            if (event.newValue === null) {
                // Another tab logged out (or timed out) and cleared the shared
                // session. Follow it to the login page WITHOUT calling the logout
                // endpoint again — that other tab already recorded the logout, so
                // re-hitting it here would log a duplicate/spurious entry.
                if (! isLoggingOut) {
                    isLoggingOut = true;
                    window.location.href = homeUrl;
                }

                return;
            }

            checkTimeout(true);
        }
    });

    window.setInterval(checkTimeout, Math.min(5000, timeoutMs));
})();
