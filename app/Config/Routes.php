<?php

use CodeIgniter\Router\RouteCollection;

$routes->get('/', 'MainLayoutController::index');
$routes->get('admin', 'MainLayoutController::index');
$routes->get('admin/dashboard', 'MainLayoutController::index');


//LOGIN, LOGOUT ROUTES
$routes->match(['GET', 'POST'], 'login', 'Auth\LoginController::login');
$routes->get('logout', 'Auth\LoginController::logout');


//MANAGE RECORDS
$routes->get('admin/manage-records', 'ManageRecordsController::index');
$routes->get('admin/family-record/new', 'ManageRecordsController::newRecord');
$routes->get('admin/family-record/(:num)/edit', 'ManageRecordsController::editRecord/$1');

// REFERENCE DATA
$routes->get('admin/sectors', 'SectorController::index');
$routes->get('admin/services', 'ServiceAndProgramsController::index');

// ADMINISTRATION
$routes->get('admin/accounts', 'AccountManagementController::index');
$routes->post('admin/accounts/create', 'AccountManagementController::create');
$routes->get('admin/audit-trails', 'AuditTrailsController::index');

// EMPLOYEE
$routes->get('employee/workspace', 'EmployeeWorkspaceController::workspace');
$routes->get('employee/manage-records', 'EmployeeWorkspaceController::manageRecords');
$routes->get('employee/family-record/new', 'EmployeeWorkspaceController::newRecord');
$routes->get('employee/family-record/(:num)/edit', 'EmployeeWorkspaceController::editRecord/$1');
$routes->get('employee/activity', 'EmployeeWorkspaceController::activity');
