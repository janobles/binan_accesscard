<?php

namespace Tests\Unit;

use App\Libraries\FamilyDataTablePresenter;
use CodeIgniter\Test\CIUnitTestCase;

final class FamilyDataTablePresenterTest extends CIUnitTestCase
{
    public function testPayloadEnvelopeShape(): void
    {
        $presenter = new FamilyDataTablePresenter('records', 'Admin');
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
        $presenter = new FamilyDataTablePresenter('records', 'Viewer');
        // Deliberately mixed-case input: the presenter must render what it is given.
        // Storage is uppercase in practice, but an uppercase fixture here could not tell
        // pass-through apart from re-casing, so it would not catch a reintroduced
        // mb_strtoupper.
        $row       = $presenter->row(
            ['memberID' => 5, 'firstname' => 'Ana', 'middlename' => 'Reyes', 'lastname' => 'Cruz', 'suffix' => '', 'address' => '123 St', 'birthday' => '1990-01-02', 'sectorID' => null],
            false,
            []
        );

        $this->assertSame(['qr', 'name', 'sector', 'address', 'birthday', 'actions'], array_keys($row));
        $this->assertStringContainsString('Cruz, Ana R.', $row['name']);
        $this->assertSame('123 St', $row['address']);
        $this->assertSame('1990-01-02', $row['birthday']);
        $this->assertStringContainsString('-', $row['qr']);
    }
}
