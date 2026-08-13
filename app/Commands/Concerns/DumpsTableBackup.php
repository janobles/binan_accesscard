<?php

namespace App\Commands\Concerns;

use CodeIgniter\CLI\CLI;

/**
 * mysqldump of a single table into writable/backups, for the one-time data
 * migrations under app/Commands. Every command that rewrites existing rows
 * takes one before it writes, so a bad fold can be restored from the same
 * machine that ran it.
 *
 * The password reaches mysqldump through a temp --defaults-extra-file rather
 * than the command line, where any other local process could read it out of
 * the process list.
 *
 * BackfillMemberBarangay and RemoveSectorDuplicateCategories predate this and
 * still carry their own copies of the same two methods.
 */
trait DumpsTableBackup
{
    /**
     * Dumps one table and returns the backup path, or null when the dump
     * failed (in which case the caller must not write).
     */
    protected function backupTable(string $table, string $label): ?string
    {
        $config = (new \Config\Database())->default;
        $dir    = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'backups';

        // Owner-only: a table dump is a full copy of the personal data in it, and
        // writable/ is inside the web root on the XAMPP boxes this runs on.
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . $label . '-' . date('Ymd-His') . '.sql';
        $err  = $path . '.err';

        $credsFile = $this->writeMysqlCredsFile(
            (string) ($config['username'] ?? 'root'),
            (string) ($config['password'] ?? '')
        );

        if ($credsFile === null) {
            CLI::error('Failed to create a temp credentials file for mysqldump.');

            return null;
        }

        $cmd = 'mysqldump'
            . ' --defaults-extra-file=' . escapeshellarg($credsFile)
            . ' -h ' . escapeshellarg((string) ($config['hostname'] ?? 'localhost'))
            . ' -P ' . escapeshellarg((string) ($config['port'] ?? 3306))
            . ' ' . escapeshellarg((string) ($config['database'] ?? ''))
            . ' ' . escapeshellarg($table)
            . ' > ' . escapeshellarg($path)
            . ' 2> ' . escapeshellarg($err);

        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);
        unlink($credsFile);

        if ($code !== 0 || ! is_file($path) || filesize($path) === 0) {
            CLI::error('mysqldump exit code ' . $code . '. See ' . $err);

            return null;
        }

        @unlink($err);

        return $path;
    }

    /** Temp [client] file holding the DB credentials. Caller-independent: unlinked above. */
    private function writeMysqlCredsFile(string $user, string $pass): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'binan-mysqldump-');

        if ($path === false) {
            return null;
        }

        // Quoted, with backslashes and quotes escaped: an option file treats #, a
        // trailing space and a bare backslash as syntax, so an unquoted password
        // containing one authenticates as something other than itself.
        $quoted = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $pass) . '"';

        if (file_put_contents($path, "[client]\nuser={$user}\npassword={$quoted}\n") === false) {
            @unlink($path);

            return null;
        }

        @chmod($path, 0600);

        return $path;
    }
}
