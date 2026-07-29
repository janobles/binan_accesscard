<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the server-side DataTables Manage Records list that was ported from the
 * jade branch: the pinned vendor assets, the five-column view, the role routes,
 * and the controller's whitelisted (date-less) parameter handling.
 *
 * Asset loading was later centralized into app/Helpers/asset_helper.php: the
 * shell renders array_merge(asset_scripts('core'), asset_scripts('admin')) and
 * array_merge(asset_styles('head'), asset_styles('admin')) instead of hand-listing
 * paths. The load-order test therefore inspects the manifest ordering and
 * confirms the one layout still wires the merged helper calls.
 */
final class FamilyDataTableTest extends TestCase
{
    public function testPinnedDataTablesAssetsAreInstalled(): void
    {
        $core = FCPATH . 'assets/datatables/js/dataTables.min.js';
        $adapter = FCPATH . 'assets/datatables/js/dataTables.bootstrap5.min.js';
        $styles = FCPATH . 'assets/datatables/css/dataTables.bootstrap5.min.css';

        $this->assertFileExists($core);
        $this->assertFileExists($adapter);
        $this->assertFileExists($styles);
        $this->assertStringContainsString('DataTables 2.3.8', (string) file_get_contents($core));
        $this->assertStringContainsString('DataTables Bootstrap 5 integration', (string) file_get_contents($adapter));
    }

    public function testFamilyListUsesSixColumnServerSideTableWithoutDateColumn(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/Family/list-body.php');
        $script = (string) file_get_contents(FCPATH . 'assets/js/dashboard/family-datatable.js');

        $this->assertStringContainsString('id="familyRecordsTable"', $view);
        // QR NO. + HEAD NAME + MEMBERS + SECTOR + ADDRESS + ACTIONS.
        $this->assertSame(6, preg_match_all('/<th(?:\s|>)/', $view));
        $this->assertStringContainsString('<th class="fw-semibold small text-center">QR NO.</th>', $view);
        // QR is sortable and the table's default order (manage-records UI
        // spec, 2026-07-12): opens 1 to n, header clicks toggle asc/desc.
        $this->assertStringContainsString("{ data: 'qr', name: 'qr', orderSequence: ['asc', 'desc']", $script);
        $this->assertStringContainsString("className: 'text-center text-nowrap'", $script);
        $this->assertStringNotContainsString('>DATE<', $view);
        $this->assertStringNotContainsString('name="date"', $view);
        $this->assertStringContainsString('serverSide: true', $script);
        $this->assertStringContainsString('searching: true', $script);
        $this->assertStringContainsString('applyCurrentPageQuickSearch', $script);
        $this->assertStringContainsString("request.search.value = ''", $script);
        // In-card layout: quick search left, page length right.
        $this->assertStringContainsString("topStart: 'search'", $script);
        $this->assertStringContainsString("topEnd: 'pageLength'", $script);
        $this->assertStringContainsString("order: [[0, 'asc']]", $script);
        // qr, name, address are sortable; members and sector are not.
        $this->assertSame(3, substr_count($script, "orderSequence: ['asc', 'desc']"));
        $this->assertStringNotContainsString('request.date', $script);
    }

    public function testTheLayoutLoadsDataTablesBeforeInitializer(): void
    {
        require_once APPPATH . 'Helpers/asset_helper.php';

        // The one shell must still render the merged manifest; if a refactor
        // drops these calls the assets never load at all.
        $layout = (string) file_get_contents(APPPATH . 'Views/layout.php');
        $this->assertStringContainsString(
            "array_merge(asset_scripts('core'), asset_scripts('admin'))",
            $layout,
            'layout.php renders merged core + admin scripts'
        );
        $this->assertStringContainsString(
            "array_merge(asset_styles('head'), asset_styles('admin'))",
            $layout,
            'layout.php renders merged head + admin styles'
        );

        $scripts = array_merge(asset_scripts('core'), asset_scripts('admin'));
        $styles  = array_merge(asset_styles('head'), asset_styles('admin'));

        $jqueryPosition          = array_search('assets/jquery/jquery-3.7.1.min.js', $scripts, true);
        $corePosition            = array_search('assets/datatables/js/dataTables.min.js', $scripts, true);
        $adapterPosition         = array_search('assets/datatables/js/dataTables.bootstrap5.min.js', $scripts, true);
        $initializerPosition     = array_search('assets/js/dashboard/family-datatable.js', $scripts, true);
        $dataTablesCssPosition   = array_search('assets/datatables/css/dataTables.bootstrap5.min.css', $styles, true);
        $managerecordCssPosition = array_search('css/managerecord.css', $styles, true);

        $this->assertIsInt($jqueryPosition, 'loads jQuery');
        $this->assertIsInt($corePosition, 'loads DataTables core');
        $this->assertIsInt($adapterPosition, 'loads DataTables bootstrap5 adapter');
        $this->assertIsInt($initializerPosition, 'loads family-datatable.js');
        $this->assertIsInt($dataTablesCssPosition, 'loads DataTables css');
        $this->assertIsInt($managerecordCssPosition, 'loads managerecord.css');

        $this->assertLessThan($corePosition, $jqueryPosition, 'jQuery before DataTables core');
        $this->assertLessThan($adapterPosition, $corePosition, 'core before adapter');
        $this->assertLessThan($initializerPosition, $adapterPosition, 'adapter before initializer');
        $this->assertLessThan($managerecordCssPosition, $dataTablesCssPosition, 'DataTables css before managerecord css');
    }

