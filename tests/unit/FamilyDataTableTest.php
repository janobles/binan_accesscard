<?php

use PHPUnit\Framework\TestCase;

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
        $helper = (string) file_get_contents(APPPATH . 'Helpers/asset_helper.php');
        $jqueryPosition = strpos($helper, 'assets/jquery/jquery-3.7.1.min.js');
        $corePosition = strpos($helper, 'assets/datatables/js/dataTables.min.js');
        $adapterPosition = strpos($helper, 'assets/datatables/js/dataTables.bootstrap5.min.js');
        $initializerPosition = strpos($helper, 'assets/js/dashboard/family-datatable.js');
        $dataTablesCssPosition = strpos($helper, 'assets/datatables/css/dataTables.bootstrap5.min.css');
        $customCssPosition = strpos($helper, 'css/managerecord.css');

        $this->assertIsInt($jqueryPosition);
        $this->assertIsInt($corePosition);
        $this->assertIsInt($adapterPosition);
        $this->assertIsInt($initializerPosition);
        $this->assertIsInt($dataTablesCssPosition);
        $this->assertIsInt($customCssPosition);
        $this->assertLessThan($corePosition, $jqueryPosition);
        $this->assertLessThan($adapterPosition, $corePosition);
        $this->assertLessThan($initializerPosition, $adapterPosition);
        $this->assertLessThan($customCssPosition, $dataTablesCssPosition);

        foreach (['Admin/layout.php', 'Employee/layout.php', 'Viewer/layout.php'] as $layoutPath) {
            $layout = (string) file_get_contents(APPPATH . 'Views/' . $layoutPath);

            $this->assertStringContainsString("helper('asset')", $layout);
            $this->assertStringContainsString("asset_tags('dashboard-core-css')", $layout);
            $this->assertStringContainsString("asset_tags('dashboard-vendor-js')", $layout);
            $this->assertStringContainsString("asset_script_tag('assets/js/session-timeout.js'", $layout);
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
        $methodEnd = strpos($controller, 'public function viewFamily', $methodStart ?: 0);
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
