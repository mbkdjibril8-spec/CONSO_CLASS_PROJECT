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
use App\Controllers\ExchangeRateController;
use App\Controllers\FinancialDataController;
use App\Controllers\IntercompanyController;
use App\Controllers\PeriodController;
use App\Controllers\SubsidiaryController;
use App\Controllers\WorkflowController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AuthorizationMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\Role;

Session::start();

$auth = new AuthMiddleware();
$csrf = new CsrfMiddleware();

// Rôles ayant une visibilité groupe (structure, taux de change).
$groupRoles = AuthorizationMiddleware::role([
    Role::GROUP_ADMIN,
    Role::CONSOLIDATION_MANAGER,
    Role::CFO_READONLY,
]);
$adminOnly = AuthorizationMiddleware::role([Role::GROUP_ADMIN]);
$periodManagers = AuthorizationMiddleware::role([Role::GROUP_ADMIN, Role::CONSOLIDATION_MANAGER]);
$preparerOnly = AuthorizationMiddleware::role([Role::PREPARER]);
$controllerOnly = AuthorizationMiddleware::role([Role::SUBSIDIARY_CONTROLLER]);
$allRoles = AuthorizationMiddleware::role([
    Role::GROUP_ADMIN,
    Role::PREPARER,
    Role::SUBSIDIARY_CONTROLLER,
    Role::CONSOLIDATION_MANAGER,
    Role::CFO_READONLY,
]);

$router = new Router();

// --- Authentification -------------------------------------------------
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], [$csrf]);
$router->post('/logout', [AuthController::class, 'logout'], [$auth, $csrf]);

// --- Tableau de bord (accueil authentifié, contenu adapté au rôle) ----
$router->get('/dashboard', [DashboardController::class, 'index'], [$auth]);

// --- Filiales -----------------------------------------------------------
// Routes statiques déclarées AVANT la route dynamique {id} (le routeur
// retient la première correspondance).
$router->get('/subsidiaries', [SubsidiaryController::class, 'index'], [$auth, $groupRoles]);
$router->get('/subsidiaries/tree', [SubsidiaryController::class, 'tree'], [$auth, $groupRoles]);
$router->get('/subsidiaries/create', [SubsidiaryController::class, 'createForm'], [$auth, $adminOnly]);
$router->post('/subsidiaries', [SubsidiaryController::class, 'store'], [$auth, $adminOnly, $csrf]);

// Fiche filiale : accessible à tous les rôles authentifiés, mais restreinte
// à sa propre filiale pour préparateur/contrôleur (AuthorizationMiddleware::subsidiaryScope).
$router->get('/subsidiaries/{id}', [SubsidiaryController::class, 'show'], [$auth, $allRoles, AuthorizationMiddleware::subsidiaryScope('id')]);
$router->get('/subsidiaries/{id}/edit', [SubsidiaryController::class, 'editForm'], [$auth, $adminOnly]);
$router->post('/subsidiaries/{id}', [SubsidiaryController::class, 'update'], [$auth, $adminOnly, $csrf]);
$router->post('/subsidiaries/{id}/toggle-active', [SubsidiaryController::class, 'toggleActive'], [$auth, $adminOnly, $csrf]);

// --- Périodes de reporting -----------------------------------------------
$router->get('/periods', [PeriodController::class, 'index'], [$auth]);
$router->post('/periods/{id}/transition', [PeriodController::class, 'transition'], [$auth, $periodManagers, $csrf]);

// --- Taux de change --------------------------------------------------------
$router->get('/exchange-rates', [ExchangeRateController::class, 'index'], [$auth, $groupRoles]);
$router->post('/exchange-rates', [ExchangeRateController::class, 'store'], [$auth, $adminOnly, $csrf]);

// --- Données financières (IS/BS/CF) ---------------------------------------
// Consultation ouverte à tous les rôles autorisés sur la filiale ; la
// saisie (formulaire + import CSV) est réservée au Préparateur.
$router->get('/financial-data/{subsidiaryId}', [FinancialDataController::class, 'periodsIndex'], [$auth, $allRoles, AuthorizationMiddleware::subsidiaryScope('subsidiaryId')]);
$router->get('/financial-data/{subsidiaryId}/{periodId}', [FinancialDataController::class, 'show'], [$auth, $allRoles, AuthorizationMiddleware::subsidiaryScope('subsidiaryId')]);
$router->post('/financial-data/{subsidiaryId}/{periodId}', [FinancialDataController::class, 'save'], [$auth, $preparerOnly, AuthorizationMiddleware::subsidiaryScope('subsidiaryId'), $csrf]);
$router->post('/financial-data/{subsidiaryId}/{periodId}/import', [FinancialDataController::class, 'import'], [$auth, $preparerOnly, AuthorizationMiddleware::subsidiaryScope('subsidiaryId'), $csrf]);

// --- Workflow (soumission / validation / rejet) ---------------------------
$router->post('/financial-data/{subsidiaryId}/{periodId}/submit', [WorkflowController::class, 'submit'], [$auth, $preparerOnly, AuthorizationMiddleware::subsidiaryScope('subsidiaryId'), $csrf]);
$router->post('/financial-data/{subsidiaryId}/{periodId}/validate', [WorkflowController::class, 'validatePackage'], [$auth, $controllerOnly, AuthorizationMiddleware::subsidiaryScope('subsidiaryId'), $csrf]);
$router->post('/financial-data/{subsidiaryId}/{periodId}/reject', [WorkflowController::class, 'reject'], [$auth, $controllerOnly, AuthorizationMiddleware::subsidiaryScope('subsidiaryId'), $csrf]);

// --- Intercompany ------------------------------------------------------------
$router->get('/intercompany', [IntercompanyController::class, 'index'], [$auth, $allRoles]);
$router->get('/intercompany/create', [IntercompanyController::class, 'createForm'], [$auth, $preparerOnly]);
$router->post('/intercompany', [IntercompanyController::class, 'store'], [$auth, $preparerOnly, $csrf]);

$request = new Request();
$router->dispatch($request);
