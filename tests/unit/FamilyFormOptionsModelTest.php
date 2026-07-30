<?php

namespace Tests\Unit;

use App\Models\Families\FamilyFormOptionsModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * staticOptionLists() is the entry point Family/_fields.php uses directly
 * (Task 8 fix round 1, finding 3), so it needs no DB query and its lists must
 * come out sorted and "Other"/"Others" pinned last, the same way
 * buildViewData() sorts them for getViewData()/getViewDataForEdit().
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

    public function testStaticOptionListsDoesNotDuplicateOrDiverge(): void
    {
        $lists = (new FamilyFormOptionsModel())->staticOptionLists();

        // Finding 3's diverged pair: the model is the only place the
        // relationship and income lists are declared now, so a value present in
        // one has to be present in both - nowhere left for them to drift.
        $this->assertContains('Relative', $lists['relationshipOptions']);
        $this->assertCount(12, $lists['incomeOptions']);
    }
}
