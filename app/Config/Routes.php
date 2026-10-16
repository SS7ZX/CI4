<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

service('auth')->routes($routes);

$routes->group('dashboard', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Dashboard::index', ['as' => 'dashboard']);
    $routes->post('cv', 'Dashboard::create', ['as' => 'cv-create']);
    $routes->get('cv/(:segment)', 'Dashboard::show/$1', ['as' => 'cv-show']);
    $routes->post('cv/(:segment)/duplicate', 'Dashboard::duplicate/$1', ['as' => 'cv-duplicate']);
    $routes->post('cv/(:segment)/delete', 'Dashboard::delete/$1', ['as' => 'cv-delete']);
    $routes->get('password', 'Account::editPassword', ['as' => 'password-edit']);
    $routes->post('password', 'Account::updatePassword', ['as' => 'password-update']);
});
