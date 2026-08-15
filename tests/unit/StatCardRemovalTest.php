<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The .stat-card system (a component plus hand-rolled CSS grids) rendered KPI
 * tiles before .kpi-card in a Bootstrap row replaced it. It is deleted,
 * including the one exception (Scanner/performance.php's poll hooks) that had
 * survived until the kiosk performance page and the station modal moved onto
 * the shared Scanner/_metrics-grid.php partial, addressed by [data-metric].
 * These pin the deletion.
 */
final class StatCardRemovalTest extends CIUnitTestCase
{
    public function testTheComponentIsGone(): void
    {
        $this->assertFileDoesNotExist(APPPATH . 'Views/components/stat_card.php');
    }

    /**
     * The grids and tile styling must not come back. Checked against rule
     * selectors, not the whole file, so the comment explaining the removal does
     * not read as a live rule.
     */
    public function testTheStylesAreGone(): void
    {
        $css = (string) file_get_contents(FCPATH . 'css/theme.css');

        // Strip comments first: the file explains what was removed and why.
        $rules = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        foreach (['.stat-card', '.stat-card-content', '.stat-card-icon', '.overview-stats', '.reports-stats'] as $selector) {
            $this->assertDoesNotMatchRegularExpression(
                '/^\s*' . preg_quote($selector, '/') . '\s*[,{]/m',
                $rules,
                $selector . ' should have been deleted with the stat-card system'
            );
        }
    }

    /** No view may call the deleted component. */
    public function testNoViewRendersTheComponent(): void
    {
        // Walked in PHP rather than shelled out to grep: shell_exec is disabled
        // on plenty of hardened PHP builds, where the old version of this test
        // read an empty string as "no hits" and passed without looking.
        $hits = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH));

        foreach ($files as $file) {
            if ($file->isFile() && str_contains((string) file_get_contents($file->getPathname()), 'components/stat_card')) {
                $hits[] = $file->getPathname();
            }
        }

        $this->assertSame([], $hits);
    }

    /**
     * The one former survivor, now retired too. Scanner/performance.php used
     * to keep .stat-card--* on its columns because its poll looked tiles up
     * by those class names; the KPI cards those hooks addressed are gone,
     * replaced by Scanner/_metrics-grid.php's label-over-value lines, which
     * both the modal and the kiosk page address by [data-metric] instead.
     * Markup and JS still have to keep agreeing, just on the new attribute.
     */
    public function testPerformanceUsesDataMetricNotStatCardHooks(): void
    {
        $src = (string) file_get_contents(APPPATH . 'Views/Scanner/performance.php');

        $this->assertStringNotContainsString('stat-card--', $src, 'the retired hooks must not come back');

        foreach (['families', 'handouts', 'pace', 'typical', 'onStation', 'idle', 'bestHour', 'share'] as $metric) {
            $this->assertStringContainsString("text[key] : '-'", $src, 'poll reads data-metric generically');
            $this->assertStringContainsString($metric . ':', $src, $metric . ' must be handled by the poll');
        }
    }
}
