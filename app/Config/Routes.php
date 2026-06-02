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
$routes->get('logout', 'Auth\AuthController::logout');
$routes->get('session/keep-alive', 'Auth\AuthController::keepAlive');

/*
 * Admin workspace
 */
$routes->group('admin', static function (RouteCollection $routes): void {
    $routes->get('', 'Workspace\Home::admin');
    $routes->get('dashboard', 'Workspace\Home::adminDashboard');
    $routes->get('accounts', 'Workspace\Home::adminAccounts');
    $routes->get('family-entry', 'Workspace\Home::adminFamilyEntry');
    $routes->get('manage-records', 'Workspace\Home::adminManageRecords');
    $routes->get('audit-trails', 'Workspace\Home::adminAuditTrails');
    $routes->get('sectors', 'Workspace\Home::adminSectors');
    $routes->get('services', 'Workspace\Home::adminServices');
    $routes->get('manage-members', 'Workspace\Home::adminManageMembers');
    // Admin-only: disable employee accounts from Account Management.
    $routes->post('accounts/disable', 'Accounts\AccountController::disableEmployee');

    $routes->get('manage-families', 'Workspace\Home::adminManageRecords');
    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('', 'Workspace\Home::adminFamilyEntry');
        $routes->get('list', 'Families\FamilyController::listFamilies');
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

    $routes->group('services', static function (RouteCollection $routes): void {
        $routes->post('create', 'Lookups\ServiceController::create');
        $routes->post('update/(:num)', 'Lookups\ServiceController::update/$1');
        $routes->post('delete/(:num)', 'Lookups\ServiceController::delete/$1');
        $routes->post('archive/(:num)', 'Lookups\ServiceController::archive/$1');
        $routes->post('restore/(:num)', 'Lookups\ServiceController::restore/$1');
    });

    $routes->group('lookups', static function (RouteCollection $routes): void {
        // Sectors
        $routes->get('sectors', 'Admin\SectorController::index');
        $routes->post('sectors/store', 'Admin\SectorController::store');
        $routes->post('sectors/update/(:num)', 'Admin\SectorController::update/$1');
        $routes->post('sectors/archive/(:num)', 'Admin\SectorController::archive/$1');
        $routes->post('sectors/restore/(:num)', 'Admin\SectorController::restore/$1');

        // Services
        $routes->get('services', 'Admin\ServicesController::index');
        $routes->post('services/store', 'Admin\ServicesController::store');
        $routes->post('services/update/(:num)', 'Admin\ServicesController::update/$1');
        $routes->post('services/archive/(:num)', 'Admin\ServicesController::archive/$1');
        $routes->post('services/restore/(:num)', 'Admin\ServicesController::restore/$1');
    });
});

/*
 * Employee workspace
 */
$routes->group('employee', static function (RouteCollection $routes): void {
    $routes->get('workspace', 'Employee\WorkspaceController::dashboard');
    $routes->get('family-entry', 'Employee\WorkspaceController::familyEntry');
    $routes->get('manage-records', 'Employee\WorkspaceController::manageRecords');
    $routes->get('activity', 'Employee\WorkspaceController::activity');

    $routes->get('manage-families', 'Employee\WorkspaceController::manageRecords');
    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('', 'Employee\WorkspaceController::familyEntry');
        $routes->get('list', 'Families\FamilyController::listFamilies');
        $routes->get('view/(:num)', 'Families\FamilyController::viewFamily/$1');
        $routes->get('edit/(:num)', 'Families\FamilyController::editFamily/$1');
        $routes->post('update/(:num)', 'Families\FamilyController::update/$1');
        $routes->post('delete/(:num)', 'Families\FamilyController::delete/$1');
    });
});

/*
 * Shared submissions
 */
$routes->post('developer/accounts', 'Accounts\AccountController::create');
$routes->post('developer/accounts/status', 'Accounts\AccountController::updateStatus');
$routes->post('families', 'Families\FamilyController::store');
