<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Authentication and session routes.
$routes->get('/', 'Home::index');
$routes->get('login', 'Home::index');
$routes->post('login', 'Home::login');
$routes->get('logout', 'Home::logout');
$routes->get('session/keep-alive', 'Home::keepAlive');

// Admin dashboard routes.
$routes->get('admin', 'Home::admin');
$routes->get('admin/dashboard', 'Home::adminDashboard');
$routes->get('admin/accounts', 'Home::adminAccounts');
$routes->get('admin/family-entry', 'Home::adminFamilyEntry');
$routes->get('admin/audit-trails', 'Home::adminAuditTrails');
$routes->get('admin/sectors', 'Home::adminSectors');
$routes->get('admin/services', 'Home::adminServices');

// Admin family management routes.
$routes->get('admin/manage-family', 'Home::adminFamilyEntry');
$routes->get('admin/manage-families', 'Home::adminFamilyEntry');
$routes->get('admin/manage-family/list', 'FamilyController::listFamilies');
$routes->get('admin/manage-family/view/(:num)', 'FamilyController::viewFamily/$1');
$routes->get('admin/manage-family/edit/(:num)', 'FamilyController::editFamily/$1');
$routes->post('admin/manage-family/update/(:num)', 'FamilyController::update/$1');

// Admin sector management routes.
$routes->post('admin/sectors/create', 'SectorController::create');
$routes->post('admin/sectors/update/(:num)', 'SectorController::update/$1');
$routes->post('admin/sectors/archive/(:num)', 'SectorController::archive/$1');

// Admin service management routes.
$routes->post('admin/services/create', 'ServiceController::create');
$routes->post('admin/services/update/(:num)', 'ServiceController::update/$1');
$routes->post('admin/services/archive/(:num)', 'ServiceController::archive/$1');

// Employee dashboard routes.
$routes->get('employee/workspace', 'Home::employee');
$routes->get('employee/family-entry', 'Home::employeeFamilyEntry');
$routes->get('employee/activity', 'Home::employeeActivity');

// Employee family management routes.
$routes->get('employee/manage-family', 'Home::employeeFamilyEntry');
$routes->get('employee/manage-families', 'Home::employeeFamilyEntry');
$routes->get('employee/manage-family/list', 'FamilyController::listFamilies');
$routes->get('employee/manage-family/view/(:num)', 'FamilyController::viewFamily/$1');
$routes->get('employee/manage-family/edit/(:num)', 'FamilyController::editFamily/$1');
$routes->post('employee/manage-family/update/(:num)', 'FamilyController::update/$1');

// Account and family submission routes.
$routes->post('developer/accounts', 'AccountController::create');
$routes->post('families', 'FamilyController::store');
