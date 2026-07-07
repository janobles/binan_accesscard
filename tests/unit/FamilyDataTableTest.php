<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the server-side DataTables Manage Records list that was ported from the
 * jade branch: the pinned vendor assets, the five-column view, the role routes,
 * and the controller's whitelisted (date-less) parameter handling.
 *
 * Asset loading was later centralized into app/Helpers/asset_helper.php: each
 * layout renders array_merge(asset_scripts('core'), asset_scripts($role)) and
 * array_merge(asset_styles('head'), asset_styles($role)) instead of hand-listing
 * paths. The load-order test therefore inspects the manifest ordering and
 * confirms each layout still wires the merged helper calls.
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
        $view = (string) file_get_contents(APPPATH . 'Views/Family/list.php');
        $script = (string) file_get_contents(FCPATH . 'assets/js/dashboard/family-datatable.js');

        $this->assertStringContainsString('id="familyRecordsTable"', $view);
        // QR NO. + HEAD/MEMBER NAME + SECTOR + ADDRESS + BIRTHDAY + ACTIONS.
        $this->assertSame(6, preg_match_all('/<th(?:\s|>)/', $view));
        $this->assertStringContainsString('>QR NO.<', $view);
        $this->assertStringContainsString("{ data: 'qr', name: 'qr', orderable: false", $script);
        $this->assertStringNotContainsString('>DATE<', $view);
        $this->assertStringNotContainsString('name="date"', $view);
        $this->assertStringContainsString('serverSide: true', $script);
        $this->assertStringContainsString('searching: true', $script);
        $this->assertStringContainsString('applyCurrentPageQuickSearch', $script);
        $this->assertStringContainsString("request.search.value = ''", $script);
        $this->assertStringContainsString("topStart: 'pageLength'", $script);
        $this->assertStringContainsString("topEnd: 'search'", $script);
        // Import feature: no initial column sort so the server's newest-first
        // order (a just-imported family) shows at the top; header clicks still sort.
        $this->assertStringContainsString('order: []', $script);
        $this->assertStringNotContainsString('request.date', $script);
    }

    public function testRoleLayoutsLoadDataTablesBeforeInitializer(): void
    {
        require_once APPPATH . 'Helpers/asset_helper.php';

        $layoutFiles = [
            'admin'    => 'Admin/layout.php',
            'employee' => 'Employee/layout.php',
            'viewer'   => 'Viewer/layout.php',
        ];

        foreach ($layoutFiles as $role => $layoutPath) {
            // The layout must still render the merged manifest for this role;
            // if a refactor drops these calls the assets never load at all.
            $layout = (string) file_get_contents(APPPATH . 'Views/' . $layoutPath);
            $this->assertStringContainsString(
                "array_merge(asset_scripts('core'), asset_scripts('" . $role . "'))",
                $layout,
                $layoutPath . ' renders merged core + role scripts'
            );
            $this->assertStringContainsString(
                "array_merge(asset_styles('head'), asset_styles('" . $role . "'))",
                $layout,
                $layoutPath . ' renders merged head + role styles'
            );

            $scripts = array_merge(asset_scripts('core'), asset_scripts($role));
            $styles  = array_merge(asset_styles('head'), asset_styles($role));

            $jqueryPosition          = array_search('assets/jquery/jquery-3.7.1.min.js', $scripts, true);
            $corePosition            = array_search('assets/datatables/js/dataTables.min.js', $scripts, true);
            $adapterPosition         = array_search('assets/datatables/js/dataTables.bootstrap5.min.js', $scripts, true);
            $initializerPosition     = array_search('assets/js/dashboard/family-datatable.js', $scripts, true);
            $dataTablesCssPosition   = array_search('assets/datatables/css/dataTables.bootstrap5.min.css', $styles, true);
            $managerecordCssPosition = array_search('css/managerecord.css', $styles, true);

            $this->assertIsInt($jqueryPosition, $role . ' loads jQuery');
            $this->assertIsInt($corePosition, $role . ' loads DataTables core');
            $this->assertIsInt($adapterPosition, $role . ' loads DataTables bootstrap5 adapter');
            $this->assertIsInt($initializerPosition, $role . ' loads family-datatable.js');
            $this->assertIsInt($dataTablesCssPosition, $role . ' loads DataTables css');
            $this->assertIsInt($managerecordCssPosition, $role . ' loads managerecord.css');

            $this->assertLessThan($corePosition, $jqueryPosition, $role . ': jQuery before DataTables core');
            $this->assertLessThan($adapterPosition, $corePosition, $role . ': core before adapter');
            $this->assertLessThan($initializerPosition, $adapterPosition, $role . ': adapter before initializer');
            $this->assertLessThan($managerecordCssPosition, $dataTablesCssPosition, $role . ': DataTables css before managerecord css');
        }
    }

    public function testEveryRoleHasADataTablesEndpoint(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertSame(3, substr_count($routes, "'data', 'Families\\FamilyDataTableController::dataTable'"));
    }

    public function testQrColumnRendersControlNumberBadge(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Families/FamilyDataTableController.php');
        $presenter  = (string) file_get_contents(APPPATH . 'Libraries/FamilyDataTablePresenter.php');

        // dataTable() batch-loads the heads' control numbers in one query...
        $this->assertStringContainsString('controlsForHeads(', $controller);
        // ...the row exposes a dedicated 'qr' cell built by the presenter's qrCell()...
        $this->assertStringContainsString("'qr' => \$this->qrCell(\$controlNo)", $presenter);
        // ...which renders a bordered QR badge with the padded number, dash when unmapped.
        $this->assertStringContainsString('bi bi-qr-code', $presenter);
        $this->assertStringContainsString('ControlNumber::format($controlNo)', $presenter);
        $this->assertStringContainsString('&mdash;', $presenter);
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
    }
}
