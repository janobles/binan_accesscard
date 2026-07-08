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
        // Aid type is chosen on the setting page; the scan page carries it as a
        // server-filled hidden input and constant — no in-page dropdown.
        $this->assertStringNotContainsString('sessionAidType', $this->html);
        $this->assertStringContainsString('AID_TYPE_NAME', $this->html);
        $this->assertStringContainsString('id="aid_type_id" name="aid_type_id" value=', $this->html);
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
