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
            // Every returned head must resolve back through qr_control, so its
            // controlNo is a real mapping, never a memberID fallback.
            $this->assertSame(
                $head['memberID'],
                (new \App\Models\Scanner\QrControlModel())->headForControl($head['controlNo']),
                'controlNo ' . $head['controlNo'] . ' must map back to its head via qr_control'
            );
        }
    }

    public function testCountHeadsForCardsMethodExists(): void
    {
        $this->assertTrue(
            method_exists(MemberModel::class, 'countHeadsForCards'),
            'countHeadsForCards() must exist for the preview table total.'
        );
    }

    public function testHeadsForCardsHonorsLimit(): void
    {
        $model = $this->modelOrSkip();

        $all = $model->headsForCards();
        if (count($all) < 2) {
            $this->markTestSkipped('Need at least 2 mapped heads to assert limit.');
        }

        $limited = $model->headsForCards(['limit' => 1]);
        $this->assertCount(1, $limited);
    }

    public function testHeadsForCardsControlRangeStaysWithinBounds(): void
    {
        $model = $this->modelOrSkip();

        $all = $model->headsForCards();
        if ($all === []) {
            $this->markTestSkipped('No mapped heads seeded.');
        }

        $controls = array_column($all, 'controlNo');
        $lo = min($controls);
        $hi = max($controls);

        $ranged = $model->headsForCards(['controlFrom' => $lo, 'controlTo' => $hi]);
        foreach ($ranged as $head) {
            $this->assertGreaterThanOrEqual($lo, $head['controlNo']);
            $this->assertLessThanOrEqual($hi, $head['controlNo']);
        }

        // The count method must agree with the ranged row count when no limit applies.
        $this->assertSame(
            count($ranged),
            $model->countHeadsForCards(['controlFrom' => $lo, 'controlTo' => $hi]),
            'countHeadsForCards() must match the unlimited ranged result size.'
        );
    }

    public function testHeadsForCardsKeywordNarrowsByName(): void
    {
        $model = $this->modelOrSkip();

        $all = $model->headsForCards();
        if ($all === []) {
            $this->markTestSkipped('No mapped heads seeded.');
        }

        // Take a lastname fragment from the first head and confirm it still returns
        // that head, so the keyword filter does not drop an exact-name match.
        $name = $all[0]['fullname'];
        $fragment = substr(trim(explode(',', $name)[0]), 0, 3);
        if ($fragment === '') {
            $this->markTestSkipped('First head has no usable name fragment.');
        }

        $hit = $model->headsForCards(['keyword' => $fragment]);
        $this->assertNotSame([], $hit, 'Keyword matching a real name must return rows.');
    }
}
