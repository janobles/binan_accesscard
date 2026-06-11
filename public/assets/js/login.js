// Clears the idle-timeout activity timestamp from localStorage when the login
// page loads, so session-timeout.js starts a fresh timer on the next login.
// Connected to: session-timeout.js (shares the 'binan_accesscard_last_activity' key).
// Loaded by: Views/Auth/login.php (or the root login view).
localStorage.removeItem('binan_accesscard_last_activity');
