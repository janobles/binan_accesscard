<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

/*
 * Authentication
 */
$routes->get('/', 'Auth\AuthController::index');
$routes->match(['GET', 'POST'], 'login', 'Auth\AuthController::login');
$routes->post('login/confirm', 'Auth\AuthController::confirmLogin');
$routes->get('logout', 'Auth\AuthController::logout');
$routes->get('session/keep-alive', 'Auth\AuthController::keepAlive');

/*
 * Admin workspace
 */
$routes->group('admin', static function (RouteCollection $routes): void {
    $routes->get('', 'Admin\DashboardController::index');
    $routes->get('dashboard', 'Admin\DashboardController::dashboard');
    $routes->get('accounts', 'Admin\DashboardController::accounts');
    $routes->get('family-entry', 'Admin\DashboardController::familyEntry');
    $routes->get('manage-records', 'Admin\DashboardController::manageRecords');
    $routes->get('audit-trails', 'Admin\DashboardController::auditTrails');
    $routes->get('sectors', 'Admin\DashboardController::sectors');
    $routes->get('services', 'Admin\DashboardController::services');
    $routes->get('categories', 'Admin\DashboardController::categories');
    $routes->get('manage-members', 'Admin\DashboardController::manageMembers');
    // Admin-only: disable/enable employee accounts from Account Management.
    $routes->post('accounts/disable', 'Accounts\AccountController::disableEmployee');
    $routes->post('accounts/enable', 'Accounts\AccountController::enableEmployee');

    $routes->get('manage-families', 'Admin\DashboardController::manageRecords');
    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('', 'Admin\DashboardController::manageRecords');
        $routes->get('list', 'Families\FamilyController::listFamilies');
        $routes->get('data', 'Families\FamilyDataTableController::dataTable');
        $routes->get('template', 'Families\FamilyImportController::downloadTemplate');
        $routes->get('import', 'Families\FamilyImportController::importForm');
        $routes->post('import', 'Families\FamilyImportController::import');
        $routes->get('import/status/(:num)', 'Families\FamilyImportController::importStatus/$1');
        $routes->get('import/review/(:num)', 'Families\FamilyImportController::reviewPage/$1');
        $routes->post('import/review/(:num)/commit', 'Families\FamilyImportController::reviewCommit/$1');
        $routes->post('import/review/(:num)/cancel', 'Families\FamilyImportController::reviewCancel/$1');
        $routes->get('import/review/(:num)/family', 'Families\FamilyImportController::reviewFamilyModal/$1');
        $routes->post('import/review/(:num)/family/save', 'Families\FamilyImportController::reviewFamilySave/$1');
        $routes->post('import/review/(:num)/family/remove', 'Families\FamilyImportController::reviewFamilyRemove/$1');
        $routes->get('create', 'Families\FamilyController::createFamily');
        $routes->get('view/(:num)', 'Families\FamilyController::viewFamily/$1');
        $routes->get('edit/(:num)', 'Families\FamilyController::editFamily/$1');
        $routes->post('update/(:num)', 'Families\FamilyController::update/$1');
        $routes->post('archive/(:num)', 'Families\FamilyController::archive/$1');
        $routes->post('restore/(:num)', 'Families\FamilyController::restore/$1');
    });

    $routes->group('sectors', static function (RouteCollection $routes): void {
        $routes->post('create', 'Lookups\SectorController::create');
        $routes->post('update/(:num)', 'Lookups\SectorController::update/$1');
        $routes->post('delete/(:num)', 'Lookups\SectorController::delete/$1');
        $routes->post('archive/(:num)', 'Lookups\SectorController::archive/$1');
        $routes->post('restore/(:num)', 'Lookups\SectorController::restore/$1');
    });

    $routes->group('categories', static function (RouteCollection $routes): void {
        $routes->post('create', 'Lookups\CategoryController::create');
        $routes->post('update/(:num)', 'Lookups\CategoryController::update/$1');
        $routes->post('archive/(:num)', 'Lookups\CategoryController::archive/$1');
        $routes->post('restore/(:num)', 'Lookups\CategoryController::restore/$1');
    });

    $routes->group('services', static function (RouteCollection $routes): void {
        $routes->post('create', 'Lookups\ServiceController::create');
        $routes->post('update/(:num)', 'Lookups\ServiceController::update/$1');
        $routes->post('delete/(:num)', 'Lookups\ServiceController::delete/$1');
        $routes->post('archive/(:num)', 'Lookups\ServiceController::archive/$1');
        $routes->post('restore/(:num)', 'Lookups\ServiceController::restore/$1');
    });

    $routes->get('aidtypes', 'Admin\AidTypesController::index');
    $routes->group('aidtypes', static function (RouteCollection $routes): void {
        $routes->post('create', 'Admin\AidTypesController::create');
        $routes->post('archive/(:num)', 'Admin\AidTypesController::archive/$1');
        $routes->post('restore/(:num)', 'Admin\AidTypesController::restore/$1');
        $routes->post('delete/(:num)', 'Admin\AidTypesController::deleteType/$1');
    });

    $routes->group('cards', static function (RouteCollection $routes): void {
        $routes->get('', 'Admin\DashboardController::cards');
        $routes->post('generate', 'Cards\QrCardController::batch');
        $routes->get('card/(:num)', 'Cards\QrCardController::card/$1');
        $routes->get('lookup/(:any)', 'Cards\QrCardController::lookup/$1');
    });

    $routes->group('batches', static function (RouteCollection $routes): void {
        $routes->get('', 'Admin\DistributionController::batches');
        $routes->post('open', 'Admin\DistributionController::openBatch');
        $routes->post('close/(:num)', 'Admin\DistributionController::closeBatch/$1');
    });
    $routes->group('distributions', static function (RouteCollection $routes): void {
        $routes->get('', 'Admin\DistributionController::distributions');
        $routes->post('void/(:num)', 'Admin\DistributionController::voidDistribution/$1');
    });
    $routes->get('reports', 'Admin\ReportsController::index');
    $routes->get('reports/stats', 'Admin\ReportsController::stats');
    $routes->get('reports/pdf', 'Admin\ReportsController::pdf');
});

