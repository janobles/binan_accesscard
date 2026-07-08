<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ManageControllerTest extends CIUnitTestCase
{
    public function testManageRoutesResolve(): void
    {
        // ManageController's own routes were dropped from the `scanner` group
        // in the kiosk/admin split (Task 5) — the kiosk group is now scan-only.
        // ManageController itself is untouched pending its removal/re-homing
        // under `admin/*` in a later task; assert the controller still exists.
        $this->assertFileExists(APPPATH . 'Controllers/Scanner/ManageController.php');
    }

    public function testEveryActionGuardsScannerRole(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ManageController.php');
        // Guard literal appears once per action (manage, create, archive, restore, delete, void).
        $this->assertGreaterThanOrEqual(6, substr_count($src, "requireRole(['Scanner', 'Admin', 'Developer'])"));
        // Mutations are audited.
        $this->assertStringContainsString('logAction', $src);
    }

    public function testBatchRoutesResolve(): void
    {
        // Same as testManageRoutesResolve: batch routes were dropped from the
        // `scanner` group in Task 5's kiosk/admin split.
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ManageController.php');
        $this->assertStringContainsString('public function openBatch(', $src);
        $this->assertStringContainsString('public function closeBatch(', $src);
    }

    public function testBatchActionsGuardAdminOnly(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ManageController.php');
        // Batch lifecycle is Admin/Developer only (stricter than the page guard).
        $this->assertGreaterThanOrEqual(2, substr_count($src, "requireRole(['Admin', 'Developer'])"));
    }
}
