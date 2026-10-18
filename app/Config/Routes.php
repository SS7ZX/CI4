<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

service('auth')->routes($routes, ['except' => ['magic-link']]);
$routes->get('login/magic-link', 'MagicLink::loginView', ['as' => 'magic-link']);
$routes->post('login/magic-link', 'MagicLink::loginAction');
$routes->get(
    'login/verify-magic-link',
    '\CodeIgniter\Shield\Controllers\MagicLinkController::verify',
    ['as' => 'verify-magic-link'],
);

$routes->group('dashboard', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Dashboard::index', ['as' => 'dashboard']);
    $routes->post('cv', 'Dashboard::create', ['as' => 'cv-create']);
    $routes->get('cv/(:segment)', 'CvBuilder::edit/$1', ['as' => 'cv-show']);
    $routes->get('cv/(:segment)/step/(:num)', 'CvBuilder::edit/$1/$2', ['as' => 'cv-step']);
    $routes->post('cv/(:segment)/autosave/(:num)', 'CvBuilder::autosave/$1/$2', ['as' => 'cv-autosave']);
    $routes->post('cv/(:segment)/photo', 'CvBuilder::uploadPhoto/$1', ['as' => 'cv-photo-upload']);
    $routes->get('cv/(:segment)/photo', 'CvBuilder::photo/$1', ['as' => 'cv-photo']);
    $routes->post('cv/(:segment)/duplicate', 'Dashboard::duplicate/$1', ['as' => 'cv-duplicate']);
    $routes->post('cv/(:segment)/delete', 'Dashboard::delete/$1', ['as' => 'cv-delete']);
    $routes->get('password', 'Account::editPassword', ['as' => 'password-edit']);
    $routes->post('password', 'Account::updatePassword', ['as' => 'password-update']);
});
