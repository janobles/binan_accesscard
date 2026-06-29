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
}
