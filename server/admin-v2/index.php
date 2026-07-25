<?php
/**
 * My Bingwa Admin V2 — single front controller. Every request enters here (the
 * .htaccess routes all non-asset paths to this file). Bootstraps the kernel, then
 * dispatches to a controller.
 *
 * Deployment: upload this folder into public_html (e.g. public_html/admin) and open
 * https://your-domain/admin/. See docs/ADMIN_V2_DEPLOYMENT.md.
 */

declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Core\Auth;

require __DIR__ . '/app/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/app');

// Store everything in UTC; display in Africa/Nairobi via helpers.
date_default_timezone_set('UTC');

Config::load(__DIR__ . '/config/config.php');
Database::boot();

$request = new Request();
$isApi = strpos($request->path(), '/api/') === 0;

// Sessions + security headers are for the admin UI, not the stateless public API.
if (!$isApi) {
    Session::start();
    Response::securityHeaders();
    View::boot(__DIR__ . '/app/Views');
    View::share('basePath', Request::basePath());
    if (Auth::check()) {
        Auth::touchSession($request->ip());
    }
}

$router = new Router();

/* --------------------------------------------------------------- public API */
$router->get('/api/v1/app/manifest',          [App\Controllers\Api\SyncController::class, 'manifest']);
$router->get('/api/v1/app/snapshot/{version}',[App\Controllers\Api\SyncController::class, 'snapshot']);
$router->get('/api/v1/app/sync',              [App\Controllers\Api\SyncController::class, 'sync']);
$router->get('/api/v1/app/offers',            [App\Controllers\Api\SyncController::class, 'offers']);
$router->get('/api/v1/app/config',            [App\Controllers\Api\SyncController::class, 'config']);
$router->get('/api/v1/app/templates',         [App\Controllers\Api\SyncController::class, 'templates']);
$router->post('/api/v1/app/sync-events',      [App\Controllers\Api\SyncController::class, 'events']);
$router->get('/api/v1/health',                [App\Controllers\Api\SyncController::class, 'health']);

/* --------------------------------------------------------------- auth flow */
$router->get('/login',   [App\Controllers\AuthController::class, 'showLogin']);
$router->post('/login',  [App\Controllers\AuthController::class, 'login']);
$router->get('/2fa',     [App\Controllers\AuthController::class, 'show2fa']);
$router->post('/2fa',    [App\Controllers\AuthController::class, 'verify2fa']);
$router->get('/forgot',  [App\Controllers\AuthController::class, 'showForgot']);
$router->post('/forgot', [App\Controllers\AuthController::class, 'forgot']);
$router->post('/logout', [App\Controllers\AuthController::class, 'logout']);
// No GET /logout — sign-out is POST-only (CSRF-protected) to prevent logout CSRF.

/* --------------------------------------------------------------- install */
$router->get('/install',  [App\Controllers\InstallController::class, 'show']);
$router->post('/install', [App\Controllers\InstallController::class, 'run']);
$router->post('/migrate', [App\Controllers\InstallController::class, 'migrate']);

/* --------------------------------------------------------------- dashboard */
$router->get('/',          [App\Controllers\DashboardController::class, 'index']);
$router->get('/dashboard', [App\Controllers\DashboardController::class, 'index']);
$router->get('/dashboard/export', [App\Controllers\DashboardController::class, 'exportCsv']);

/* --------------------------------------------------------------- offers */
$router->get('/offers',              [App\Controllers\OffersController::class, 'index']);
$router->get('/offers/new',          [App\Controllers\OffersController::class, 'create']);
$router->get('/offers/{id}/edit',    [App\Controllers\OffersController::class, 'edit']);
$router->post('/offers/save',        [App\Controllers\OffersController::class, 'save']);
$router->post('/offers/{id}/duplicate', [App\Controllers\OffersController::class, 'duplicate']);
$router->post('/offers/{id}/archive',[App\Controllers\OffersController::class, 'archive']);
$router->post('/offers/{id}/restore',[App\Controllers\OffersController::class, 'restore']);
$router->post('/offers/{id}/delete', [App\Controllers\OffersController::class, 'delete']);
$router->get('/offers/export',       [App\Controllers\OffersController::class, 'exportCsv']);

/* --------------------------------------------------------------- billboards */
$router->get('/billboards',            [App\Controllers\BillboardsController::class, 'index']);
$router->get('/billboards/calendar',   [App\Controllers\BillboardsController::class, 'calendar']);
$router->get('/billboards/new',        [App\Controllers\BillboardsController::class, 'create']);
$router->get('/billboards/{id}/edit',  [App\Controllers\BillboardsController::class, 'edit']);
$router->post('/billboards/save',      [App\Controllers\BillboardsController::class, 'save']);
$router->post('/billboards/{id}/status', [App\Controllers\BillboardsController::class, 'setStatus']);
$router->post('/billboards/{id}/delete', [App\Controllers\BillboardsController::class, 'delete']);
$router->get('/billboards/simulator',  [App\Controllers\BillboardsController::class, 'simulator']);
$router->post('/billboards/simulator', [App\Controllers\BillboardsController::class, 'runSimulator']);

/* --------------------------------------------------------------- notifications */
$router->get('/notifications',           [App\Controllers\NotificationsController::class, 'index']);
$router->get('/notifications/calendar',  [App\Controllers\NotificationsController::class, 'calendar']);
$router->get('/notifications/new',       [App\Controllers\NotificationsController::class, 'create']);
$router->get('/notifications/{id}/edit', [App\Controllers\NotificationsController::class, 'edit']);
$router->post('/notifications/save',     [App\Controllers\NotificationsController::class, 'save']);
$router->post('/notifications/{id}/cancel', [App\Controllers\NotificationsController::class, 'cancel']);
$router->post('/notifications/{id}/test',[App\Controllers\NotificationsController::class, 'testSend']);

