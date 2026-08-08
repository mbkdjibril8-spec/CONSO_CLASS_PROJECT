<?php

/**
 * Point d'entrée unique de l'application (front controller).
 * Toutes les requêtes HTTP passent par ce fichier (voir public/.htaccess).
 */

declare(strict_types=1);

// --- Autoload maison (PSR-4 simplifié, sans dépendance Composer) ----------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    $className = array_pop($parts);
    $dir = strtolower(implode('/', $parts));
    $path = __DIR__ . '/../app/' . $dir . '/' . $className . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/../app/helpers/helpers.php';

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone']);
error_reporting($config['app']['debug'] ? E_ALL : 0);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');

use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\SubsidiaryController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AuthorizationMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\Role;

Session::start();

$auth = new AuthMiddleware();
$csrf = new CsrfMiddleware();

$router = new Router();

// --- Authentification -------------------------------------------------
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], [$csrf]);
$router->post('/logout', [AuthController::class, 'logout'], [$auth, $csrf]);

// --- Tableau de bord (accueil authentifié, contenu adapté au rôle) ----
$router->get('/dashboard', [DashboardController::class, 'index'], [$auth]);

// --- Filiales (lecture seule en Phase 1 — CRUD complet en Phase 2) ----
// Tous les rôles authentifiés peuvent consulter une fiche filiale, mais
// AuthorizationMiddleware::subsidiaryScope restreint préparateur/contrôleur
// à leur propre filiale.
$router->get('/subsidiaries/{id}', [SubsidiaryController::class, 'show'], [
    $auth,
    AuthorizationMiddleware::role([
        Role::GROUP_ADMIN,
        Role::PREPARER,
        Role::SUBSIDIARY_CONTROLLER,
        Role::CONSOLIDATION_MANAGER,
        Role::CFO_READONLY,
    ]),
    AuthorizationMiddleware::subsidiaryScope('id'),
]);

$request = new Request();
$router->dispatch($request);
