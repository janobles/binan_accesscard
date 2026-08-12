<?php

namespace Tests\Unit;

use App\Models\Families\FamilyFormOptionsModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * staticOptionLists() needs no DB query, and its lists come out sorted with
 * "Other"/"Others" pinned last, the same way buildViewData() sorts them for
 * getViewData()/getViewDataForEdit(), so a form fed by either method shows the
 * same order.
 */
final class FamilyFormOptionsModelTest extends CIUnitTestCase
{
    public function testStaticOptionListsSortsAlphabeticallyAndPinsOtherLast(): void
    {
        $lists = (new FamilyFormOptionsModel())->staticOptionLists();

        // FamilyProfilingFormV2::civilStatuses() declares "OTHERS" mid-list;
        // sorted, it has to land at the end.
        $this->assertSame('OTHERS', end($lists['civilOptions']));

        // The inline relationship list ends with "OTHER"; sorted, it lands last too.
        $this->assertSame('OTHER', end($lists['relationshipOptions']));
    }

    public function testSexOptionsAreSortedLikeBuildViewDataSortsThem(): void
    {
        $lists = (new FamilyFormOptionsModel())->staticOptionLists();

        // getOptions()'s sexes are declared ['MALE', 'FEMALE']; buildViewData()
        // sorts every caller through sortLabelOptions(), which alphabetizes to
        // FEMALE-then-MALE. staticOptionLists() must match, not fall back to a
        // caller's own unsorted default.
        $this->assertSame(['FEMALE', 'MALE'], $lists['sexOptions']);
    }

    public function testRelationshipAndIncomeListsAreDeclaredOnlyHere(): void
    {
        $lists = (new FamilyFormOptionsModel())->staticOptionLists();

        $this->assertContains('RELATIVE', $lists['relationshipOptions']);
        $this->assertCount(12, $lists['incomeOptions']);
    }
}
