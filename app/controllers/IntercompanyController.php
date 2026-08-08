<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\IntercompanyRepository;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;
use App\Services\IntercompanyService;

/**
 * Déclaration et consultation des soldes/flux intercompany. Un rôle groupe
 * voit toutes les déclarations de la période ; un rôle filiale ne voit que
 * celles qui l'impliquent (en tant que déclarant ou contrepartie).
 */
class IntercompanyController extends Controller
{
    private ReportingPeriodRepository $periods;
    private SubsidiaryRepository $subsidiaries;

    public function __construct()
    {
        $this->periods = new ReportingPeriodRepository();
        $this->subsidiaries = new SubsidiaryRepository();
    }

    public function index(Request $request): void
    {
        $periods = $this->periods->all();
        $selectedId = (int) $request->query('period_id', 0);
        $period = $selectedId ? $this->periods->findById($selectedId) : null;
        if (!$period && !empty($periods)) {
            $period = $periods[count($periods) - 1];
        }

        $user = $this->currentUser();
        $repo = new IntercompanyRepository();
        $rows = [];
        if ($period) {
            $rows = $user->isGroupLevel()
                ? $repo->forPeriod($period->id)
                : $repo->forSubsidiaryAndPeriod($user->subsidiaryId, $period->id);
        }

        $this->view('intercompany/index', [
            'title' => 'Intercompany',
            'periods' => $periods,
            'period' => $period,
            'rows' => $rows,
        ]);
    }

    public function createForm(Request $request): void
    {
        $user = $this->currentUser();
        $this->view('intercompany/form', [
            'title' => 'Déclarer un solde intercompany',
            'periods' => array_filter($this->periods->all(), fn ($p) => !$p->isClosed()),
            'subsidiaries' => array_filter($this->subsidiaries->all(true), fn ($s) => $s->id !== $user->subsidiaryId),
            'errors' => [],
            'values' => ['period_id' => '', 'counterparty_subsidiary_id' => '', 'type' => 'receivable', 'amount_local' => ''],
        ]);
    }

    public function store(Request $request): void
    {
        $user = $this->currentUser();
        $periodId = (int) $request->input('period_id');
        $counterpartyId = (int) $request->input('counterparty_subsidiary_id');
        $type = (string) $request->input('type');
        $amountLocal = (float) $request->input('amount_local', 0);

        [$ok, $error] = (new IntercompanyService())->declare(
            $user->subsidiaryId,
            $periodId,
            $counterpartyId,
            $type,
            $amountLocal,
            $user,
            $request
        );

        if (!$ok) {
            $this->view('intercompany/form', [
                'title' => 'Déclarer un solde intercompany',
                'periods' => array_filter($this->periods->all(), fn ($p) => !$p->isClosed()),
                'subsidiaries' => array_filter($this->subsidiaries->all(true), fn ($s) => $s->id !== $user->subsidiaryId),
                'errors' => ['_global' => $error],
                'values' => $request->all(),
            ]);
            return;
        }

        Session::flash('success', 'Déclaration intercompany enregistrée.');
        $this->redirect('/intercompany?period_id=' . $periodId);
    }
}
