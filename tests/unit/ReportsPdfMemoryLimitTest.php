<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ReportsPdfGenerator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guards the render ceilings on the report PDF.
 *
 * Two regressions live here. The limit must stay finite, because an unlimited
 * one lets a single oversized report exhaust the machine rather than fail one
 * download. And restoring it afterwards must not itself throw: PHP refuses to
 * set memory_limit below current usage, and an unguarded restore turned a
 * report that had already rendered into a 500 on the way out.
 */
final class ReportsPdfMemoryLimitTest extends CIUnitTestCase
{
    public function testTheRenderCeilingIsFinite(): void
    {
        $source = (string) file_get_contents(APPPATH . 'Libraries/Scanner/ReportsPdfGenerator.php');

        $this->assertStringNotContainsString("ini_set('memory_limit', '-1')", $source);
        $this->assertMatchesRegularExpression(
            "/RENDER_MEMORY_LIMIT\s*=\s*'\d+[KMG]'/",
            $source,
            'the render memory ceiling must be a finite php.ini shorthand value'
        );
    }

    /** @return array<string, array{string, int}> */
    public static function shorthandProvider(): array
    {
        return [
            'megabytes' => ['128M', 134217728],
            'gigabytes' => ['1G', 1073741824],
            'kilobytes' => ['512K', 524288],
            'plain bytes' => ['1048576', 1048576],
            'unlimited reads as no floor' => ['-1', 0],
        ];
    }

    /**
     * @dataProvider shorthandProvider
     */
    public function testPhpIniShorthandParsesToBytes(string $shorthand, int $expected): void
    {
        $method = new \ReflectionMethod(ReportsPdfGenerator::class, 'bytes');

        $this->assertSame($expected, $method->invoke(null, $shorthand));
    }

    /**
     * The guard's whole point: with usage above the old ceiling, restoring is
     * skipped rather than attempted and thrown from.
     */
    public function testRestoreIsSkippedWhileUsageIsStillAboveTheOldCeiling(): void
    {
        $generator = new ReportsPdfGenerator();
        $method    = new \ReflectionMethod(ReportsPdfGenerator::class, 'restoreMemoryLimit');

        $before = ini_get('memory_limit');

        // 1K is below anything PHPUnit itself is already using, so a real
        // ini_set would warn; the guard must decline instead.
        $method->invoke($generator, '1K');

        $this->assertSame($before, ini_get('memory_limit'), 'the ceiling must be left alone');
    }

    /** A restore the process can satisfy still happens. */
    public function testRestoreAppliesWhenUsageIsBelowTheOldCeiling(): void
    {
        $generator = new ReportsPdfGenerator();
        $method    = new \ReflectionMethod(ReportsPdfGenerator::class, 'restoreMemoryLimit');

        $before = ini_get('memory_limit');
        ini_set('memory_limit', '1G');

        $method->invoke($generator, '512M');
        $applied = ini_get('memory_limit');

        ini_set('memory_limit', $before);

        $this->assertSame('512M', $applied);
    }
}
