<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->post('login', 'Home::login');
$routes->get('logout', 'Home::logout');
$routes->get('admin', 'Home::admin');
$routes->get('admin/dashboard', 'Home::adminDashboard');
$routes->get('admin/accounts', 'Home::adminAccounts');
$routes->get('admin/family-entry', 'Home::adminFamilyEntry');
$routes->get('admin/manage-family', 'Home::adminFamilyEntry');
$routes->get('admin/manage-families', 'Home::adminFamilyEntry');
$routes->get('admin/audit-trails', 'Home::adminAuditTrails');
$routes->get('employee/workspace', 'Home::employee');
$routes->get('employee/family-entry', 'Home::employeeFamilyEntry');
$routes->get('employee/manage-family', 'Home::employeeFamilyEntry');
$routes->get('employee/manage-families', 'Home::employeeFamilyEntry');
$routes->get('employee/activity', 'Home::employeeActivity');
$routes->post('developer/accounts', 'AccountController::create');
$routes->post('families', 'FamilyController::store');
