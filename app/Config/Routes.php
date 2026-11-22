<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('robots.txt', 'SiteMetadata::robots', ['as' => 'robots']);
$routes->get('sitemap.xml', 'SiteMetadata::sitemap', ['as' => 'sitemap']);

$routes->get('login', 'AuthLogin::loginView', ['as' => 'login']);
$routes->post('login', 'AuthLogin::loginAction');
$routes->get('register', 'AuthRegister::registerView', ['as' => 'register']);
$routes->post('register', 'AuthRegister::registerAction');
service('auth')->routes($routes, ['except' => ['login', 'register', 'magic-link', 'logout']]);
$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', ['as' => 'logout']);
// Password-reset routes replace magic-link semantics in the forgot-password flow.
$routes->get('login/magic-link', 'PasswordReset::forgotView', ['as' => 'magic-link']);
$routes->post('login/magic-link', 'PasswordReset::requestReset', ['as' => 'password-forgot']);
$routes->get('password/reset-requested', 'PasswordReset::resetRequested', ['as' => 'password-reset-requested']);
$routes->get('password/reset/(:segment)', 'PasswordReset::resetView/$1', ['as' => 'password-reset']);
$routes->post('password/reset/(:segment)', 'PasswordReset::resetAction/$1', ['as' => 'password-reset-action']);
$routes->get(
    'login/verify-magic-link',
    '\CodeIgniter\Shield\Controllers\MagicLinkController::verify',
    ['as' => 'verify-magic-link'],
);

$routes->group('dashboard', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Dashboard::index', ['as' => 'dashboard']);
    $routes->post('cv', 'Dashboard::create', ['as' => 'cv-create']);
    $routes->post('cv/quick', 'Dashboard::quickCreate', ['as' => 'cv-quick-create']);
    $routes->post('cv/ai/prepare', 'Dashboard::aiPrepare', ['as' => 'cv-ai-prepare']);
    $routes->post('cv/ai/generate', 'Dashboard::aiGenerate', ['as' => 'cv-ai-generate']);
    $routes->post('cv/ai/save', 'Dashboard::aiSave', ['as' => 'cv-ai-save']);
    $routes->get('cv/(:segment)', 'CvBuilder::edit/$1', ['as' => 'cv-show']);
    $routes->get('cv/(:segment)/step/(:num)', 'CvBuilder::edit/$1/$2', ['as' => 'cv-step']);
    $routes->post('cv/(:segment)/autosave/(:num)', 'CvBuilder::autosave/$1/$2', ['as' => 'cv-autosave']);
    $routes->get('cv/(:segment)/preview', 'CvPreview::current/$1', ['as' => 'cv-preview']);
    $routes->get('cv/(:segment)/preview/(:segment)', 'CvPreview::theme/$1/$2', ['as' => 'cv-theme-preview']);
    $routes->get('cv/(:segment)/pages', 'CvPreview::pageCount/$1', ['as' => 'cv-page-count']);
    $routes->get('cv/(:segment)/download', 'CvDownload::download/$1', ['as' => 'cv-download']);
    $routes->get('cv/(:segment)/payment', 'CvPayments::checkout/$1', ['as' => 'cv-payment']);
    $routes->post('cv/(:segment)/payment/proof', 'CvPayments::submit/$1', ['as' => 'cv-payment-submit']);
    $routes->get('payments/qr', 'CvPayments::qr', ['as' => 'cv-payment-qr']);
    $routes->get('payments/admin', 'CvPayments::admin', ['as' => 'cv-payment-admin']);
    $routes->post('payments/admin/qr', 'CvPayments::uploadQr', ['as' => 'cv-payment-qr-upload']);
    $routes->get('payments/admin/proof/(:segment)', 'CvPayments::proof/$1', ['as' => 'cv-payment-proof']);
    $routes->post('payments/admin/review/(:segment)', 'CvPayments::review/$1', ['as' => 'cv-payment-review']);
    $routes->post('cv/(:segment)/photo', 'CvBuilder::uploadPhoto/$1', ['as' => 'cv-photo-upload']);
    $routes->get('cv/(:segment)/photo', 'CvBuilder::photo/$1', ['as' => 'cv-photo']);
    $routes->post('cv/(:segment)/duplicate', 'Dashboard::duplicate/$1', ['as' => 'cv-duplicate']);
    $routes->post('cv/(:segment)/delete', 'Dashboard::delete/$1', ['as' => 'cv-delete']);
    $routes->get('cv/(:segment)/jobs', 'Jobs::index/$1', ['as' => 'cv-jobs']);
    $routes->post('cv/(:segment)/jobs/consent', 'Jobs::grantConsent/$1', ['as' => 'cv-jobs-consent']);
    $routes->delete('cv/(:segment)/jobs/consent', 'Jobs::revokeConsent/$1', ['as' => 'cv-jobs-consent-revoke']);
    $routes->post('cv/(:segment)/jobs/consent/revoke', 'Jobs::revokeConsent/$1', ['as' => 'cv-jobs-consent-revoke-form']);
    $routes->get('cv/(:segment)/jobs/preferences', 'Jobs::preferences/$1', ['as' => 'cv-jobs-preferences']);
    $routes->post('cv/(:segment)/jobs/preferences', 'Jobs::savePreferences/$1', ['as' => 'cv-jobs-preferences-save']);
    $routes->post('cv/(:segment)/jobs/refresh', 'Jobs::refresh/$1', ['as' => 'cv-jobs-refresh']);
    $routes->get('cv/(:segment)/jobs/results', 'Jobs::results/$1', ['as' => 'cv-jobs-results']);
    $routes->get('cv/(:segment)/jobs/match/(:segment)', 'Jobs::detail/$1/$2', ['as' => 'cv-jobs-detail']);
    $routes->post('cv/(:segment)/jobs/match/(:segment)/save', 'Jobs::saveMatch/$1/$2', ['as' => 'cv-jobs-save']);
    $routes->post('cv/(:segment)/jobs/match/(:segment)/hide', 'Jobs::hideMatch/$1/$2', ['as' => 'cv-jobs-hide']);
    $routes->post('cv/(:segment)/jobs/match/(:segment)/restore', 'Jobs::restoreMatch/$1/$2', ['as' => 'cv-jobs-restore']);
    $routes->post('cv/(:segment)/jobs/match/(:segment)/application', 'Jobs::updateApplication/$1/$2', ['as' => 'cv-jobs-application-update']);
    $routes->get('cv/(:segment)/jobs/applications', 'Jobs::applications/$1', ['as' => 'cv-jobs-applications']);
    $routes->get('cv/(:segment)/jobs/insights', 'Jobs::insights/$1', ['as' => 'cv-jobs-insights']);
    $routes->get('password', 'Account::editPassword', ['as' => 'password-edit']);
    $routes->post('password', 'Account::updatePassword', ['as' => 'password-update']);
});
