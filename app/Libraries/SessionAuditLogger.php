<?php

namespace App\Libraries;

use App\Models\Audit\AuditTrailsModel;
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
        ?RequestInterface $request
    ): void {
        if ($userId <= 0) {
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
                self::userAgent($request)
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

    /** Human-friendly role label for audit descriptions ('User' shown as 'Employee'). */
    private static function roleLabel(string $role): string
    {
        return match (strtolower(trim($role))) {
            'developer' => 'Developer',
            'admin', 'administrator' => 'Admin',
            'user', 'employee' => 'Employee',
            default => 'User',
        };
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
