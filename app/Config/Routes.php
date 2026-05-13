<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->post('login', 'Home::login');
$routes->get('logout', 'Home::logout');
$routes->get('admin', 'Home::admin');
$routes->get('employee/workspace', 'Home::employee');
