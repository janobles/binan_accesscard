<?php

namespace App\Libraries;

use App\Models\Audit\AuditTrailsModel;
use App\Models\Auth\UserModel;
use CodeIgniter\HTTP\RequestInterface;
use Throwable;

/**
 * Writes authentication session events to audit_trails.
 */
class SessionAuditLogger
{
    /**
     * Records a USER_LOGIN audit row after a successful login (called by the auth
     * controllers). Builds a human-readable description from the role and username.
     */
    public static function logLogin(array $user, string $role, ?RequestInterface $request = null): void
    {
        $username = trim((string) ($user['username'] ?? ''));
        $roleLabel = self::roleLabel($role);
        $description = 'Logged in ' . $roleLabel . ' account';

        if ($username !== '') {
            $description .= ' "' . $username . '"';
        }

        self::log(
            (int) ($user['userID'] ?? 0),
            null,
            'USER_LOGIN',
            $description . '.',
            $request
        );
    }

    /**
     * Records a LOGIN_FAILED audit row for a rejected login attempt (invalid
     * credentials, disabled account, or invalid role). Attributes the row to the
     * targeted account's userID when the username matches a real one (so admins see
     * failed attempts against known accounts); otherwise userID is null and the row
     * still surfaces to admins via the SYSTEM_ACTIONS allowance. The attempted
     * username is carried into the full narrative as detail. Never throws.
     */
    public static function logFailedLogin(string $username, string $reason, ?RequestInterface $request = null): void
    {
        $username = trim($username);
        $shown = $username === '' ? '(blank)' : $username;
        $description = 'Failed login for "' . $shown . '" (' . $reason . ').';

        try {
            $userId = (new UserModel())->userIdByUsername($username) ?? 0;
        } catch (Throwable $exception) {
            $userId = 0;
        }

        self::log(
            $userId,
            null,
            'LOGIN_FAILED',
            $description,
            $request,
            'Attempted username "' . $shown . '"; reason: ' . $reason
        );
    }

    /**
     * Records a USER_LOGOUT audit row using the current session's user info, before
     * the session is cleared. $timedOut distinguishes idle-timeout logouts from
     * manual ones. Called by the auth controllers' logout().
     */
    public static function logLogoutFromSession(?RequestInterface $request = null, bool $timedOut = false): void
    {
        $session = session();
        $username = trim((string) $session->get('username'));
        $roleLabel = self::roleLabel((string) $session->get('role'));
        $description = $timedOut
            ? 'Logged out ' . $roleLabel . ' account due to inactivity'
            : 'Logged out ' . $roleLabel . ' account';

        if ($username !== '') {
            $description .= ' "' . $username . '"';
        }

        self::log(
            (int) $session->get('user_id'),
            self::positiveIntOrNull($session->get('member_id')),
            'USER_LOGOUT',
            $description . '.',
            $request
        );
    }

    /**
     * Shared writer: persists an audit row via AuditTrailsModel with the request's
     * IP and user agent. No-ops without a valid user or the audit table, and never
     * lets a logging failure break auth flow.
     */
    private static function log(
        int $userId,
        ?int $memberId,
        string $action,
        string $description,
        ?RequestInterface $request,
        ?string $detail = null
    ): void {
        // userID 0 represents an unknown/targeted account on a failed login and is
        // logged with a NULL userID by AuditTrailsModel. Only negative IDs are bogus.
        if ($userId < 0) {
            return;
        }

        $auditModel = new AuditTrailsModel();

        if (! $auditModel->hasTable()) {
            return;
        }

        try {
            $auditModel->logAction(
                $userId,
                $memberId,
                $action,
                $description,
                self::ipAddress($request),
                self::userAgent($request),
                $detail
            );
        } catch (Throwable $exception) {
            log_message('error', 'Session audit trail skipped: ' . $exception->getMessage());
        }
    }

    /** Coerces a value to a positive int, or null (used for the optional memberID). */
    private static function positiveIntOrNull(mixed $value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    /** Human-friendly role label for audit descriptions (the encoder/staff role is shown as 'Encoder'). */
    private static function roleLabel(string $role): string
    {
        return RoleAccess::auditRoleLabel($role) ?? 'Encoder';
    }

    /** Safely extracts the client IP from the request, or null. */
    private static function ipAddress(?RequestInterface $request): ?string
    {
        return $request !== null && method_exists($request, 'getIPAddress')
            ? $request->getIPAddress()
            : null;
    }

    /** Safely extracts the browser user-agent string from the request, or null. */
    private static function userAgent(?RequestInterface $request): ?string
    {
        if ($request === null || ! method_exists($request, 'getUserAgent')) {
            return null;
        }

        return $request->getUserAgent()->getAgentString();
    }
}