/*
 * Employee workspace
 */
$routes->group('employee', static function (RouteCollection $routes): void {
    $routes->get('workspace', 'Employee\DashboardController::dashboard');
    $routes->get('family-entry', 'Employee\DashboardController::familyEntry');
    $routes->get('manage-records', 'Employee\DashboardController::manageRecords');
    $routes->get('activity', 'Employee\DashboardController::activity');

    $routes->get('manage-families', 'Employee\DashboardController::manageRecords');
    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('', 'Employee\DashboardController::manageRecords');
        $routes->get('list', 'Families\FamilyController::listFamilies');
        $routes->get('data', 'Families\FamilyDataTableController::dataTable');
        $routes->get('template', 'Families\FamilyImportController::downloadTemplate');
        $routes->get('import', 'Families\FamilyImportController::importForm');
        $routes->post('import', 'Families\FamilyImportController::import');
        $routes->get('import/status/(:num)', 'Families\FamilyImportController::importStatus/$1');
        $routes->get('import/review/(:num)', 'Families\FamilyImportController::reviewPage/$1');
        $routes->post('import/review/(:num)/commit', 'Families\FamilyImportController::reviewCommit/$1');
        $routes->post('import/review/(:num)/cancel', 'Families\FamilyImportController::reviewCancel/$1');
        $routes->get('import/review/(:num)/family', 'Families\FamilyImportController::reviewFamilyModal/$1');
        $routes->post('import/review/(:num)/family/save', 'Families\FamilyImportController::reviewFamilySave/$1');
        $routes->post('import/review/(:num)/family/remove', 'Families\FamilyImportController::reviewFamilyRemove/$1');
        $routes->get('create', 'Families\FamilyController::createFamily');
        $routes->get('view/(:num)', 'Families\FamilyController::viewFamily/$1');
        $routes->get('edit/(:num)', 'Families\FamilyController::editFamily/$1');
        $routes->post('update/(:num)', 'Families\FamilyController::update/$1');
        $routes->post('archive/(:num)', 'Families\FamilyController::archive/$1');
        $routes->post('restore/(:num)', 'Families\FamilyController::restore/$1');
    });
});

/*
 * Viewer workspace (read-only). GET routes only — no mutation endpoints are
 * exposed. The read-only family detail fragment reuses FamilyController::viewFamily,
 * which permits the Viewer role via requireFamilyViewAccess().
 */
$routes->group('viewer', static function (RouteCollection $routes): void {
    $routes->get('', 'Viewer\DashboardController::index');
    $routes->get('dashboard', 'Viewer\DashboardController::dashboard');
    $routes->get('manage-records', 'Viewer\DashboardController::manageRecords');
    $routes->get('manage-families', 'Viewer\DashboardController::manageRecords');
    $routes->get('sectors', 'Viewer\DashboardController::sectors');
    $routes->get('services', 'Viewer\DashboardController::services');
    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('data', 'Families\FamilyDataTableController::dataTable');
        $routes->get('view/(:num)', 'Families\FamilyController::viewFamily/$1');
    });
});

/**
 * Scanner module (aid distribution). Scanner/Admin/Developer only — each action
 * calls RoleAccess::requireRole() internally (mirrors the Cards controller).
 */
$routes->group('scanner', static function (RouteCollection $routes): void {
    $routes->get('scan', 'Scanner\ScanController::scan');
    $routes->get('performance', 'Scanner\ScanController::performance');
    $routes->get('stats', 'Scanner\ScanController::stats');
    $routes->post('log', 'Scanner\ScanController::logAid');
});

/*
 * Shared submissions
 */
$routes->post('developer/accounts', 'Accounts\AccountController::create');
$routes->post('developer/accounts/status', 'Accounts\AccountController::updateStatus');

// Account management edit + password reset (Admin/Developer; controllers self-guard).
$routes->get('accounts/create', 'Accounts\AccountController::createForm');
$routes->get('accounts/edit/(:num)', 'Accounts\AccountController::editForm/$1');
$routes->post('accounts/update', 'Accounts\AccountController::update');
$routes->post('accounts/reset-password', 'Accounts\AccountController::resetPassword');

// Self-service My Account (any logged-in non-developer).
$routes->get('account/profile', 'Accounts\ProfileController::myAccount');
$routes->post('account/profile/update', 'Accounts\ProfileController::update');

$routes->post('families', 'Families\FamilyController::store');
