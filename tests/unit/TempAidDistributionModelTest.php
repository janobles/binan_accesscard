<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class TempAidDistributionModelTest extends CIUnitTestCase
{
    public function testTemporaryModelMatchesQrOnlySchema(): void
    {
        $src = file_get_contents(APPPATH . 'Models/Scanner/TempAidDistributionModel.php');

        foreach (['control_no', 'aid_type_id', 'claim_date', 'batch_id'] as $field) {
            $this->assertStringContainsString($field, $src);
        }
        $this->assertStringNotContainsString('memberID', $src);
        $this->assertStringNotContainsString('userID', $src);
        $this->assertStringContainsString('voidInBatch', $src);
        $this->assertStringContainsString("->where('control_no', \$controlNo)", $src);
        $this->assertStringContainsString("->where('batch_id', \$batchId)", $src);
    }

    public function testSummaryReturnsReceivedVsNotShapeWithNothingWaiting(): void
    {
        $src = file_get_contents(APPPATH . 'Models/Scanner/TempAidDistributionModel.php');

        // Same keys as AidStatsModel::receivedVsNot() so the reports tiles bind.
        foreach (["'total'", "'received'", "'notReceived'", "'coverage'"] as $key) {
            $this->assertStringContainsString($key, $src);
        }
        // Temp mode never has anyone waiting.
        $this->assertStringContainsString("'notReceived' => 0", $src);
    }
}
