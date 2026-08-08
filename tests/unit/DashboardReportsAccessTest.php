<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Navigation;

/**
 * The dashboard's Distribution pane is open to every staff role, but the two
 * endpoints it reads from live under the Distribution page's route group. An
 * Encoder therefore rendered a pane whose live poll 404'd and whose Download
 * Report link bounced, with nothing on screen saying so.
 *
 * These pin the split that fixes it: the read-only stats and PDF endpoints hang
 * off their own manifest key open to all staff, while the batch writes stay on
 * the Distribution page's key.
 */
final class DashboardReportsAccessTest extends CIUnitTestCase
{
    private const ALL_STAFF = ['Developer', 'Admin', 'Encoder', 'Viewer'];

    /** Every role that renders the Distribution pane must reach the data behind it. */
    public function testDashboardReportsKeyIsOpenToEveryRoleThatSeesTheDashboard(): void
    {
        $dashboardRoles = Navigation::pageRoles('dashboard');
        $reportsRoles   = Navigation::pageRoles('dashboard-reports');

        $this->assertSame(self::ALL_STAFF, $dashboardRoles, 'the dashboard is the landing page for all staff');

        foreach ($dashboardRoles as $role) {
            $this->assertContains(
                $role,
                $reportsRoles,
                "$role renders the Distribution pane, so it must reach distribution/reports/*"
            );
        }
    }

    /**
     * The route declaration is the other half: a permissive manifest entry does
     * nothing while the routes still declare the Distribution page's key.
     */
    public function testStatsAndPdfRoutesDeclareTheDashboardReportsKey(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertMatchesRegularExpression(
            "/\\\$routes->group\('distribution', \['filter' => 'roleNav:dashboard-reports'\].*?"
            . "\\\$routes->get\('reports\/stats'.*?\\\$routes->get\('reports\/pdf'/s",
            $routes,
            'reports/stats and reports/pdf must sit in the roleNav:dashboard-reports group'
        );
    }

    /**
     * The manifest key is the only gate. ReportsController used to re-check for
     * Admin/Developer inside both actions, which would keep 403ing an Encoder no
     * matter what the route declared.
     */
    public function testReportsControllerDoesNotReGateOnRole(): void
    {
        $source = (string) file_get_contents(APPPATH . 'Controllers/Admin/ReportsController.php');

        $this->assertStringNotContainsString('requireRole', $source);
        $this->assertStringNotContainsString('RoleAccess', $source);
    }

    /**
     * Opening the reads must not open the writes. Closing a batch or voiding a
     * distribution stays on the Distribution page's own key.
     */
    public function testBatchWritesStayOnTheDistributionPageKey(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertMatchesRegularExpression(
            "/\\\$routes->group\('distribution', \['filter' => 'roleNav:distribution'\].*?"
            . "\\\$routes->post\('batches\/close\/\(:num\)'"
            . ".*?\\\$routes->post\('void\/\(:num\)'/s",
            $routes,
            'batch close/void must stay in the roleNav:distribution group'
        );

        $this->assertNotContains(
            'Encoder',
            Navigation::pageRoles('distribution'),
            'opening the reads must not hand an Encoder the Distribution page'
        );
    }
}
