<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;

/**
 * Pins the V21 batch schedule columns to the dump.
 *
 * The dump is the schema source of truth and there are no migrations, so a
 * column that never made it into the dump would still pass every test that
 * only touches the model. This reads the dump text directly.
 */
final class DumpV21SchemaTest extends CIUnitTestCase
{
    private function dumpText(): string
    {
        $path = DumpSchema::dumpPath();
        $this->assertNotNull($path, 'no accesscardV*.sql in the project root');

        return (string) file_get_contents($path);
    }

    /**
     * The CREATE TABLE body for distribution_batch alone. Column names like
     * `color` and `started_at` appear in other tables too, so a search across
     * the whole dump would pass on a column that never reached this one.
     */
    private function batchTable(): string
    {
        $matched = preg_match(
            '/CREATE TABLE `distribution_batch` \((.*?)\n\)/s',
            $this->dumpText(),
            $m
        );

        $this->assertSame(1, $matched, 'no CREATE TABLE `distribution_batch` in the dump');

        return $m[1];
    }

    public function testScheduleColumnsExist(): void
    {
        $table = $this->batchTable();

        foreach (['venue', 'scheduled_start', 'scheduled_end', 'daily_start_time', 'daily_end_time', 'color'] as $column) {
            $this->assertStringContainsString('`' . $column . '`', $table, $column . ' missing from distribution_batch');
        }
    }

    public function testStartedAtIsNullable(): void
    {
        $this->assertMatchesRegularExpression(
            '/`started_at` timestamp NULL DEFAULT NULL/',
            $this->batchTable(),
            'started_at must be nullable so a plotted batch can report that it has not started'
        );
    }

    public function testDumpIsV21(): void
    {
        $this->assertStringEndsWith('accesscardV21.sql', (string) DumpSchema::dumpPath());
    }
}
