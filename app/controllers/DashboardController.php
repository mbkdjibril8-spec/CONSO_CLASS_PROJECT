<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;
use App\Repositories\UserRepository;
use App\Services\ReportingService;

/**
 * Tableau de bord. Vue CODIR (KPIs, tendance, contribution par filiale,
 * alertes) pour les rôles groupe, filtrable par période/filiale/pays ;
 * vue restreinte à sa propre filiale pour préparateur/contrôleur.
 * Toutes les données proviennent de requêtes réelles (ReportingService) —
 * aucune valeur en dur.
 */
class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $user = $this->currentUser();
        $subsidiaryRepo = new SubsidiaryRepository();
        $periodRepo = new ReportingPeriodRepository();
        $reporting = new ReportingService();

        $periods = $periodRepo->all();
        $selectedPeriodId = (int) $request->query('period_id', 0);
        $period = $selectedPeriodId ? $periodRepo->findById($selectedPeriodId) : null;
        if (!$period && !empty($periods)) {
            $period = $periods[count($periods) - 1];
        }

        $data = [
            'title' => 'Tableau de bord',
            'user' => $user,
            'periods' => $periods,
            'period' => $period,
        ];

        if (!$period) {
            $this->view('dashboard/index', $data, $request->isAjax() ? null : 'layouts/main');
            return;
        }

        if ($user->isGroupLevel()) {
            $allSubsidiaries = $subsidiaryRepo->all(true);
            $fullMethodSubsidiaries = array_values(array_filter($allSubsidiaries, fn ($s) => $s->consolidationMethod === 'full'));
            $countries = array_values(array_unique(array_map(fn ($s) => $s->country, $fullMethodSubsidiaries)));

            $subsidiaryFilter = $request->query('subsidiary_id', '');
            $countryFilter = $request->query('country', '');

            $targetIds = array_map(fn ($s) => $s->id, $fullMethodSubsidiaries);
            if ($subsidiaryFilter !== '') {
                $targetIds = array_intersect($targetIds, [(int) $subsidiaryFilter]);
            } elseif ($countryFilter !== '') {
                $targetIds = array_map(fn ($s) => $s->id, array_values(array_filter($fullMethodSubsidiaries, fn ($s) => $s->country === $countryFilter)));
            }

            $data['subsidiaryCount'] = $subsidiaryRepo->count();
            $data['userCount'] = (new UserRepository())->countActive();
            $data['subsidiaries'] = $allSubsidiaries;
            $data['allSubsidiaries'] = $fullMethodSubsidiaries;
            $data['countries'] = $countries;
            $data['subsidiaryFilter'] = $subsidiaryFilter;
            $data['countryFilter'] = $countryFilter;

            $data['kpis'] = $reporting->kpis($period->id, $targetIds);
            $data['trend'] = $reporting->trend($period->year, $targetIds);
            $data['contribution'] = $subsidiaryFilter === '' ? $reporting->contributionBySubsidiary($period->id) : [];
            $data['alerts'] = $reporting->alerts($period->id);
        } else {
            $mySubsidiary = $user->subsidiaryId ? $subsidiaryRepo->findById($user->subsidiaryId) : null;
            $data['mySubsidiary'] = $mySubsidiary;

            if ($mySubsidiary && $mySubsidiary->consolidationMethod !== 'excluded') {
                $data['kpis'] = $reporting->kpis($period->id, [$mySubsidiary->id]);
                $data['trend'] = $reporting->trend($period->year, [$mySubsidiary->id]);
            }
        }

        $this->view('dashboard/index', $data, $request->isAjax() ? null : 'layouts/main');
    }
}