    public function testThereIsExactlyOneDataTablesEndpoint(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertSame(1, substr_count($routes, "'data', 'Families\\FamilyDataTableController::dataTable'"));
    }

    public function testQrColumnRendersPlainControlNumberText(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Families/FamilyDataTableController.php');
        $presenter  = (string) file_get_contents(APPPATH . 'Libraries/FamilyDataTablePresenter.php');

        // dataTable() batch-loads the heads' control numbers in one query...
        $this->assertStringContainsString('controlsForHeads(', $controller);
        // ...the row exposes a dedicated 'qr' cell built by the presenter's qrCell()...
        $this->assertStringContainsString("'qr' => \$this->qrCell((int) (\$controlNumbers[\$headId] ?? 0))", $presenter);
        // ...which renders escaped plain text that inherits the row typography.
        $this->assertStringContainsString('return esc(ControlNumber::format($controlNo));', $presenter);
        $this->assertStringNotContainsString('badge bg-light text-dark border fw-semibold fs-6 text-nowrap', $presenter);
        $this->assertStringNotContainsString(" . '#'", $presenter);
        $this->assertStringContainsString('-', $presenter);
    }

    public function testEmptyHeadIdsNeverReachWhereIn(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Families/FamilyDataTableController.php');

        // whereIn() on an empty PHP array compiles to invalid SQL ("... IN ()"),
        // which the DB rejects. Both call sites build a whereIn() from a head-ID
        // list computed earlier in the request, so a search or a page past the
        // end - either one leaves that list empty - must skip the query rather
        // than reach whereIn() with nothing in it.
        $wholeDatabaseGuard = strpos($controller, "if (\$headIds !== [])");
        $wholeDatabaseWhereIn = strpos($controller, "whereIn('memberID', \$headIds)->findAll()");
        $this->assertNotFalse($wholeDatabaseGuard, 'missing the whole-database head-lookup guard');
        $this->assertNotFalse($wholeDatabaseWhereIn, 'missing the whole-database head lookup');
        $this->assertLessThan($wholeDatabaseWhereIn, $wholeDatabaseGuard);

        $countGuard = strpos($controller, "if (\$pageHeadIds !== [])");
        $countWhereIn = strpos($controller, "whereIn('headID', \$pageHeadIds)");
        $this->assertNotFalse($countGuard, 'missing the member-count guard');
        $this->assertNotFalse($countWhereIn, 'missing the member-count query');
        $this->assertLessThan($countWhereIn, $countGuard);
    }

    public function testControllerUsesWhitelistedDataTablesParametersWithoutDateFilter(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Families/FamilyDataTableController.php');
        $methodStart = strpos($controller, 'public function dataTable()');
        // dataTable() is followed by its private helper dataTableOrder() in this branch.
        $methodEnd = $methodStart !== false ? strpos($controller, 'private function dataTableOrder', $methodStart) : false;
        $method = $methodStart !== false && $methodEnd !== false
            ? substr($controller, $methodStart, $methodEnd - $methodStart)
            : '';

        $this->assertNotSame('', $method);
        $this->assertStringContainsString('[10, 25, 50, 100]', $method);
        $this->assertStringContainsString("'sectorID'", $method);
        $this->assertStringContainsString("'barangay'", $method);
        $this->assertStringContainsString("getGet('search')", $method);
        $this->assertStringNotContainsString("getGet('date')", $method);

        $orderMethod = $methodEnd !== false ? substr($controller, $methodEnd) : '';
        $this->assertStringContainsString("if (\$requestedDirection === '')", $orderMethod);
        // Default order is QR ascending (manage-records UI spec, 2026-07-12).
        $this->assertStringContainsString("return ['qr', 'asc'];", $orderMethod);

        $searchModel = (string) file_get_contents(APPPATH . 'Models/SearchModel.php');
        $this->assertStringContainsString("case 'newest':", $searchModel);
        $this->assertStringContainsString("orderBy('m.memberID', 'DESC')", $searchModel);
    }

    public function testDatabaseSearchIncludesExactQrNumberMatches(): void
    {
        $filters = (string) file_get_contents(APPPATH . 'Models/Concerns/MemberQueryFilters.php');
        $memberModel = (string) file_get_contents(APPPATH . 'Models/Families/MemberModel.php');
        $searchModel = (string) file_get_contents(APPPATH . 'Models/SearchModel.php');

        $this->assertStringContainsString('ControlNumber::parse(trim($keyword))', $filters);
        $this->assertStringContainsString("->where('control_no', \$controlNo)", $filters);
        $qrGuard = strpos($filters, 'if ($qrHeadIds !== [])');
        $generalSearch = strpos($filters, "\$tokens = preg_split");
        $this->assertNotFalse($qrGuard);
        $this->assertNotFalse($generalSearch);
        $this->assertLessThan($generalSearch, $qrGuard);
        $this->assertStringContainsString("whereIn(\$prefix . 'headID', \$qrHeadIds)", $filters);
        $this->assertStringContainsString('$this->headIdsForQrKeyword($keyword)', $memberModel);
        $this->assertSame(2, substr_count($searchModel, '$this->headIdsForQrKeyword($keyword)'));
    }
}
