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
