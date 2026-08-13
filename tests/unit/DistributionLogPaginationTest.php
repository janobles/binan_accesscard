<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyDistributionModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * The Distribution Log reads one page at a time.
 *
 * The page used to fetch every distribution ever logged and filter it in the
 * browser, which is a whole-table render against the 100k-family target. These
 * cases pin the bound and the keyword filter to the query rather than the DOM.
 */
final class DistributionLogPaginationTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DumpSchema::create(db_connect());
        $this->seedThreeClaims();
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    /** Three heads, one claim each, on consecutive days so the order is fixed. */
    private function seedThreeClaims(): void
    {
        $db = db_connect();

        ReferentialFixture::claimParents($db, [1, 2, 3], 100);
        $db->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Rice Q1', 'subsidy_type_id' => 1, 'eligible_count' => 3,
        ]);

        foreach ([1, 2, 3] as $i) {
            $db->table('subsidy_distribution')->insert([
                'distribution_id' => $i,
                'control_no'      => 100 + $i,
                'memberID'        => $i,
                'subsidy_type_id' => 1,
                'batch_id'        => 1,
                'userID'          => null,
                'claim_date'      => sprintf('2026-08-%02d 09:00:00', 10 + $i),
            ]);
        }
    }

    public function testCountReportsEveryClaim(): void
    {
        $this->assertSame(3, (new SubsidyDistributionModel())->countDistributions(''));
    }

    public function testAPageIsBoundedAndNewestFirst(): void
    {
        $rows = (new SubsidyDistributionModel())->distributionsPage('', 2, 0);

        $this->assertCount(2, $rows);
        $this->assertSame(103, (int) $rows[0]['control_no'], 'newest claim first');
        $this->assertSame(102, (int) $rows[1]['control_no']);
    }

    public function testTheOffsetReachesTheSecondPage(): void
    {
        $rows = (new SubsidyDistributionModel())->distributionsPage('', 2, 2);

        $this->assertCount(1, $rows);
        $this->assertSame(101, (int) $rows[0]['control_no']);
    }

    public function testAKeywordNarrowsBothThePageAndTheCount(): void
    {
        $model = new SubsidyDistributionModel();

        $this->assertSame(1, $model->countDistributions('HEAD2'));
        $this->assertCount(1, $model->distributionsPage('HEAD2', 25, 0));
    }

    public function testAKeywordMatchingNothingReturnsNothing(): void
    {
        $model = new SubsidyDistributionModel();

        $this->assertSame(0, $model->countDistributions('NOBODY'));
        $this->assertSame([], $model->distributionsPage('NOBODY', 25, 0));
    }
}