/* --------------------------------------------------------------- message templates */
$router->get('/message-templates',            [App\Controllers\TemplatesController::class, 'index']);
$router->get('/message-templates/new',        [App\Controllers\TemplatesController::class, 'create']);
$router->get('/message-templates/{id}/edit',  [App\Controllers\TemplatesController::class, 'edit']);
$router->post('/message-templates/save',      [App\Controllers\TemplatesController::class, 'save']);
$router->post('/message-templates/{id}/status', [App\Controllers\TemplatesController::class, 'setStatus']);
$router->post('/message-templates/{id}/duplicate', [App\Controllers\TemplatesController::class, 'duplicate']);
$router->post('/message-templates/{id}/delete', [App\Controllers\TemplatesController::class, 'delete']);
$router->get('/message-templates/console',    [App\Controllers\TemplatesController::class, 'console']);
$router->post('/message-templates/console',   [App\Controllers\TemplatesController::class, 'runConsole']);
$router->get('/message-templates/senders',    [App\Controllers\TemplatesController::class, 'senders']);
$router->post('/message-templates/senders/save', [App\Controllers\TemplatesController::class, 'saveSender']);

/* --------------------------------------------------------------- payments */
$router->get('/payments',            [App\Controllers\PaymentsController::class, 'index']);
$router->get('/payments/{id}',       [App\Controllers\PaymentsController::class, 'show']);
$router->get('/payments-export',     [App\Controllers\PaymentsController::class, 'exportCsv']);

/* --------------------------------------------------------------- support */
$router->get('/support',      [App\Controllers\SupportController::class, 'index']);
$router->post('/support/save',[App\Controllers\SupportController::class, 'save']);

/* --------------------------------------------------------------- payment gateway (server-side) */
$router->get('/gateway',      [App\Controllers\GatewayController::class, 'index']);
$router->post('/gateway/save',[App\Controllers\GatewayController::class, 'save']);

/* --------------------------------------------------------------- app config */
$router->get('/app-config',      [App\Controllers\AppConfigController::class, 'index']);
$router->post('/app-config/save',[App\Controllers\AppConfigController::class, 'save']);

/* --------------------------------------------------------------- versions */
$router->get('/versions',           [App\Controllers\VersionsController::class, 'index']);
$router->get('/versions/new',       [App\Controllers\VersionsController::class, 'create']);
$router->get('/versions/{id}/edit', [App\Controllers\VersionsController::class, 'edit']);
$router->post('/versions/save',     [App\Controllers\VersionsController::class, 'save']);
$router->post('/versions/{id}/activate', [App\Controllers\VersionsController::class, 'activate']);

/* --------------------------------------------------------------- audit */
$router->get('/audit',        [App\Controllers\AuditController::class, 'index']);
$router->get('/audit/{id}',   [App\Controllers\AuditController::class, 'show']);
$router->get('/audit-export', [App\Controllers\AuditController::class, 'exportCsv']);

/* --------------------------------------------------------------- settings */
$router->get('/settings',                  [App\Controllers\SettingsController::class, 'index']);
$router->post('/settings/profile',         [App\Controllers\SettingsController::class, 'saveProfile']);
$router->post('/settings/password',        [App\Controllers\SettingsController::class, 'savePassword']);
$router->get('/settings/2fa',              [App\Controllers\SettingsController::class, 'twoFactor']);
$router->post('/settings/2fa/enable',      [App\Controllers\SettingsController::class, 'enable2fa']);
$router->post('/settings/2fa/disable',     [App\Controllers\SettingsController::class, 'disable2fa']);
$router->post('/settings/sessions/revoke', [App\Controllers\SettingsController::class, 'revokeSession']);
$router->get('/settings/admins',           [App\Controllers\SettingsController::class, 'admins']);
$router->post('/settings/admins/save',     [App\Controllers\SettingsController::class, 'saveAdmin']);
$router->post('/settings/admins/{id}/disable', [App\Controllers\SettingsController::class, 'disableAdmin']);
$router->get('/settings/roles',            [App\Controllers\SettingsController::class, 'roles']);
$router->post('/settings/roles/save',      [App\Controllers\SettingsController::class, 'saveRole']);

/* --------------------------------------------------------------- publishing */
$router->get('/publish',              [App\Controllers\PublishController::class, 'review']);
$router->post('/publish/execute',     [App\Controllers\PublishController::class, 'execute']);
$router->get('/releases',             [App\Controllers\PublishController::class, 'releases']);
$router->get('/releases/{version}',   [App\Controllers\PublishController::class, 'show']);
$router->post('/releases/{version}/rollback', [App\Controllers\PublishController::class, 'rollback']);

try {
    $router->dispatch($request);
} catch (Throwable $e) {
    if (!Config::isProduction()) {
        Response::html('<pre style="padding:24px;font:13px/1.5 monospace;color:#b00">'
            . e($e->getMessage()) . "\n\n" . e($e->getTraceAsString()) . '</pre>', 500);
    }
    error_log('[mybingwa-admin] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if ($isApi) {
        Response::json(['error' => 'server_error'], 500);
    }
    Response::html('<!doctype html><meta charset="utf-8"><title>Error</title>'
        . '<div style="font-family:system-ui;padding:40px">Something went wrong. Please try again.</div>', 500);
}
