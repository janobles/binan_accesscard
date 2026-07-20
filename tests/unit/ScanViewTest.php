<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ScanViewTest extends CIUnitTestCase
{
    private string $html;

    protected function setUp(): void
    {
        parent::setUp();
        $this->html = file_get_contents(APPPATH . 'Views/Scanner/scan.php');
    }

    public function testScannerManifestIncludesScanCss(): void
    {
        helper('asset');
        $this->assertContains('css/scanner-scan.css', asset_styles('scanner'));
        $this->assertFileExists(FCPATH . 'css/scanner-scan.css');
    }

    public function testNoInlineStyles(): void
    {
        $this->assertStringNotContainsString('style="', $this->html);
    }

    public function testAidTypeFixedByServer(): void
    {
        // The aid type comes from the active batch; the scan page carries it
        // as a server-filled JS constant — no in-page dropdown, and it is
        // never posted (logAid derives it server-side from the batch).
        $this->assertStringNotContainsString('sessionAidType', $this->html);
        $this->assertStringContainsString('AID_TYPE_NAME', $this->html);
        $this->assertStringNotContainsString('SERVICE_NAME', $this->html);
        $this->assertStringNotContainsString('name="service_id"', $this->html);
    }

    public function testNoActiveBatchEmptyState(): void
    {
        $this->assertStringContainsString('No active distribution batch', $this->html);
    }

    public function testTwoColumnResponsiveGrid(): void
    {
        $this->assertStringContainsString('col-lg-7', $this->html);
        $this->assertStringContainsString('col-lg-5', $this->html);
    }

    public function testOneActionScanBanner(): void
    {
        // One-action scan: no confirm form, one result banner region that
        // reads Logged (success) or Duplicate Entry (danger).
        $this->assertStringNotContainsString('id="logForm"', $this->html);
        $this->assertStringNotContainsString('Confirm (Enter)', $this->html);
        $this->assertStringContainsString('id="resultBanner"', $this->html);
        $this->assertStringContainsString('Duplicate Entry', $this->html);
        $this->assertStringContainsString('alert-success', $this->html);
        $this->assertStringContainsString('alert-danger', $this->html);
    }

    public function testScanLoopJsBehaviors(): void
    {
        // clear-on-scan, focus guard, banner renderer
        foreach
            (["\$('controlInput').value = ''", "window.addEventListener('keydown'", 'showBanner('] as $needle) {
            $this->assertStringContainsString($needle, $this->html, "missing JS behavior: {$needle}");
        }
    }
}
