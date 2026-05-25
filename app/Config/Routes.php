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
    $routes->get('', 'Home::admin');
    $routes->get('dashboard', 'Home::adminDashboard');
    $routes->get('accounts', 'Home::adminAccounts');
    $routes->get('family-entry', 'Home::adminFamilyEntry');
    $routes->get('audit-trails', 'Home::adminAuditTrails');
    $routes->get('sectors', 'Home::adminSectors');
    $routes->get('services', 'Home::adminServices');

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
$routes->post('families', 'FamilyController::store');
