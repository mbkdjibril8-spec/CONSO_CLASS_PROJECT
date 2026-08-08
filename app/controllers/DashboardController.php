<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Role;
use App\Repositories\SubsidiaryRepository;
use App\Repositories\UserRepository;

/**
 * Accueil applicatif. Le contenu affiché dépend du rôle de l'utilisateur
 * connecté : vue groupe pour les rôles transverses, vue restreinte à la
 * filiale d'affectation pour préparateur/contrôleur.
 * Le tableau de bord CODIR complet (KPIs, alertes...) arrive en Phase 6 —
 * cet écran ne montre ici que des données réellement disponibles en base.
 */
class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $user = $this->currentUser();
        $subsidiaries = new SubsidiaryRepository();

        $data = [
            'title' => 'Tableau de bord',
            'user'  => $user,
        ];

        if ($user->isGroupLevel()) {
            $data['subsidiaryCount'] = $subsidiaries->count();
            $data['userCount'] = (new UserRepository())->countActive();
            $data['subsidiaries'] = $subsidiaries->all();
        } else {
            $data['mySubsidiary'] = $user->subsidiaryId ? $subsidiaries->findById($user->subsidiaryId) : null;
        }

        $this->view('dashboard/index', $data);
    }
}
