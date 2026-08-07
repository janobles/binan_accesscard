<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * components/page_tabs.php gained an optional $queryParams passthrough (Task
 * 11 fix-up) so a page-level selector like the dashboard's ?batch= survives a
 * ?tab= switch. Distribution and Reference Data never pass it, so their href
 * must render byte-for-byte the same as before the change.
 */
final class PageTabsQueryParamsTest extends CIUnitTestCase
{
    private function render(array $data): string
    {
        return view('components/page_tabs', $data, ['saveData' => false]);
    }

    public function testNoQueryParamsRendersThePlainTabHref(): void
    {
        $html = $this->render([
            'tabs' => [['key' => 'batches', 'label' => 'Batches']],
            'active' => 'batches',
            'baseUrl' => 'distribution',
        ]);

        $this->assertStringContainsString('href="' . site_url('distribution') . '?tab=batches"', $html);
    }

    public function testQueryParamsAreCarriedThroughEveryTabLink(): void
    {
        $html = $this->render([
            'tabs' => [
                ['key' => 'barangay', 'label' => 'Barangay'],
                ['key' => 'remaining', 'label' => 'Remaining'],
            ],
            'active' => 'barangay',
            'baseUrl' => 'dashboard',
            'queryParams' => ['batch' => 9],
        ]);

        $this->assertStringContainsString('href="' . site_url('dashboard') . '?tab=barangay&amp;batch=9"', $html);
        $this->assertStringContainsString('href="' . site_url('dashboard') . '?tab=remaining&amp;batch=9"', $html);
    }

    public function testParamDefaultsToTab(): void
    {
        $html = $this->render([
            'tabs'    => [['key' => 'barangay', 'label' => 'Barangay']],
            'active'  => 'barangay',
            'baseUrl' => 'dashboard',
        ]);

        $this->assertStringContainsString('?tab=barangay', $html);
        $this->assertStringNotContainsString('?view=', $html);
    }

    public function testParamOverridesTheQueryKey(): void
    {
        $html = $this->render([
            'tabs'    => [['key' => 'overview', 'label' => 'Overview']],
            'active'  => 'overview',
            'baseUrl' => 'dashboard',
            'param'   => 'view',
        ]);

        $this->assertStringContainsString('?view=overview', $html);
        $this->assertStringNotContainsString('?tab=', $html);
    }

    public function testParamCarriesExtraQueryParams(): void
    {
        $html = $this->render([
            'tabs'        => [['key' => 'distribution', 'label' => 'Distribution']],
            'active'      => 'distribution',
            'baseUrl'     => 'dashboard',
            'param'       => 'view',
            'queryParams' => ['batch' => 5],
        ]);

        $this->assertStringContainsString('href="' . site_url('dashboard') . '?view=distribution&amp;batch=5"', $html);
    }
}
