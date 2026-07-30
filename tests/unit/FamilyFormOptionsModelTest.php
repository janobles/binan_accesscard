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

        // FamilyProfilingFormV2::civilStatuses() declares "Others" mid-list;
        // sorted, it has to land at the end.
        $this->assertSame('Others', end($lists['civilOptions']));

        // FamilyProfilingFormV2::barangays() declares "Santo Tomas (Calabuso)"
        // before "Canlalay"; alphabetized, Canlalay comes first.
        $barangayIndex = array_flip($lists['barangayOptions']);
        $this->assertLessThan(
            $barangayIndex['Santo Tomas (Calabuso)'],
            $barangayIndex['Canlalay'],
            'Barangay options must be alphabetized, not left in declaration order.'
        );

        // The inline relationship list ends with "Other"; sorted, it lands last too.
        $this->assertSame('Other', end($lists['relationshipOptions']));
    }

    public function testSexOptionsAreSortedLikeBuildViewDataSortsThem(): void
    {
        $lists = (new FamilyFormOptionsModel())->staticOptionLists();

        // getOptions()'s sexes are declared ['Male', 'Female']; buildViewData()
        // sorts every caller through sortLabelOptions(), which alphabetizes to
        // Female-then-Male. staticOptionLists() must match, not fall back to a
        // caller's own unsorted default.
        $this->assertSame(['Female', 'Male'], $lists['sexOptions']);
    }

    public function testRelationshipAndIncomeListsAreDeclaredOnlyHere(): void
    {
        $lists = (new FamilyFormOptionsModel())->staticOptionLists();

        $this->assertContains('Relative', $lists['relationshipOptions']);
        $this->assertCount(12, $lists['incomeOptions']);
    }
}
