<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ManageViewTest extends CIUnitTestCase
{
    public function testManageViewHasBothSections(): void
    {
        // Tab bodies live in card-component partials; the modal stays in manage.php.
        $src = file_get_contents(APPPATH . 'Views/Scanner/manage.php')
            . file_get_contents(APPPATH . 'Views/Scanner/manage-distributions-body.php')
            . file_get_contents(APPPATH . 'Views/Scanner/manage-aidtypes-body.php');
        // Aid-types CRUD form + all-distributions void action.
        $this->assertStringContainsString('scanner/aid-types/create', $src);
        $this->assertStringContainsString('scanner/distributions/void/', $src);
        // No leftover single-QR log form.
        $this->assertStringNotContainsString('id="logForm"', $src);
    }
}
