<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

/*
 * Authentication
 */
$routes->get('/', 'Home::index');
$routes->match(['GET', 'POST'], 'login', 'Home::login');
$routes->get('logout', 'Home::logout');
$routes->get('session/keep-alive', 'Home::keepAlive');

/*
 * Admin workspace
 */
$routes->group('admin', static function (RouteCollection $routes): void {
    $routes->get('', 'Home::admin');
    $routes->get('dashboard', 'Home::adminDashboard');
    $routes->get('accounts', 'Home::adminAccounts');
    $routes->get('family-entry', 'Home::adminFamilyEntry');
    $routes->get('audit-trails', 'Home::adminAuditTrails');
    $routes->get('sectors', 'Home::adminSectors');
    $routes->get('services', 'Home::adminServices');
    // Admin-only: disable employee accounts from Account Management.
    $routes->post('accounts/disable', 'AccountController::disableEmployee');

    $routes->get('manage-families', 'Home::adminFamilyEntry');
    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('', 'Home::adminFamilyEntry');
        $routes->get('list', 'FamilyController::listFamilies');
        $routes->get('view/(:num)', 'FamilyController::viewFamily/$1');
        $routes->get('edit/(:num)', 'FamilyController::editFamily/$1');
        $routes->post('update/(:num)', 'FamilyController::update/$1');
        $routes->post('archive/(:num)', 'FamilyController::archive/$1');
        $routes->post('restore/(:num)', 'FamilyController::restore/$1');
    });

    $routes->group('sectors', static function (RouteCollection $routes): void {
        $routes->post('create', 'SectorController::create');
        $routes->post('update/(:num)', 'SectorController::update/$1');
        $routes->post('archive/(:num)', 'SectorController::archive/$1');
    });

    $routes->group('services', static function (RouteCollection $routes): void {
        $routes->post('create', 'ServiceController::create');
        $routes->post('update/(:num)', 'ServiceController::update/$1');
        $routes->post('archive/(:num)', 'ServiceController::archive/$1');
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
    $routes->get('workspace', 'Home::employee');
    $routes->get('family-entry', 'Home::employeeFamilyEntry');
    $routes->get('activity', 'Home::employeeActivity');

    $routes->get('manage-families', 'Home::employeeFamilyEntry');
    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('', 'Home::employeeFamilyEntry');
        $routes->get('list', 'FamilyController::listFamilies');
        $routes->get('view/(:num)', 'FamilyController::viewFamily/$1');
        $routes->get('edit/(:num)', 'FamilyController::editFamily/$1');
        $routes->post('update/(:num)', 'FamilyController::update/$1');
        $routes->post('delete/(:num)', 'FamilyController::delete/$1');
    });
});

/*
 * Shared submissions
 */
$routes->post('developer/accounts', 'AccountController::create');
// Developer-only: toggle staff account status from Account Management (Dashboard/accounts).
$routes->post('developer/accounts/status', 'AccountController::updateStatus');
$routes->post('families', 'FamilyController::store');
