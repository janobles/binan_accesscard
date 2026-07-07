<?php

namespace App\Libraries;

use CodeIgniter\HTTP\RequestInterface;
use Config\Session as SessionConfig;

/**
 * File-backed registry of the single active login per account, used to enforce one
 * concurrent session per user WITHOUT touching the database schema (CLAUDE.md: no
 * migrations — the SQL dump owns the schema).
 *
 * Storage is writable/active_sessions/sessions.json: a map of identity key =>
 * { token, username, ip, user_agent, updated_at }.
 *
 *   - Identity key is the immutable userID for real accounts, or the username for
 *     the .env Developer (userID 0, no `users` row).
 *   - `token` is a random per-login value kept in the session's DATA (not the PHP
 *     session id, which CI4 auto-rotates every Session::timeToUpdate seconds and
 *     would make the id-based match spuriously fail). App\Filters\SingleSessionFilter
 *     compares the live session's `auth_token` against this and logs out any session
 *     whose token no longer matches (i.e. a newer login took over the account).
 *   - An entry counts as "active" only while updated_at is within the idle window;
 *     SingleSessionFilter refreshes it on every protected request (mirroring the
 *     session's idle_last_activity), so an abandoned session ages out in lockstep
 *     and stops blocking re-login.
 *
 * Mirrors App\Libraries\DeveloperProfile's writable-JSON + atomic-write pattern.
 */
class ActiveSessionRegistry
{
    /** Absolute path to the registry JSON (created on first write). */
    public static function path(): string
    {
        return WRITEPATH . 'active_sessions' . DIRECTORY_SEPARATOR . 'sessions.json';
    }

    /**
     * Stable per-account key: the immutable userID for real accounts, or the
     * lower-cased username for the .env Developer (userID 0, no users row).
     */
    public static function identityKey(int $userId, string $username): string
    {
        return $userId > 0 ? 'uid:' . $userId : 'user:' . strtolower(trim($username));
    }

    /** The stored record for an identity, or null when none is registered. */
    public static function get(string $identity): ?array
    {
        return self::readAll()[$identity] ?? null;
    }

    /**
     * Records $token as the sole active session for $identity, capturing the
     * request's IP/user-agent for the "logged in elsewhere" prompt. Overwrites any
     * prior entry — that is how a confirmed new login evicts the old one.
     */
    public static function put(string $identity, string $token, string $username, ?RequestInterface $request = null): bool
    {
        $all = self::readAll();
        $all[$identity] = [
            'token'      => $token,
            'username'   => $username,
            'ip'         => self::ipAddress($request),
            'user_agent' => self::userAgent($request),
            'updated_at' => time(),
        ];

        return self::writeAll($all);
    }

    /**
     * Refreshes the updated_at heartbeat for an identity when $token is still the
     * active one. No-op if the identity is unknown or held by a different token.
     * Called by SingleSessionFilter on each protected request.
     */
    public static function touch(string $identity, string $token): void
    {
        $all = self::readAll();

        if (! isset($all[$identity]) || ($all[$identity]['token'] ?? '') !== $token) {
            return;
        }

        $all[$identity]['updated_at'] = time();
        self::writeAll($all);
    }

    /** Drops an identity's entry (clean logout). Safe when absent. */
    public static function forget(string $identity): void
    {
        $all = self::readAll();

        if (! array_key_exists($identity, $all)) {
            return;
        }

        unset($all[$identity]);
        self::writeAll($all);
    }

    /** Reads and decodes the registry map. Missing/corrupt file reads as empty. */
    private static function readAll(): array
    {
        $file = self::path();

        if (! is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Persists the map, dropping entries older than the session expiration so the
     * file cannot grow without bound. Atomic write + Windows-safe overwrite, matching
     * DeveloperProfile::atomicWrite.
     */
    private static function writeAll(array $all): bool
    {
        $cutoff = time() - (new SessionConfig())->expiration;
        foreach ($all as $key => $record) {
            if ((int) ($record['updated_at'] ?? 0) < $cutoff) {
                unset($all[$key]);
            }
        }

        $dir = dirname(self::path());
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }

        $json = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return self::atomicWrite(self::path(), $json);
    }

    /**
     * Writes a file atomically: temp file + rename, with a Windows-safe overwrite
     * fallback (rename() cannot overwrite on Windows). Mirrors DeveloperProfile.
     */
    private static function atomicWrite(string $path, string $content): bool
    {
        $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }

        if (is_file($path) && ! @unlink($path)) {
            @unlink($tmp);

            return false;
        }

        if (! @rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }

        return true;
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
