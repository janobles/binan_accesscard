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
    $routes->get('', 'Admin\WorkspaceController::index');
    $routes->get('dashboard', 'Admin\WorkspaceController::dashboard');
    $routes->get('accounts', 'Admin\WorkspaceController::accounts');
    $routes->get('family-entry', 'Admin\WorkspaceController::familyEntry');
    $routes->get('manage-records', 'Admin\WorkspaceController::manageRecords');
    $routes->get('audit-trails', 'Admin\WorkspaceController::auditTrails');
    $routes->get('sectors', 'Admin\SectorController::index');
    $routes->get('services', 'Admin\ServicesController::index');
    $routes->get('manage-members', 'Admin\WorkspaceController::manageRecords');
    // Admin-only: disable employee accounts from Account Management.
    $routes->post('accounts/disable', 'AccountController::disableEmployee');

    $routes->get('manage-families', 'Admin\WorkspaceController::manageRecords');
    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('', 'Admin\WorkspaceController::familyEntry');
        $routes->get('list', 'FamilyController::listFamilies');
        $routes->get('view/(:num)', 'FamilyController::viewFamily/$1');
        $routes->get('edit/(:num)', 'FamilyController::editFamily/$1');
        $routes->post('update/(:num)', 'FamilyController::update/$1');
        $routes->post('archive/(:num)', 'FamilyController::archive/$1');
        $routes->post('restore/(:num)', 'FamilyController::restore/$1');
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
$routes->post('developer/accounts/status', 'AccountController::updateStatus');
$routes->post('families', 'FamilyController::store');
