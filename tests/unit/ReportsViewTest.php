<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ReportsViewTest extends CIUnitTestCase
{
    private string $html;

    protected function setUp(): void
    {
        parent::setUp();
        $this->html = file_get_contents(APPPATH . 'Views/Scanner/reports.php');
    }

    public function testUsesHouseStyleClasses(): void
    {
        foreach (['stat-card', 'reports-toolbar', 'reports-stats', 'reports-chart-card'] as $cls) {
            $this->assertStringContainsString($cls, $this->html, "missing house class: {$cls}");
        }
    }

    public function testUsesBootstrapIcons(): void
    {
        $this->assertMatchesRegularExpression('/class="bi bi-[a-z-]+/', $this->html);
    }

    public function testForbidsSbAdminProComponents(): void
    {
        $this->assertStringNotContainsString('border-left-', $this->html);
        $this->assertStringNotContainsString('text-xs text-uppercase', $this->html);
    }

    public function testHasChartAnchorsAndDataBlock(): void
    {
        foreach (['chartReceived', 'chartBarangay', 'chartAidType', 'reportsData'] as $id) {
            $this->assertStringContainsString($id, $this->html);
        }
    }
}
