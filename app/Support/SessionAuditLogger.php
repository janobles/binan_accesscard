<?php

namespace App\Support;

use App\Models\AuditTrailsModel;
use CodeIgniter\HTTP\RequestInterface;
use Throwable;

/**
 * Writes authentication session events to audit_trails.
 */
class SessionAuditLogger
{
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
            self::positiveIntOrNull($user['memberID'] ?? null),
            'USER_LOGIN',
            $description . '.',
            $request
        );
    }

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

    private static function positiveIntOrNull(mixed $value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private static function roleLabel(string $role): string
    {
        return match (strtolower(trim($role))) {
            'developer' => 'Developer',
            'admin', 'administrator' => 'Admin',
            'user', 'employee' => 'Employee',
            default => 'User',
        };
    }

    private static function ipAddress(?RequestInterface $request): ?string
    {
        return $request !== null && method_exists($request, 'getIPAddress')
            ? $request->getIPAddress()
            : null;
    }

    private static function userAgent(?RequestInterface $request): ?string
    {
        if ($request === null || ! method_exists($request, 'getUserAgent')) {
            return null;
        }

        return $request->getUserAgent()->getAgentString();
    }
}
