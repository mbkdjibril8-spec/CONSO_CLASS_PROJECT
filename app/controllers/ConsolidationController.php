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
use App\Services\ValidationService;

class ConsolidationController extends Controller
{
    private ReportingPeriodRepository $periods;
    private ConsolidationRunRepository $runs;

    public function __construct()
    {
        $this->periods = new ReportingPeriodRepository();
        $this->runs = new ConsolidationRunRepository();
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

        $summary = null;
        if ($run['status'] === 'completed') {
            $minorityTotals = (new MinorityInterestRepository())->totalsForRun($runId);
            $netIncomeFullAgg = (new ValidationService())->computeNetIncome($lineItems);
            $eqIncome = $lineItems['EQ_METHOD_INCOME'] ?? 0;
            $eqInvestment = $lineItems['EQ_METHOD_INVESTMENT'] ?? 0;

            $totalNetIncome = $netIncomeFullAgg + $eqIncome;
            $groupNetIncome = $totalNetIncome - $minorityTotals['net_income'];

            $totalAssetsExEquityMethod = ($lineItems['FIXED_ASSETS'] ?? 0) + ($lineItems['RECEIVABLES'] ?? 0)
                + ($lineItems['IC_RECEIVABLE'] ?? 0) + ($lineItems['CASH'] ?? 0);
            $totalAssets = $totalAssetsExEquityMethod + $eqInvestment;
            $totalLiabilities = ($lineItems['PAYABLES'] ?? 0) + ($lineItems['IC_PAYABLE'] ?? 0) + ($lineItems['FINANCIAL_DEBT'] ?? 0);
            // Dérivé de Actif - Passif - Minoritaires (et non Capital+Réserves+RN) pour
            // absorber l'écart de conversion des filiales en devise étrangère et
            // garantir l'équilibre exact du bilan consolidé — voir CONSOLIDATION_LOGIC.md.
            $groupEquity = $totalAssetsExEquityMethod - $totalLiabilities - $minorityTotals['equity'] + $eqInvestment;

            $summary = [
                'netIncomeFullAgg' => $netIncomeFullAgg,
                'eqIncome' => $eqIncome,
                'eqInvestment' => $eqInvestment,
                'totalNetIncome' => $totalNetIncome,
                'groupNetIncome' => $groupNetIncome,
                'minorityNetIncome' => $minorityTotals['net_income'],
                'totalAssets' => $totalAssets,
                'totalLiabilities' => $totalLiabilities,
                'groupEquity' => $groupEquity,
                'minorityEquity' => $minorityTotals['equity'],
            ];
        }

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
        ]);
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
            $request
        );

        Session::flash('success', 'Ajustement enregistré. Relancez la consolidation de cette période pour le prendre en compte.');
        $this->redirect('/consolidation/adjustments?period_id=' . $periodId);
    }
}
