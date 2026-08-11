<?php

namespace Tests\Support\Database;

use CodeIgniter\Database\BaseConnection;

/**
 * Builds the test SQLite schema from the MySQL dump, so a feature test renders
 * a page against the same column names production runs on.
 *
 * The dump is the schema source of truth for this project and there are no
 * migrations, so a hand-written fixture schema would be a second, silently
 * drifting definition. This reads the newest accesscardV*.sql in the project
 * root, keeps the column list of each CREATE TABLE, and rewrites the MySQL
 * spelling into something SQLite accepts (types collapse to INTEGER/TEXT/REAL,
 * secondary KEY and CONSTRAINT lines drop, AUTO_INCREMENT and ON UPDATE drop).
 *
 * Data is not loaded: a test inserts only the handful of rows it asserts on.
 */
final class DumpSchema
{
    /** Creates every table in the dump on the given connection, honouring its DBPrefix. */
    public static function create(BaseConnection $db): void
    {
        $prefix = $db->getPrefix();

        foreach (self::tables() as $name => $columns) {
            $db->query('DROP TABLE IF EXISTS ' . $prefix . $name);
            $db->query('CREATE TABLE ' . $prefix . $name . ' (' . implode(', ', $columns) . ')');
        }

        // tableExists() answers from a per-connection list built once, and the
        // models branch on it, so the list has to be dropped alongside the DDL.
        $db->resetDataCache();
    }

    /**
     * Drops every table create() made. The test connection is one in-memory
     * SQLite database shared by the whole run, so a case that leaves the dump
     * schema behind changes what the next case sees.
     */
    public static function drop(BaseConnection $db): void
    {
        $prefix = $db->getPrefix();

        foreach (array_keys(self::tables()) as $name) {
            $db->query('DROP TABLE IF EXISTS ' . $prefix . $name);
        }

        $db->resetDataCache();
    }

    /** The newest dump in the project root, or null when none is present. */
    public static function dumpPath(): ?string
    {
        $files = glob(ROOTPATH . 'accesscardV*.sql') ?: [];
        if ($files === []) {
            return null;
        }

        usort($files, static function (string $a, string $b): int {
            preg_match('/V(\d+)/', basename($a), $ma);
            preg_match('/V(\d+)/', basename($b), $mb);

            return ((int) ($ma[1] ?? 0)) <=> ((int) ($mb[1] ?? 0));
        });

        return end($files);
    }

    /**
     * Table name => list of SQLite column definitions.
     *
     * @return array<string, list<string>>
     */
    private static function tables(): array
    {
        $path = self::dumpPath();
        if ($path === null) {
            return [];
        }

        $sql = (string) file_get_contents($path);
        preg_match_all('/CREATE TABLE `(\w+)` \((.*?)\n\) ENGINE/s', $sql, $matches, PREG_SET_ORDER);

        $tables = [];
        foreach ($matches as $match) {
            preg_match('/PRIMARY KEY \(`(\w+)`\)/i', $match[2], $pk);
            $primaryKey = $pk[1] ?? null;

            $columns = [];
            foreach (explode("\n", $match[2]) as $line) {
                $definition = self::column(trim($line), $primaryKey);
                if ($definition !== null) {
                    $columns[] = $definition;
                }
            }

            if ($columns !== []) {
                $tables[$match[1]] = $columns;
            }
        }

        return $tables;
    }

    /**
     * One dump line as a SQLite column definition, or null when the line is not
     * a column. An AUTO_INCREMENT primary key is declared `INTEGER PRIMARY KEY`
     * so SQLite aliases it to the rowid: without that alias an insert leaves
     * the column NULL and a later find() by the id getInsertID() returned
     * comes back empty.
     */
    private static function column(string $line, ?string $primaryKey): ?string
    {
        $line = rtrim($line, ',');

        if ($line === '' || preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY|CONSTRAINT|FULLTEXT|INDEX)/i', $line) === 1) {
            return null;
        }

        if (preg_match('/^`(\w+)`\s+(\w+)/', $line, $parts) !== 1) {
            return null;
        }

        [$name, $type] = [$parts[1], strtolower($parts[2])];

        if ($name === $primaryKey && str_contains($type, 'int')) {
            return '`' . $name . '` INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        $sqliteType = match (true) {
            str_contains($type, 'int')                                => 'INTEGER',
            in_array($type, ['decimal', 'float', 'double'], true)     => 'REAL',
            default                                                   => 'TEXT',
        };

        $default = '';
        if (preg_match('/DEFAULT\s+(current_timestamp\(\)|NULL|\'[^\']*\'|[\d.]+)/i', $line, $defaultMatch) === 1) {
            $value   = strtolower($defaultMatch[1]) === 'current_timestamp()' ? 'CURRENT_TIMESTAMP' : $defaultMatch[1];
            $default = ' DEFAULT ' . $value;
        }

        return '`' . $name . '` ' . $sqliteType . $default;
    }
}
