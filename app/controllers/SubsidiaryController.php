<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\SubsidiaryRepository;

/**
 * Fiche filiale en lecture seule. La gestion complète (CRUD, arbre de
 * hiérarchie) arrive en Phase 2 ; cette route sert dès la Phase 1 à
 * démontrer l'isolation par filiale imposée par AuthorizationMiddleware.
 */
class SubsidiaryController extends Controller
{
    public function show(Request $request, string $id): void
    {
        $subsidiary = (new SubsidiaryRepository())->findById((int) $id);

        if (!$subsidiary) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Filiale introuvable']);
            return;
        }

        $this->view('subsidiaries/show', [
            'title' => $subsidiary->name,
            'subsidiary' => $subsidiary,
        ]);
    }
}
