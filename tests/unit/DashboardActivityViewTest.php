<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The Encoder activity panel renders through components/card plus a body
 * partial, the house pattern for every table on the app. It replaced a
 * components/data_table call that took its rows as pre-rendered HTML, so these
 * cover what that swap could have broken: the rows still appear, the empty
 * state still appears, and the values are still escaped now that the partial
 * rather than the caller owns the escaping.
 */
final class DashboardActivityViewTest extends CIUnitTestCase
{
    private function render(array $audits): string
    {
        return view('Pages/dashboard-activity-body', [
            'myAudits'          => $audits,
            'formatAuditMember' => static fn (array $row): string => (string) ($row['member'] ?? ''),
        ]);
    }

    public function testRendersARowPerAudit(): void
    {
        $html = $this->render([
            ['user_action' => 'CREATE', 'member' => 'DELA CRUZ, JUAN', 'description' => 'Added a family'],
            ['user_action' => 'UPDATE', 'member' => 'SANTOS, MARIA', 'description' => 'Edited a member'],
        ]);

        $this->assertStringContainsString('DELA CRUZ, JUAN', $html);
        $this->assertStringContainsString('SANTOS, MARIA', $html);
        $this->assertSame(2, substr_count($html, '<tr>') - 1, 'one header row plus one row per audit');
    }

    public function testShowsTheEmptyStateWithNoAudits(): void
    {
        $this->assertStringContainsString('No activity yet.', $this->render([]));
    }

    public function testEscapesAuditValues(): void
    {
        $html = $this->render([
            ['user_action' => '<b>X</b>', 'member' => 'A', 'description' => '<script>alert(1)</script>'],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /** Wide content scrolls inside its own wrapper, never the page body. */
    public function testTableIsWrappedForHorizontalScroll(): void
    {
        $this->assertStringContainsString('table-responsive', $this->render([]));
    }
}
