<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\DumpSchema;

/**
 * Renders the dashboard for real and reads the HTML back.
 *
 * The rest of the dashboard suite greps source files, which cannot see whether
 * a link a view builds actually carries the query params it needs. These cases
 * fetch the page through the router with a session and assert on the response
 * body: that the Distribution pane's own links keep the reader inside the pane,
 * and that a role without distribution access still lands on a dashboard with
 * something on it.
 *
 * The schema comes from the SQL dump (Tests\Support\Database\DumpSchema); rows
 * are the few this file asserts on.
 */
final class DashboardPaneUrlFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const BATCH_ID = 7;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clean();
        // The renderer is a singleton and CI4 keeps view data on it between
        // render() calls, so one case's tab strip would otherwise seed the
        // next case's.
        \Config\Services::resetSingle('renderer');

        $db = db_connect();
        DumpSchema::create($db);

        $db->table('users')->insertBatch([
            ['userID' => 1, 'username' => 'boss', 'account_level' => 'administrator', 'isactive' => 'Enable', 'password' => 'x'],
            ['userID' => 2, 'username' => 'keyer', 'account_level' => 'encoder', 'isactive' => 'Enable', 'password' => 'x'],
            ['userID' => 3, 'username' => 'looker', 'account_level' => 'viewer', 'isactive' => 'Enable', 'password' => 'x'],
            ['userID' => 4, 'username' => 'dev', 'account_level' => 'developer', 'isactive' => 'Enable', 'password' => 'x'],
        ]);
        $db->table('subsidy')->insert(['subsidy_type_id' => 1, 'name' => 'Rice']);
        $db->table('barangay')->insert(['barangayID' => 1, 'name' => 'Canlalay']);
        $db->table('distribution_batch')->insert([
            'batch_id'        => self::BATCH_ID,
            'name'            => 'August rice',
            'subsidy_type_id' => 1,
            'started_at'      => '2026-08-01 08:00:00',
            'closed_at'       => null,
            'eligible_count'  => 12,
        ]);

        // Twelve eligible heads, none served, so a 10-per-page Remaining tab
        // has a second page and therefore real pagination links to inspect.
        $members = [];
        $roster  = [];
        for ($id = 1; $id <= 12; $id++) {
            $members[] = [
                'memberID' => $id, 'lastname' => 'Head' . $id, 'firstname' => 'Test',
                'middlename' => '', 'headID' => $id, 'barangayID' => 1,
                'contactnumber' => '', 'dt_deleted' => null,
            ];
            $roster[] = ['batch_id' => self::BATCH_ID, 'headID' => $id];
        }
        $db->table('member')->insertBatch($members);
        $db->table('batch_eligibility')->insertBatch($roster);
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        cache()->clean();

        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function session(string $role, int $userId, string $username): array
    {
        return ['is_logged_in' => true, 'role' => $role, 'user_id' => $userId, 'username' => $username];
    }

    /**
     * Every href in $html that points at the dashboard and carries $needle,
     * with entity-encoded ampersands decoded back so a caller can grep params.
     *
     * @return list<string>
     */
    private function dashboardLinks(string $html, string $needle): array
    {
        preg_match_all('/href="([^"]*dashboard\?[^"]*)"/', $html, $matches);

        $links = array_map(static fn (string $href): string => html_entity_decode($href), $matches[1]);

        return array_values(array_filter($links, static fn (string $href): bool => str_contains($href, $needle)));
    }

    public function testEveryCardRendersTogetherOnOnePaneLoad(): void
    {
        $body = $this->withSession($this->session('administrator', 1, 'boss'))
            ->get('dashboard?view=distribution&batch=' . self::BATCH_ID)
            ->getBody();

        // The Distribution pane rendered, and the Overview pane did not.
        $this->assertStringNotContainsString('Families profiled', $body);

        // All four subjects at once, which is the point of the restructure:
        // reading two of them no longer costs the first.
        $this->assertStringContainsString('id="activityCard"', $body);
        $this->assertStringContainsString('id="barangayCard"', $body);
        $this->assertStringContainsString('id="stationsTable"', $body);
        $this->assertStringContainsString('id="remainingCard"', $body);

        // No sub-tab strip carrying ?tab= on this pane any more.
        $this->assertSame([], $this->dashboardLinks($body, 'tab='));

        // Batch picker: submitting it must not drop the pane.
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="view" value="distribution"\s*\/?>/',
            $body
        );
        $this->assertStringNotContainsString('<input type="hidden" name="tab"', $body);
    }

    public function testRemainingPaginationAndFormsStayInsideTheDistributionPane(): void
    {
        $body = $this->withSession($this->session('administrator', 1, 'boss'))
            ->get('dashboard?view=distribution&batch=' . self::BATCH_ID . '&per_page=10')
            ->getBody();

        $pageLinks = $this->dashboardLinks($body, 'page=');
        $this->assertNotSame([], $pageLinks, 'Expected paginated Remaining links.');
        foreach ($pageLinks as $href) {
            $this->assertStringContainsString('view=distribution', $href, $href);
            $this->assertStringContainsString('batch=' . self::BATCH_ID, $href, $href);
        }

        // Three forms carry the pane back: the batch picker, the Remaining
        // search box and its page-size selector.
        $this->assertGreaterThanOrEqual(
            3,
            preg_match_all('/<input type="hidden" name="view" value="distribution"\s*\/?>/', $body),
            'The batch picker and both Remaining forms must carry the pane.'
        );
    }

    public function testOverviewPaneKeepsThePickedBatchOnTheOuterTabs(): void
    {
        $body = $this->withSession($this->session('administrator', 1, 'boss'))
            ->get('dashboard?view=overview&batch=' . self::BATCH_ID)
            ->getBody();

        // Only the outer strip, not the Distributions table's own batch links.
        $this->assertSame(1, preg_match('#<ul class="nav nav-pills segmented-tabs">.*?</ul>#s', $body, $strip));
        $outer = $this->dashboardLinks($strip[0], 'view=');
        $this->assertCount(2, $outer);
        foreach ($outer as $href) {
            $this->assertStringContainsString('batch=' . self::BATCH_ID, $href, $href);
        }
    }

    public function testEncoderAndViewerBothGetTheRealDashboard(): void
    {
        foreach ([['encoder', 2, 'keyer'], ['viewer', 3, 'looker']] as [$role, $userId, $username]) {
            $body = $this->withSession($this->session($role, $userId, $username))->get('dashboard')->getBody();

            // The outer strip, both its tabs, and the Overview pane it lands on.
            $this->assertMatchesRegularExpression('#<ul class="nav nav-pills segmented-tabs">#', $body, $role);
            $this->assertStringContainsString('?view=distribution', $body, $role);
            $this->assertStringContainsString('Program to date', $body, $role);
            $this->assertStringContainsString('Families profiled', $body, $role);
            $this->assertStringContainsString('Access cards issued', $body, $role);
            $this->assertStringContainsString('never served', $body, $role);
            $this->assertStringContainsString('August rice', $body, $role);
        }
    }

    public function testEncoderReachesTheDistributionPaneToo(): void
    {
        $body = $this->withSession($this->session('encoder', 2, 'keyer'))
            ->get('dashboard?view=distribution&batch=' . self::BATCH_ID)
            ->getBody();

        $this->assertStringContainsString('id="stationsTable"', $body);
        $this->assertStringContainsString('Eligible', $body);
    }

    /**
     * With no ?batch= in the URL the Distribution pane still resolves a batch
     * (BatchScope: the open one), so the outer tab strip has to carry that id
     * onto the Overview pane. Carrying 0 would describe a page nobody is
     * looking at, and the reader would lose their batch crossing the strip.
     */
    public function testTabStripCarriesTheResolvedBatchWithoutAnExplicitQuery(): void
    {
        $body = $this->withSession($this->session('administrator', 1, 'boss'))
            ->get('dashboard?view=distribution')
            ->getBody();

        // Read off the strip's own links rather than the whole body, where any
        // batch-scoped href on the page would have satisfied the assertion.
        $outerTabs = $this->dashboardLinks($body, 'view=');
        $this->assertNotSame([], $outerTabs);

        foreach ($outerTabs as $href) {
            $this->assertStringContainsString('batch=' . self::BATCH_ID, $href);
        }
    }

    /** An id matching no batch is not a selection, and must not travel. */
    public function testTabStripDropsAnUnknownBatchRatherThanCarryingIt(): void
    {
        $body = $this->withSession($this->session('administrator', 1, 'boss'))
            ->get('dashboard?view=distribution&batch=99999')
            ->getBody();

        foreach ($this->dashboardLinks($body, 'view=') as $href) {
            $this->assertStringNotContainsString('batch=99999', $href);
        }
    }

    /**
     * The station modal reads scanner/stats, which answers Scanner, Admin and
     * Developer only. An Encoder sees the table but must get no control that
     * would 403, and no modal markup to go with it.
     */
    public function testEncoderGetsStationsWithoutADrillIn(): void
    {
        $body = $this->withSession($this->session('encoder', 2, 'keyer'))
            ->get('dashboard?view=distribution&batch=' . self::BATCH_ID)
            ->getBody();

        $this->assertStringContainsString('data-can-drill-in="0"', $body);
        $this->assertStringNotContainsString('data-scanner-id=', $body);
        $this->assertStringNotContainsString('id="stationModal"', $body);
    }

    /** The same table, for a role that may open a station. */
    public function testDeveloperGetsTheStationModal(): void
    {
        $body = $this->withSession($this->session('developer', 4, 'dev'))
            ->get('dashboard?view=distribution&batch=' . self::BATCH_ID)
            ->getBody();

        $this->assertStringContainsString('data-can-drill-in="1"', $body);
        $this->assertStringContainsString('id="stationModal"', $body);
        $this->assertStringNotContainsString('scanner/performance', $body);
    }
}
