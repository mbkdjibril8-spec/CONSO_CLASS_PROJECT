<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\User;
use App\Services\AuditService;

/**
 * Autorisation à deux niveaux exigée par le cahier des charges :
 *  - rôle : la route n'est accessible qu'à une liste de rôles autorisés ;
 *  - portée filiale : un utilisateur "filiale" (préparateur, contrôleur) ne
 *    peut atteindre que les ressources de SA propre filiale.
 * Chaque refus est journalisé dans audit_logs avant l'affichage du 403.
 */
class AuthorizationMiddleware
{
    /** @param string[] $roles codes de rôles autorisés pour cette route */
    public static function role(array $roles): callable
    {
        return function (Request $request) use ($roles): bool {
            $user = Session::get('user');

            if (!$user instanceof User || !in_array($user->roleCode, $roles, true)) {
                self::deny($user instanceof User ? $user : null, $request, 'Rôle non autorisé pour cette route');
                return false;
            }

            return true;
        };
    }

    /**
     * Vérifie que l'utilisateur a le droit d'accéder à la filiale identifiée
     * par le paramètre de route $paramName (ex: /subsidiaries/{id}).
     */
    public static function subsidiaryScope(string $paramName = 'id'): callable
    {
        return function (Request $request) use ($paramName): bool {
            $user = Session::get('user');
            $subsidiaryId = (int) $request->param($paramName);

            if (!$user instanceof User || !$user->canAccessSubsidiary($subsidiaryId)) {
                self::deny(
                    $user instanceof User ? $user : null,
                    $request,
                    "Portée filiale refusée (subsidiary_id={$subsidiaryId})"
                );
                return false;
            }

            return true;
        };
    }

    private static function deny(?User $user, Request $request, string $reason): void
    {
        (new AuditService())->logUnauthorizedAccess($user, $request, $reason);
        http_response_code(403);
        View::render('errors/403', []);
        exit;
    }
}
