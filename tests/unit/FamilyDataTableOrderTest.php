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

    public function testAddressStillMaps(): void
    {
        $this->assertSame(['address', 'asc'], $this->orderFor([
            'order' => [['column' => '4', 'dir' => 'asc']],
        ]));
    }

    public function testMembersColumnFallsBackToName(): void
    {
        // Members (column 2) is not orderable; an unrecognized column falls
        // back to the name column same as any other non-orderable column.
        $this->assertSame(['name', 'desc'], $this->orderFor([
            'order' => [['column' => '2', 'dir' => 'desc']],
        ]));
    }
}
