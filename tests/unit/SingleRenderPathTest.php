<?php

namespace Tests\Unit;

use App\Libraries\DashboardPageBuilder;
use App\Libraries\FamilyDataTablePresenter;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * One page, one render path. The three role-parallel render and record-list
 * methods existed only to serve three URL prefixes; with one prefix they collapse.
 */
final class SingleRenderPathTest extends CIUnitTestCase
{
    public function testOneRenderMethodReplacesThree(): void
    {
        $this->assertTrue(method_exists(DashboardPageBuilder::class, 'renderPage'));

        foreach (['renderAdminPage', 'renderEmployeePage', 'renderViewerPage'] as $gone) {
            $this->assertFalse(method_exists(DashboardPageBuilder::class, $gone), $gone);
        }
    }

    public function testOneRecordListBuilderReplacesThree(): void
    {
        $this->assertTrue(method_exists(DashboardPageBuilder::class, 'buildRecordListViewData'));

        foreach (['buildAdminRecordListViewData', 'buildEmployeeRecordListViewData',
                  'buildViewerRecordListViewData'] as $gone) {
            $this->assertFalse(method_exists(DashboardPageBuilder::class, $gone), $gone);
        }
    }

    public function testPresenterNoLongerTakesARouteBase(): void
    {
        $params = (new \ReflectionMethod(FamilyDataTablePresenter::class, '__construct'))
            ->getParameters();

        $this->assertSame(['role'], array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            $params
        ));
    }

    public function testTheRoleParallelControllersAreGone(): void
    {
        $this->assertFalse(class_exists(\App\Controllers\Employee\DashboardController::class));
        $this->assertFalse(class_exists(\App\Controllers\Viewer\DashboardController::class));
    }
}
