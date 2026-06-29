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
 *   - editable credentials live in writable/developer/credentials.json, seeded from
 *     the .env developer.* keys on first use. The .env values are only the initial
 *     defaults and are NEVER written at runtime, so the account is fully editable on
 *     any deploy where writable/ is writable (which the app already requires).
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

    /** Absolute path to the JSON credentials file (born on first credential change). */
    public static function credentialsPath(): string
    {
        return WRITEPATH . 'developer' . DIRECTORY_SEPARATOR . 'credentials.json';
    }

    /**
     * The live Developer credentials as ['username' => ..., 'passwordHash' => ...].
     * Single source of truth: the writable credentials.json when present and valid,
     * otherwise the .env developer.* keys (the seed/default used until the first
     * runtime change). A missing/partial/corrupt file transparently falls back to
     * the seed.
     */
    public static function credentials(): array
    {
        $file = self::credentialsPath();
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (
                is_array($decoded)
                && isset($decoded['username'], $decoded['passwordHash'])
                && is_string($decoded['username']) && $decoded['username'] !== ''
                && is_string($decoded['passwordHash']) && $decoded['passwordHash'] !== ''
            ) {
                return ['username' => $decoded['username'], 'passwordHash' => $decoded['passwordHash']];
            }
        }

        // Seed from .env — only the initial default, never written back to .env.
        return [
            'username'     => (string) env('developer.username'),
            'passwordHash' => (string) env('developer.passwordHash'),
        ];
    }

    /** The current Developer username (live file value, else .env seed). */
    public static function username(): string
    {
        return self::credentials()['username'];
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

    /** Verifies a plaintext password against the live Argon2id hash (file or seed). */
    public static function verifyPassword(string $plain): bool
    {
        $hash = self::credentials()['passwordHash'];

        return $hash !== '' && password_verify($plain, $hash);
    }

    /**
     * Changes the Developer username, persisting it to writable/developer/credentials.json
     * (seeded from the current credentials first, so the password hash is preserved).
     * Never touches .env. Takes effect on the next login. Returns false if the file
     * cannot be written.
     */
    public static function changeUsername(string $newUsername): bool
    {
        $creds = self::credentials();
        $creds['username'] = $newUsername;

        return self::writeCredentials($creds);
    }

    /**
     * Changes the Developer password: hashes the new password (Argon2id) and writes
     * the merged credentials to the writable file, preserving the current username.
     * Never touches .env.
     */
    public static function changePassword(string $newPlain): bool
    {
        $hash = password_hash($newPlain, PASSWORD_ARGON2ID);
        if (! is_string($hash) || $hash === '') {
            return false;
        }

        $creds = self::credentials();
        $creds['passwordHash'] = $hash;

        return self::writeCredentials($creds);
    }

    /**
     * Writes the username + passwordHash pair to writable/developer/credentials.json.
     * Both must be non-empty. writable/ is always app-writable (sessions/cache live
     * there) and web-blocked by writable/.htaccess, so no special deploy setup or
     * .env write permission is needed. Returns false on any failure.
     */
    private static function writeCredentials(array $creds): bool
    {
        $clean = [
            'username'     => trim((string) ($creds['username'] ?? '')),
            'passwordHash' => (string) ($creds['passwordHash'] ?? ''),
        ];

        if ($clean['username'] === '' || $clean['passwordHash'] === '') {
            return false;
        }

        $dir = dirname(self::credentialsPath());
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }

        $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return self::atomicWrite(self::credentialsPath(), $json);
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
