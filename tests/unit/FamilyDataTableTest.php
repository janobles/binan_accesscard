<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the server-side DataTables Manage Records list that was ported from the
 * jade branch: the pinned vendor assets, the five-column view, the role routes,
 * and the controller's whitelisted (date-less) parameter handling.
 *
 * Adapted from jade for this branch's asset loading: layouts here enumerate
 * asset_url() paths directly (no asset-group helper), so the load-order test
 * inspects the layout files rather than an asset registry.
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

    public function testFamilyListUsesFiveColumnServerSideTableWithoutDateColumn(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/Family/list.php');
        $script = (string) file_get_contents(FCPATH . 'assets/js/dashboard/family-datatable.js');

        $this->assertStringContainsString('id="familyRecordsTable"', $view);
        $this->assertSame(5, preg_match_all('/<th(?:\s|>)/', $view));
        $this->assertStringNotContainsString('>DATE<', $view);
        $this->assertStringNotContainsString('name="date"', $view);
        $this->assertStringContainsString('serverSide: true', $script);
        $this->assertStringContainsString('searching: true', $script);
        $this->assertStringContainsString('applyCurrentPageQuickSearch', $script);
        $this->assertStringContainsString("request.search.value = ''", $script);
        $this->assertStringContainsString("topStart: 'pageLength'", $script);
        $this->assertStringContainsString("topEnd: 'search'", $script);
        $this->assertStringContainsString("order: [[0, 'asc']]", $script);
        $this->assertStringNotContainsString('request.date', $script);
    }

    public function testRoleLayoutsLoadDataTablesBeforeInitializer(): void
    {
        foreach (['Admin/layout.php', 'Employee/layout.php', 'Viewer/layout.php'] as $layoutPath) {
            $layout = (string) file_get_contents(APPPATH . 'Views/' . $layoutPath);

            $jqueryPosition = strpos($layout, 'assets/jquery/jquery-3.7.1.min.js');
            $corePosition = strpos($layout, 'assets/datatables/js/dataTables.min.js');
            $adapterPosition = strpos($layout, 'assets/datatables/js/dataTables.bootstrap5.min.js');
            $initializerPosition = strpos($layout, 'assets/js/dashboard/family-datatable.js');
            $dataTablesCssPosition = strpos($layout, 'assets/datatables/css/dataTables.bootstrap5.min.css');
            $managerecordCssPosition = strpos($layout, 'css/managerecord.css');

            $this->assertIsInt($jqueryPosition, $layoutPath . ' loads jQuery');
            $this->assertIsInt($corePosition, $layoutPath . ' loads DataTables core');
            $this->assertIsInt($adapterPosition, $layoutPath . ' loads DataTables bootstrap5 adapter');
            $this->assertIsInt($initializerPosition, $layoutPath . ' loads family-datatable.js');
            $this->assertIsInt($dataTablesCssPosition, $layoutPath . ' loads DataTables css');
            $this->assertIsInt($managerecordCssPosition, $layoutPath . ' loads managerecord.css');

            $this->assertLessThan($corePosition, $jqueryPosition, $layoutPath . ': jQuery before DataTables core');
            $this->assertLessThan($adapterPosition, $corePosition, $layoutPath . ': core before adapter');
            $this->assertLessThan($initializerPosition, $adapterPosition, $layoutPath . ': adapter before initializer');
            $this->assertLessThan($managerecordCssPosition, $dataTablesCssPosition, $layoutPath . ': DataTables css before managerecord css');
        }
    }

    public function testEveryRoleHasADataTablesEndpoint(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertSame(3, substr_count($routes, "'data', 'Families\\FamilyController::dataTable'"));
    }

    public function testControllerUsesWhitelistedDataTablesParametersWithoutDateFilter(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Families/FamilyController.php');
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
