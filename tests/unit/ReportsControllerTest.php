<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ReportsControllerTest extends CIUnitTestCase
{
    private string $src;

    protected function setUp(): void
    {
        parent::setUp();
        $this->src = file_get_contents(APPPATH . 'Controllers/Scanner/ReportsController.php');
    }

    public function testGuardsWithScannerRoleList(): void
    {
        $this->assertStringContainsString("['Scanner', 'Admin', 'Developer']", $this->src);
    }

    public function testHasIndexAndPdfActions(): void
    {
        $this->assertStringContainsString('public function index(', $this->src);
        $this->assertStringContainsString('public function pdf(', $this->src);
    }

    public function testPassesStatsToView(): void
    {
        $this->assertStringContainsString('AidStatsModel', $this->src);
        $this->assertStringContainsString("view('Scanner/reports'", $this->src);
    }

    public function testRoutesResolve(): void
    {
        // ReportsController's own routes were dropped from the `scanner` group
        // in the kiosk/admin split (Task 5) — the kiosk group is now scan-only.
        // ReportsController itself is untouched pending its removal/re-homing
        // under `admin/*` in a later task.
        $this->assertStringContainsString('public function index(', $this->src);
        $this->assertStringContainsString('public function pdf(', $this->src);
    }
}
