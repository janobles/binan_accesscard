<?php

namespace App\Libraries;

/**
 * File-backed profile + credential store for the hardcoded Developer account.
 *
 * The Developer authenticates from .env (username + Argon2id passwordHash) and has
 * NO row in the `users` table, so it stays invisible to staff and to the audit
 * listing (see App\Libraries\RoleAccess and App\Models\Auth\UserModel::verifyDeveloperLogin).
 * To still give it a "My Account" profile without touching the database:
 *   - editable personal details live in writable/developer/profile.json, and
 *   - a password change rewrites ONLY the developer.passwordHash line in .env.
 *
 * Everything here is intentionally isolated from the DB so the Developer can never
 * leak into the users table or be seen/edited by other roles.
 */
class DeveloperProfile
{
    /** Personal-detail keys the My Account modal reads/writes. */
    private const FIELDS = ['last_name', 'first_name', 'middle_name', 'suffix', 'address', 'contact_no', 'birthday'];

    /** Absolute path to the JSON profile file (created on first save). */
    public static function path(): string
    {
        return WRITEPATH . 'developer' . DIRECTORY_SEPARATOR . 'profile.json';
    }

    /** The Developer username, sourced from .env (never editable via the UI). */
    public static function username(): string
    {
        return (string) env('developer.username');
    }

    /**
     * Returns the stored personal details with every expected key present (''),
     * so the shared modal can prefill without isset() noise. Empty when the file
     * does not exist yet or is unreadable/corrupt.
     */
    public static function load(): array
    {
        $details = array_fill_keys(self::FIELDS, '');

        $file = self::path();
        if (! is_file($file)) {
            return $details;
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        if (! is_array($decoded)) {
            return $details;
        }

        foreach (self::FIELDS as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key])) {
                $details[$key] = $decoded[$key];
            }
        }

        return $details;
    }

    /**
     * Persists the personal details to writable/developer/profile.json. Only the
     * known FIELDS are written. Returns false if the directory or file cannot be
     * written.
     */
    public static function save(array $details): bool
    {
        $clean = [];
        foreach (self::FIELDS as $key) {
            $clean[$key] = trim((string) ($details[$key] ?? ''));
        }

        $dir = dirname(self::path());
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }

        $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return self::atomicWrite(self::path(), $json);
    }

    /** Verifies a plaintext password against the live .env Argon2id hash. */
    public static function verifyPassword(string $plain): bool
    {
        $hash = (string) env('developer.passwordHash');

        return $hash !== '' && password_verify($plain, $hash);
    }

    /**
     * Rewrites the `developer.username` line in .env. The new username takes effect
     * on the next login (dotenv is loaded at boot); the current session is not
     * affected. Returns false (old username intact) if .env cannot be written.
     */
    public static function changeUsername(string $newUsername): bool
    {
        return self::writeEnvLine('developer.username', $newUsername);
    }

    /**
     * Rewrites the `developer.passwordHash` line in .env with a fresh Argon2id hash
     * of $newPlain. Same persistence + timing rules as changeUsername().
     */
    public static function changePassword(string $newPlain): bool
    {
        $hash = password_hash($newPlain, PASSWORD_ARGON2ID);
        if (! is_string($hash) || $hash === '') {
            return false;
        }

        return self::writeEnvLine('developer.passwordHash', $hash);
    }

    /**
     * Replaces ONLY the `key = '...'` line in .env, preserving the rest of the file.
     * Single-quoted so $-segments are not interpolated by dotenv; rejects values
     * containing a quote or newline so the file can never be corrupted. Writes in
     * place under an exclusive lock (not unlink+rename, which would briefly leave no
     * .env on Windows). Returns false on any failure, so the old value survives.
     */
    private static function writeEnvLine(string $key, string $value): bool
    {
        if (strpbrk($value, "'\r\n") !== false) {
            return false;
        }

        $envFile = ROOTPATH . '.env';
        if (! is_file($envFile) || ! is_writable($envFile)) {
            return false;
        }

        $contents = (string) file_get_contents($envFile);
        $replacement = $key . " = '" . $value . "'";
        $updated = preg_replace(
            '/^[ \t]*' . preg_quote($key, '/') . '[ \t]*=.*$/m',
            $replacement,
            $contents,
            1,
            $count
        );

        if ($updated === null || $count === 0) {
            return false;
        }

        return file_put_contents($envFile, $updated, LOCK_EX) !== false;
    }

    /**
     * Writes a brand-new (non-critical) file atomically: temp file + rename, with
     * a Windows-safe overwrite fallback.
     */
    private static function atomicWrite(string $path, string $content): bool
    {
        $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }

        // rename() cannot overwrite an existing file on Windows; drop it first.
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
}
