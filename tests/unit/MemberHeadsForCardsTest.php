<?php

namespace Tests\Unit;

use App\Models\Families\MemberModel;
use CodeIgniter\Test\CIUnitTestCase;

final class MemberHeadsForCardsTest extends CIUnitTestCase
{
    private function modelOrSkip(): MemberModel
    {
        $model = new MemberModel();
        if (! $model->hasTable()) {
            $this->markTestSkipped('member table not available in this environment.');
        }

        return $model;
    }

    public function testHeadsForCardsReturnsExpectedShape(): void
    {
        $model = $this->modelOrSkip();

        $heads = $model->headsForCards();

        $this->assertIsArray($heads);
        if ($heads === []) {
            $this->markTestSkipped('No head records seeded to assert shape against.');
        }

        $first = $heads[0];
        $this->assertArrayHasKey('memberID', $first);
        $this->assertArrayHasKey('fullname', $first);
        $this->assertArrayHasKey('barangay', $first);
        $this->assertIsInt($first['memberID']);
    }

    public function testFindHeadRejectsNonHead(): void
    {
        $model = $this->modelOrSkip();

        // memberID 0 can never be a head (ids are positive).
        $this->assertNull($model->findHead(0));
    }

    public function testHeadsForCardsControlNoEqualsMappedControl(): void
    {
        $model = $this->modelOrSkip();
        $heads = $model->headsForCards();
        if ($heads === []) {
            $this->markTestSkipped('No mapped heads seeded to assert against.');
        }

        foreach ($heads as $head) {
            $this->assertArrayHasKey('controlNo', $head);
            $this->assertIsInt($head['controlNo']);
            // Every returned head must resolve back through qr_control — i.e. its
            // controlNo is a real mapping, never a memberID fallback.
            $this->assertSame(
                $head['memberID'],
                (new \App\Models\Scanner\QrControlModel())->headForControl($head['controlNo']),
                'controlNo ' . $head['controlNo'] . ' must map back to its head via qr_control'
            );
        }
    }
}
