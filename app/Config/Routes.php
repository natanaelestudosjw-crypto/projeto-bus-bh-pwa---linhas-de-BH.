<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Onibus::app');
$routes->get('onibus', 'Onibus::index');
$routes->get('onibus/proximos', 'Onibus::proximos');
$routes->get('onibus/app' , 'Onibus::app');