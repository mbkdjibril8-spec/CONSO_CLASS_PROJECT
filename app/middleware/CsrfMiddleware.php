<?php

namespace App\Middleware;

use App\Core\Request;

/** Vérifie le jeton CSRF sur toute requête POST. */
class CsrfMiddleware
{
    public function __invoke(Request $request): bool
    {
        if ($request->isPost() && !csrf_verify($request->input('_csrf'))) {
            http_response_code(419);
            echo 'Jeton de sécurité invalide ou expiré. Veuillez recharger la page et réessayer.';
            exit;
        }

        return true;
    }
}
