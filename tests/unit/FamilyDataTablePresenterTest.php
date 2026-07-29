<?php

namespace Tests\Unit;

use App\Libraries\FamilyDataTablePresenter;
use CodeIgniter\Test\CIUnitTestCase;

final class FamilyDataTablePresenterTest extends CIUnitTestCase
{
    public function testPayloadEnvelopeShape(): void
    {
        $presenter = new FamilyDataTablePresenter('Admin');
        $payload   = $presenter->payload(3, 10, 2, [['x']]);

        $this->assertSame(3, $payload['draw']);
        $this->assertSame(10, $payload['recordsTotal']);
        $this->assertSame(2, $payload['recordsFiltered']);
        $this->assertSame([['x']], $payload['data']);
        $this->assertArrayNotHasKey('error', $payload);

        $withError = $presenter->payload(1, 0, 0, [], 'boom');
        $this->assertSame('boom', $withError['error']);
    }

    public function testRowIsOneHouseholdWithItsMemberCount(): void
    {
        $presenter = new FamilyDataTablePresenter('Admin');

        $cells = $presenter->row(
            ['memberID' => 7, 'headID' => 7, 'lastname' => 'DELA CRUZ',
             'firstname' => 'JUAN', 'address' => 'CANLALAY', 'sectorID' => '1'],
            [1 => 'SC'],
            [7 => 142],
            4
        );

        $this->assertSame(['qr', 'name', 'members', 'sector', 'address', 'actions'],
            array_keys($cells));
        $this->assertSame('4', $cells['members']);
        $this->assertStringContainsString('DELA CRUZ', $cells['name']);
        $this->assertStringNotContainsString('text-muted d-block', $cells['name'],
            'A head row carries no relationship subline.');
    }

    public function testRowLinksToTheFlatProfileUri(): void
    {
        $presenter = new FamilyDataTablePresenter('Admin');

        $cells = $presenter->row(
            ['memberID' => 7, 'headID' => 7, 'lastname' => 'SANTOS', 'firstname' => 'PEDRO'],
            [],
            [7 => 143],
            3
        );

        // The URLs sit in esc(..., 'attr') attributes, which percent-encodes '/'
        // to &#x2F; - decode before asserting so the test checks the head ID
        // reaching the flat route, not an escaping implementation detail.
        $actionsHtml = html_entity_decode($cells['actions'], ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString('records/7', $actionsHtml);
        $this->assertStringNotContainsString('manage-family', $actionsHtml);
    }
}
