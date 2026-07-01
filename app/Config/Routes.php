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
        $routes->get('', 'Admin\DashboardController::familyEntry');
        $routes->get('list', 'Families\FamilyController::listFamilies');
        $routes->get('data', 'Families\FamilyController::dataTable');
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

    $routes->group('cards', static function (RouteCollection $routes): void {
        $routes->get('', 'Admin\DashboardController::cards');
        $routes->post('generate', 'Cards\QrCardController::batch');
        $routes->get('card/(:num)', 'Cards\QrCardController::card/$1');
        $routes->get('lookup/(:any)', 'Cards\QrCardController::lookup/$1');
    });
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
        $routes->get('', 'Employee\DashboardController::familyEntry');
        $routes->get('list', 'Families\FamilyController::listFamilies');
        $routes->get('data', 'Families\FamilyController::dataTable');
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
        $routes->get('data', 'Families\FamilyController::dataTable');
        $routes->get('view/(:num)', 'Families\FamilyController::viewFamily/$1');
    });
});

/**
 * Scanner module (aid distribution). Scanner/Admin/Developer only — each action
 * calls RoleAccess::requireRole() internally (mirrors the Cards controller).
 */
$routes->group('scanner', static function (RouteCollection $routes): void {
    $routes->get('scan', 'Scanner\ScanController::scan');
    $routes->get('lookup/(:num)', 'Scanner\ScanController::lookup/$1');
    $routes->post('log', 'Scanner\ScanController::logAid');

    $routes->get('manage', 'Scanner\ManageController::index');
    $routes->post('aid-types/create', 'Scanner\ManageController::createAidType');
    $routes->post('aid-types/archive/(:num)', 'Scanner\ManageController::archiveAidType/$1');
    $routes->post('aid-types/restore/(:num)', 'Scanner\ManageController::restoreAidType/$1');
    $routes->post('aid-types/delete/(:num)', 'Scanner\ManageController::deleteAidType/$1');
    $routes->post('distributions/void/(:num)', 'Scanner\ManageController::voidDistribution/$1');
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
