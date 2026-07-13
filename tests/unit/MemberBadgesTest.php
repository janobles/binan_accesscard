<?php

namespace Tests\Unit;

use App\Models\Families\MemberModel;
use CodeIgniter\Test\CIUnitTestCase;

final class MemberBadgesTest extends CIUnitTestCase
{
    public function testReferenceBadgesExistsAndIsSafeWithoutDb(): void
    {
        $model = new MemberModel();
        // Empty input never touches the DB and returns an empty map.
        $this->assertSame([], $model->referenceBadges([]));
    }

    public function testScanControllerAttachesBadges(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
        $this->assertStringContainsString('referenceBadges(', $src);
        $this->assertStringContainsString("'badges'", $src);
    }
}
