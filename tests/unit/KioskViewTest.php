<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class KioskViewTest extends CIUnitTestCase
{
    public function testKioskLayoutHasNoDashboardChrome(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/kiosk-layout.php');
        $this->assertStringNotContainsString('dashboard_sidebar', $src);
        $this->assertStringNotContainsString('dashboard-topnav', $src);
        $this->assertStringContainsString('myBatchCount', $src);
        $this->assertStringContainsString("renderSection('content')", $src);
    }

    public function testSettingViewExtendsKioskLayout(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/setting.php');
        $this->assertStringContainsString("extend('Scanner/kiosk-layout')", $src);
        $this->assertStringContainsString('scanner/scan', $src);
    }

    public function testScanViewUsesKioskLayoutWithoutAidDropdown(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/scan.php');
        $this->assertStringContainsString("extend('Scanner/kiosk-layout')", $src);
        $this->assertStringNotContainsString('sessionAidType', $src);
        $this->assertStringContainsString('myBatchCount', $src);
    }
}
