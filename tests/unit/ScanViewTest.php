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

    public function testServiceFixedByServer(): void
    {
        // The service comes from the active batch; the scan page carries it as
        // server-filled JS constants — no in-page dropdown, and it is never
        // posted (logAid derives it server-side from the batch).
        $this->assertStringNotContainsString('sessionAidType', $this->html);
        $this->assertStringContainsString('SERVICE_NAME', $this->html);
        $this->assertStringContainsString('SERVICE_ID', $this->html);
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

    public function testReceiptPanelPresent(): void
    {
        $this->assertStringContainsString('receiptPanel', $this->html);
        $this->assertStringContainsString('scan-receipt', $this->html);
    }

    public function testConfirmMentionsEnterKey(): void
    {
        $this->assertStringContainsString('Confirm (Enter)', $this->html);
    }

    public function testScanLoopJsBehaviors(): void
    {
        // clear-on-lookup, empty-Enter confirm, focus guard, step engine
        foreach (['requestSubmit', "window.addEventListener('keydown'", 'showReceipt('] as $needle) {
            $this->assertStringContainsString($needle, $this->html, "missing JS behavior: {$needle}");
        }
    }
}
