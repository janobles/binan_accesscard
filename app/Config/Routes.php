<?php

use CodeIgniter\Router\RouteCollection;

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
    $routes->get('', 'Admin\DashboardController::index');
    $routes->get('dashboard', 'Admin\DashboardController::dashboard');
    $routes->get('accounts', 'Admin\DashboardController::accounts');
    $routes->get('family-entry', 'Admin\DashboardController::familyEntry');
    $routes->get('manage-records', 'Admin\DashboardController::manageRecords');
    $routes->get('manage-families', 'Admin\DashboardController::manageRecords');
    $routes->get('manage-members', 'Admin\DashboardController::manageMembers');
    $routes->get('audit-trails', 'Admin\DashboardController::auditTrails');
    $routes->get('sectors', 'Admin\DashboardController::sectors');
    $routes->get('services', 'Admin\DashboardController::services');

    $routes->post('accounts/create', 'Accounts\AccountController::create');
    $routes->post('accounts/status', 'Accounts\AccountController::updateStatus');
    $routes->post('accounts/disable', 'Accounts\AccountController::disableEmployee');
    $routes->post('accounts/enable', 'Accounts\AccountController::enableEmployee');

    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('', 'Admin\DashboardController::familyEntry');
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

    $routes->group('sector-categories', static function (RouteCollection $routes): void {
        $routes->post('save', 'Lookups\SectorCategoryController::save');
        $routes->post('delete', 'Lookups\SectorCategoryController::delete');
    });

    $routes->group('services', static function (RouteCollection $routes): void {
        $routes->post('create', 'Lookups\ServiceController::create');
        $routes->post('update/(:num)', 'Lookups\ServiceController::update/$1');
        $routes->post('delete/(:num)', 'Lookups\ServiceController::delete/$1');
        $routes->post('archive/(:num)', 'Lookups\ServiceController::archive/$1');
        $routes->post('restore/(:num)', 'Lookups\ServiceController::restore/$1');
    });
});

/*
 * Employee workspace
 */
$routes->group('employee', static function (RouteCollection $routes): void {
    $routes->get('workspace', 'Employee\DashboardController::dashboard');
    $routes->get('family-entry', 'Employee\DashboardController::familyEntry');
    $routes->get('manage-records', 'Employee\DashboardController::manageRecords');
    $routes->get('manage-families', 'Employee\DashboardController::manageRecords');
    $routes->get('activity', 'Employee\DashboardController::activity');

    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('', 'Employee\DashboardController::familyEntry');
        $routes->get('list', 'Families\FamilyController::listFamilies');
        $routes->get('view/(:num)', 'Families\FamilyController::viewFamily/$1');
        $routes->get('edit/(:num)', 'Families\FamilyController::editFamily/$1');
        $routes->post('update/(:num)', 'Families\FamilyController::update/$1');
        $routes->post('archive/(:num)', 'Families\FamilyController::archive/$1');
        $routes->post('restore/(:num)', 'Families\FamilyController::restore/$1');
    });
});

/*
 * Shared submissions and legacy-compatible account endpoints.
 */
$routes->post('families', 'Families\FamilyController::store');
$routes->post('developer/accounts', 'Accounts\AccountController::create');
$routes->post('developer/accounts/status', 'Accounts\AccountController::updateStatus');
