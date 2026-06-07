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
    $routes->get('', 'Workspace\HomeController::admin');
    $routes->get('dashboard', 'Workspace\HomeController::adminDashboard');
    $routes->get('accounts', 'Workspace\HomeController::adminAccounts');
    $routes->get('family-entry', 'Workspace\HomeController::adminFamilyEntry');
    $routes->get('manage-records', 'Workspace\HomeController::adminManageRecords');
    $routes->get('audit-trails', 'Workspace\HomeController::adminAuditTrails');
    $routes->get('sectors', 'Workspace\HomeController::adminSectors');
    $routes->get('services', 'Workspace\HomeController::adminServices');
    $routes->get('manage-members', 'Workspace\HomeController::adminManageMembers');
    // Admin-only: disable/enable employee accounts from Account Management.
    $routes->post('accounts/disable', 'Accounts\AccountController::disableEmployee');
    $routes->post('accounts/enable', 'Accounts\AccountController::enableEmployee');

    $routes->get('manage-families', 'Workspace\HomeController::adminManageRecords');
    $routes->group('manage-family', static function (RouteCollection $routes): void {
        $routes->get('', 'Workspace\HomeController::adminFamilyEntry');
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
        $routes->post('archive/(:num)', 'Families\FamilyController::archive/$1');
        $routes->post('restore/(:num)', 'Families\FamilyController::restore/$1');
    });
});

/*
 * Shared submissions
 */
$routes->post('developer/accounts', 'Accounts\AccountController::create');
$routes->post('developer/accounts/status', 'Accounts\AccountController::updateStatus');
$routes->post('families', 'Families\FamilyController::store');
