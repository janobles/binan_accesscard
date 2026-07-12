<?php

namespace Tests\Unit;

use App\Controllers\Families\FamilyDataTableController;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * dataTableOrder() turns the DataTables order[] request into a
 * [columnKey, direction] pair. Default (no order requested) is QR ascending
 * so the table always opens 1 to n, per the manage-records UI spec.
 */
final class FamilyDataTableOrderTest extends CIUnitTestCase
{
    private function orderFor(array $get): array
    {
        $_GET = $get;
        Services::reset(true);
        $controller = new FamilyDataTableController();
        $controller->initController(Services::request(), Services::response(), Services::logger());

        $invoker = $this->getPrivateMethodInvoker($controller, 'dataTableOrder');

        return $invoker();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testNoOrderDefaultsToQrAscending(): void
    {
        $this->assertSame(['qr', 'asc'], $this->orderFor([]));
    }

    public function testEmptyDirectionFallsBackToQrAscending(): void
    {
        $this->assertSame(['qr', 'asc'], $this->orderFor([
            'order' => [['column' => '1', 'dir' => '']],
        ]));
    }

    public function testQrColumnMapsToQrKey(): void
    {
        $this->assertSame(['qr', 'desc'], $this->orderFor([
            'order' => [['column' => '0', 'dir' => 'desc']],
        ]));
    }

    public function testAddressAndBirthdayStillMap(): void
    {
        $this->assertSame(['address', 'asc'], $this->orderFor([
            'order' => [['column' => '3', 'dir' => 'asc']],
        ]));
        $this->assertSame(['birthday', 'desc'], $this->orderFor([
            'order' => [['column' => '4', 'dir' => 'desc']],
        ]));
    }
}
