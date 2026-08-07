<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The .stat-card system (a component plus hand-rolled CSS grids) rendered KPI
 * tiles before .kpi-card in a Bootstrap row replaced it. It is deleted. These
 * pin the deletion, and pin the one piece of it that deliberately survived.
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
     * The one survivor. Scanner/performance.php keeps .stat-card--* on its
     * columns because its poll looks tiles up by those class names; deleting
     * them as "leftover stat-card naming" would silently stop the page
     * repainting. Markup and JS have to keep agreeing.
     */
    public function testPerformanceTileHooksSurviveInBothMarkupAndPoll(): void
    {
        $src = (string) file_get_contents(APPPATH . 'Views/Scanner/performance.php');

        foreach (['records', 'members', 'sectors', 'services'] as $variant) {
            $this->assertStringContainsString('col stat-card--' . $variant, $src, 'markup hook');
            $this->assertStringContainsString("setTile('stat-card--" . $variant . "'", $src, 'poll lookup');
        }
    }
}
