<?php

namespace Tests\Unit;

use App\Libraries\FamilyDataTablePresenter;
use CodeIgniter\Test\CIUnitTestCase;

final class FamilyDataTablePresenterTest extends CIUnitTestCase
{
    public function testPayloadEnvelopeShape(): void
    {
        $presenter = new FamilyDataTablePresenter('admin/manage-family', 'Admin');
        $payload   = $presenter->payload(3, 10, 2, [['x']]);

        $this->assertSame(3, $payload['draw']);
        $this->assertSame(10, $payload['recordsTotal']);
        $this->assertSame(2, $payload['recordsFiltered']);
        $this->assertSame([['x']], $payload['data']);
        $this->assertArrayNotHasKey('error', $payload);

        $withError = $presenter->payload(1, 0, 0, [], 'boom');
        $this->assertSame('boom', $withError['error']);
    }

    public function testRowShapesHeadScopeCells(): void
    {
        $presenter = new FamilyDataTablePresenter('admin/manage-family', 'Viewer');
        $row       = $presenter->row(
            ['memberID' => 5, 'firstname' => 'Ana', 'middlename' => 'Reyes', 'lastname' => 'Cruz', 'suffix' => '', 'address' => '123 St', 'birthday' => '1990-01-02', 'sectorID' => null],
            false,
            []
        );

        $this->assertSame(['qr', 'name', 'sector', 'address', 'birthday', 'actions'], array_keys($row));
        $this->assertStringContainsString('CRUZ, ANA R.', $row['name']);
        $this->assertSame('1990-01-02', $row['birthday']);
        $this->assertStringContainsString('&mdash;', $row['qr']);
    }
}
