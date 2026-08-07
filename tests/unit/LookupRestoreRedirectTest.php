<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * A failed restore has to send the user back to the Archived filter, which is
 * the only place the restore control is rendered. Sectors and Services already
 * did; Categories dropped the filter and landed the user on Active, where the
 * row they were trying to restore is not listed.
 *
 * Read from source: both error branches need the lookup table to be missing or
 * the model's update to fail, neither of which a request can provoke, so there
 * is no route to drive them through.
 */
final class LookupRestoreRedirectTest extends CIUnitTestCase
{
    /** The body of one controller method, from its signature to the closing brace. */
    private function methodSource(string $controller, string $method): string
    {
        $source = (string) file_get_contents(APPPATH . 'Controllers/Lookups/' . $controller . '.php');
        $start  = strpos($source, 'public function ' . $method . '(');

        $this->assertNotFalse($start, $controller . '::' . $method . '() not found.');

        $end = strpos($source, "\n    }", $start);

        return substr($source, $start, $end - $start);
    }

    public function testCategoryRestoreErrorBranchesKeepTheArchivedFilter(): void
    {
        $restore = $this->methodSource('CategoryController', 'restore');

        preg_match_all("/redirect\('error'[^;]*/", $restore, $matches);

        $this->assertNotEmpty($matches[0], 'restore() should still have error branches to check.');

        foreach ($matches[0] as $branch) {
            $this->assertStringContainsString(
                'true',
                $branch,
                'A failed restore must pass $archived so the user lands back on the Archived filter: ' . $branch
            );
        }
    }

    public function testTheCategoryRedirectHelperBuildsTheArchivedUrl(): void
    {
        $source = (string) file_get_contents(APPPATH . 'Controllers/Lookups/CategoryController.php');

        $this->assertStringContainsString(
            "'reference-data?tab=categories' . (\$archived ? '&status=archived' : '')",
            $source
        );
    }

    /**
     * The two controllers this one was measured against, so a future edit that
     * drops their filter is caught in the same place.
     */
    public function testSectorAndServiceRestoreStillKeepTheArchivedFilter(): void
    {
        foreach (['SectorController' => 'sectors', 'ServiceController' => 'services'] as $controller => $tab) {
            $restore = $this->methodSource($controller, 'restore');

            // Error branches only. The success branch goes to Active on purpose:
            // the row it just restored is not on the Archived list any more.
            preg_match_all("/redirectAdmin\('([^']*)', 'error'/", $restore, $matches);

            $this->assertNotEmpty($matches[1], $controller . '::restore() should still have error branches to check.');

            foreach ($matches[1] as $target) {
                $this->assertStringContainsString(
                    'tab=' . $tab . '&status=archived',
                    $target,
                    $controller . '::restore() redirects a failure to ' . $target . ', losing the Archived filter.'
                );
            }
        }
    }
}
