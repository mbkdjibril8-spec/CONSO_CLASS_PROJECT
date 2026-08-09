<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AccountRepository;
use App\Repositories\ConsolidationAdjustmentRepository;
use App\Repositories\ConsolidationLineItemRepository;
use App\Repositories\ConsolidationRunRepository;
use App\Repositories\EliminationRepository;
use App\Repositories\ExchangeRateRepository;
use App\Repositories\MinorityInterestRepository;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;
use App\Services\AuditService;
use App\Services\ConsolidationService;

class ConsolidationController extends Controller
{
    private ReportingPeriodRepository $periods;
    private ConsolidationRunRepository $runs;
    private ConsolidationService $consolidation;

    public function __construct()
    {
        $this->periods = new ReportingPeriodRepository();
        $this->runs = new ConsolidationRunRepository();
        $this->consolidation = new ConsolidationService();
    }

    public function index(Request $request): void
    {
        $this->view('consolidation/index', [
            'title' => 'Consolidation',
            'periods' => $this->periods->all(),
            'runs' => $this->runs->all(),
        ]);
    }

    public function run(Request $request): void
    {
        $periodId = (int) $request->input('period_id');
        $period = $this->periods->findById($periodId);
        if (!$period) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Période introuvable']);
            return;
        }

        [$ok, $runId, $error] = (new ConsolidationService())->run($period, $this->currentUser(), $request);
        Session::flash($ok ? 'success' : 'error', $ok ? "Consolidation de {$period->label} terminée." : "Échec de la consolidation : {$error}");
        $this->redirect('/consolidation/' . $runId);
    }

    public function show(Request $request, string $id): void
    {
        $runId = (int) $id;
        $run = $this->runs->findById($runId);
        if (!$run) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Run introuvable']);
            return;
        }

        $lineItems = (new ConsolidationLineItemRepository())->forRun($runId);
        $accounts = (new AccountRepository())->allByCode();

        $summary = $run['status'] === 'completed' ? $this->consolidation->computeSummary($lineItems, $runId) : null;

        $this->view('consolidation/show', [
            'title' => 'Run de consolidation — ' . $run['period_label'],
            'run' => $run,
            'steps' => $this->runs->steps($runId),
            'lineItems' => $lineItems,
            'accounts' => $accounts,
            'summary' => $summary,
            'minorityInterests' => (new MinorityInterestRepository())->forRun($runId),
            'eliminations' => (new EliminationRepository())->forRun($runId),
            'rates' => (new ExchangeRateRepository())->forPeriod((int) $run['period_id']),
        ]);
    }

    /**
     * Liasse groupe complète : compte de résultat + bilan (actif/passif)
     * au format OHADA/SYCEBNL pour le dernier run terminé d'une période
     * choisie. Contrairement à /consolidation/{id} (détail technique d'un
     * run précis : étapes, éliminations, intérêts minoritaires), cet écran
     * est pensé comme le document de synthèse consultable directement
     * (sélecteur de période, export CSV/PDF), sans devoir connaître l'id
     * d'un run.
     */
    public function statements(Request $request): void
    {
        $latestCompletedByPeriod = [];
        foreach ($this->runs->all() as $r) {
            if ($r['status'] !== 'completed') {
                continue;
            }
            $pid = (int) $r['period_id'];
            if (!isset($latestCompletedByPeriod[$pid])) {
                $latestCompletedByPeriod[$pid] = $r; // all() trié par started_at DESC : premier = plus récent
            }
        }

        $selectedPeriodId = (int) $request->query('period_id', 0);
        if ($selectedPeriodId === 0 || !isset($latestCompletedByPeriod[$selectedPeriodId])) {
            $selectedPeriodId = $latestCompletedByPeriod !== [] ? (int) array_key_first($latestCompletedByPeriod) : 0;
        }

        $run = $selectedPeriodId !== 0 ? $latestCompletedByPeriod[$selectedPeriodId] : null;
        $lineItems = $run ? (new ConsolidationLineItemRepository())->forRun((int) $run['id']) : [];
        $summary = $run ? $this->consolidation->computeSummary($lineItems, (int) $run['id']) : null;

        $this->view('consolidation/statements', [
            'title' => 'Liasse groupe',
            'periodsWithRuns' => $latestCompletedByPeriod,
            'selectedPeriodId' => $selectedPeriodId,
            'run' => $run,
            'lineItems' => $lineItems,
            'summary' => $summary,
        ], $request->isAjax() ? null : 'layouts/main');
    }

    public function adjustmentsIndex(Request $request): void
    {
        $periods = $this->periods->all();
        $selectedId = (int) $request->query('period_id', 0);
        $period = $selectedId ? $this->periods->findById($selectedId) : null;
        if (!$period && !empty($periods)) {
            $period = $periods[count($periods) - 1];
        }

        $this->view('consolidation/adjustments', [
            'title' => 'Ajustements de consolidation',
            'periods' => $periods,
            'period' => $period,
            'adjustments' => $period ? (new ConsolidationAdjustmentRepository())->forPeriod($period->id) : [],
            'accounts' => (new AccountRepository())->all(),
            'subsidiaries' => (new SubsidiaryRepository())->all(true),
            'errors' => [],
        ], $request->isAjax() ? null : 'layouts/main');
    }

    public function adjustmentsStore(Request $request): void
    {
        $periodId = (int) $request->input('period_id');
        $period = $this->periods->findById($periodId);
        $accountId = (int) $request->input('account_id');
        $debitCredit = (string) $request->input('debit_credit');
        $amount = (float) $request->input('amount', 0);
        $reason = trim((string) $request->input('reason', ''));
        $subsidiaryId = $request->input('subsidiary_id', '') !== '' ? (int) $request->input('subsidiary_id') : null;

        $errors = [];
        if (!$period) {
            $errors['period_id'] = 'Période invalide.';
        } elseif ($period->isClosed()) {
            $errors['period_id'] = 'Cette période est clôturée.';
        }
        if ($amount <= 0) {
            $errors['amount'] = 'Le montant doit être positif.';
        }
        if (!in_array($debitCredit, ['debit', 'credit'], true)) {
            $errors['debit_credit'] = 'Sens invalide.';
        }
        if ($reason === '') {
            $errors['reason'] = 'Le motif est obligatoire.';
        }

        if (!empty($errors)) {
            $this->view('consolidation/adjustments', [
                'title' => 'Ajustements de consolidation',
                'periods' => $this->periods->all(),
                'period' => $period,
                'adjustments' => $period ? (new ConsolidationAdjustmentRepository())->forPeriod($period->id) : [],
                'accounts' => (new AccountRepository())->all(),
                'subsidiaries' => (new SubsidiaryRepository())->all(true),
                'errors' => $errors,
            ]);
            return;
        }

        $actor = $this->currentUser();
        $repo = new ConsolidationAdjustmentRepository();
        $id = $repo->create([
            'period_id' => $periodId,
            'subsidiary_id' => $subsidiaryId,
            'account_id' => $accountId,
            'debit_credit' => $debitCredit,
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'posted',
            'created_by' => $actor->id,
        ]);

        (new AuditService())->logChange(
            $actor, 'consolidation_adjustment_create', 'consolidation_adjustment', $id,
            null, ['period_id' => $periodId, 'account_id' => $accountId, 'debit_credit' => $debitCredit, 'amount' => $amount, 'reason' => $reason],
            $request, $subsidiaryId, $periodId
        );

        Session::flash('success', 'Ajustement enregistré. Relancez la consolidation de cette période pour le prendre en compte.');
        $this->redirect('/consolidation/adjustments?period_id=' . $periodId);
    }
}
