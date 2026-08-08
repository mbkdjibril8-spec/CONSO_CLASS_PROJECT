<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Services\AuditService;

/**
 * Exige une session utilisateur active. Toute tentative d'accès sans
 * session valide est journalisée dans audit_logs (action = unauthorized_access).
 */
class AuthMiddleware
{
    public function __invoke(Request $request): bool
    {
        $user = Session::get('user');

        if (!$user instanceof User) {
            (new AuditService())->logUnauthorizedAccess(null, $request, 'Session non authentifiée');
            header('Location: ' . base_url('login'));
            exit;
        }

        return true;
    }
}
