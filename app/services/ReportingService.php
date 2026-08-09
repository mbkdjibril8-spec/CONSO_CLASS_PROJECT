<?php

namespace App\Services;

use App\Repositories\BudgetRepository;
use App\Repositories\ConsolidationRunRepository;
use App\Models\ReportingPeriod;
use App\Repositories\FinancialDataRepository;
use App\Repositories\IntercompanyRepository;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;

/**
 * Agrège les données pour les dashboards (CODIR + filiale). Vision
 * "cumulée" (somme des filiales en intégration globale, hors élimination
 * interco et hors mise en équivalence) — distincte du résultat CONSOLIDÉ
 * officiel produit par un run (Phase 5). Utilisée pour les tendances et
 * classements qui n'ont pas toujours un run disponible pour chaque mois.
 * IMPORTANT : les montants filiale sont stockés en devise locale — toute
 * agrégation multi-filiale convertit d'abord chaque filiale en XOF (taux
 * moyen, cohérent avec la convention IS) avant de sommer, sous peine de
 * mélanger XOF/EUR/MAD. Voir docs/CONSOLIDATION_LOGIC.md §Dashboards.
 */
class ReportingService
{
    private const LARGE_VARIANCE_THRESHOLD_PCT = 15.0;

    private FinancialDataRepository $financialData;
    private BudgetRepository $budgets;
    private SubsidiaryRepository $subsidiaries;
    private IntercompanyRepository $intercompany;
    private ConsolidationRunRepository $runs;
    private WorkflowService $workflow;
    private ValidationService $validation;
    private BudgetVarianceService $variance;
    private CurrencyConversionService $conversion;

    public function __construct()
    {
        $this->financialData = new FinancialDataRepository();
        $this->budgets = new BudgetRepository();
        $this->subsidiaries = new SubsidiaryRepository();
        $this->intercompany = new IntercompanyRepository();
        $this->runs = new ConsolidationRunRepository();
        $this->workflow = new WorkflowService();
        $this->validation = new ValidationService();
        $this->variance = new BudgetVarianceService();
        $this->conversion = new CurrencyConversionService();
    }

    /** Filiales en intégration globale (seules incluses dans la vision cumulée). */
    public function fullMethodSubsidiaryIds(): array
    {
        return array_map(fn ($s) => $s->id, array_values(array_filter(
            $this->subsidiaries->all(true),
            fn ($s) => $s->consolidationMethod === 'full'
        )));
    }

    /**
     * Convertit et agrège en XOF les montants IS (par compte) d'un ensemble
     * de filiales pour une période, à partir de données groupées par
     * filiale (financial_data ou budgets).
     * @param array<int, array<string, float>> $groupedBySubsidiary
     * @param int[] $subsidiaryIds
     * @return array<string, float>
     */
    private function convertAndSum(array $groupedBySubsidiary, array $subsidiaryIds, array $rates): array
    {
        $subsidiariesById = [];
        foreach ($this->subsidiaries->all(true) as $s) {
            $subsidiariesById[$s->id] = $s;
        }

        $totals = [];
        foreach ($subsidiaryIds as $sid) {
            if (!isset($groupedBySubsidiary[$sid], $subsidiariesById[$sid])) {
                continue;
            }
            $currency = $subsidiariesById[$sid]->currencyCode;
            foreach ($groupedBySubsidiary[$sid] as $code => $amountLocal) {
                $amountXof = $this->conversion->convert($amountLocal, $currency, 'IS', $rates);
                if ($amountXof === null) {
                    continue; // taux manquant : exclu du cumul (pas d'estimation fictive)
                }
                $totals[$code] = ($totals[$code] ?? 0) + $amountXof;
            }
        }
        return $totals;
    }

    /**
     * KPIs Actual/Budget/Écart (en XOF) pour un ensemble de filiales et une période.
     * @param int[] $subsidiaryIds
     */
    public function kpis(int $periodId, array $subsidiaryIds): array
    {
        $rates = $this->conversion->ratesForPeriod($periodId);
        $actual = $this->convertAndSum($this->financialData->isAmountsGroupedBySubsidiary($periodId), $subsidiaryIds, $rates);
        $budget = $this->convertAndSum($this->budgets->isAmountsGroupedBySubsidiary($periodId), $subsidiaryIds, $rates);

        return [
            'revenue' => $this->variance->compute($this->validation->computeRevenue($actual), $this->validation->computeRevenue($budget), true),
            'ebitda' => $this->variance->compute($this->validation->computeEbitda($actual), $this->validation->computeEbitda($budget), true),
            'netIncome' => $this->variance->compute($this->validation->computeNetIncome($actual), $this->validation->computeNetIncome($budget), true),
        ];
    }

    /**
     * Tendance 12 mois (revenu, EBITDA, résultat net, en XOF) pour un ensemble de filiales.
     * @param int[] $subsidiaryIds
     * @return array<int, array{period: \App\Models\ReportingPeriod, revenue: float, ebitda: float, netIncome: float}>
     */
    public function trend(int $year, array $subsidiaryIds): array
    {
        $periods = (new ReportingPeriodRepository())->all();
        $byYear = array_values(array_filter($periods, fn ($p) => $p->year === $year));

        $rows = [];
        foreach ($byYear as $p) {
            $rates = $this->conversion->ratesForPeriod($p->id);
            $grouped = $this->financialData->isAmountsGroupedBySubsidiary($p->id);
            $amounts = $this->convertAndSum($grouped, $subsidiaryIds, $rates);
            $rows[] = [
                'period' => $p,
                'revenue' => $this->validation->computeRevenue($amounts),
                'ebitda' => $this->validation->computeEbitda($amounts),
                'netIncome' => $this->validation->computeNetIncome($amounts),
            ];
        }
        return $rows;
    }

    /**
     * Contribution EBITDA par filiale pour une période (classement top/bottom), en XOF.
     * @return array<int, array{subsidiary: \App\Models\Subsidiary, ebitda: float}>
     */
    public function contributionBySubsidiary(int $periodId): array
    {
        $rates = $this->conversion->ratesForPeriod($periodId);
        $grouped = $this->financialData->isAmountsGroupedBySubsidiary($periodId);
        $subsidiariesById = [];
        foreach ($this->subsidiaries->all(true) as $s) {
            $subsidiariesById[$s->id] = $s;
        }

        $rows = [];
        foreach ($this->fullMethodSubsidiaryIds() as $sid) {
            if (!isset($subsidiariesById[$sid])) {
                continue;
            }
            $converted = $this->convertAndSum($grouped, [$sid], $rates);
            $rows[] = [
                'subsidiary' => $subsidiariesById[$sid],
                'ebitda' => $this->validation->computeEbitda($converted),
            ];
        }
        usort($rows, fn ($a, $b) => $b['ebitda'] <=> $a['ebitda']);
        return $rows;
    }

    /**
     * Détail Budget vs Actual par compte IS, pour le mois sélectionné et en
     * cumul depuis janvier (YTD), en XOF. Utilisé par l'écran dédié
     * Budget vs Actual (item §2.10 du cahier des charges).
     * @param int[] $subsidiaryIds
     * @return array{month: array<string, array>, ytd: array<string, array>}
     */
    public function budgetVsActualDetail(ReportingPeriod $period, array $subsidiaryIds): array
    {
        $periods = (new ReportingPeriodRepository())->all();
        $yearMonths = array_values(array_filter($periods, fn ($p) => $p->year === $period->year && $p->month <= $period->month));

        $monthActual = $this->convertAndSum($this->financialData->isAmountsGroupedBySubsidiary($period->id), $subsidiaryIds, $this->conversion->ratesForPeriod($period->id));
        $monthBudget = $this->convertAndSum($this->budgets->isAmountsGroupedBySubsidiary($period->id), $subsidiaryIds, $this->conversion->ratesForPeriod($period->id));

        $ytdActual = [];
        $ytdBudget = [];
        foreach ($yearMonths as $p) {
            $rates = $this->conversion->ratesForPeriod($p->id);
            foreach ($this->convertAndSum($this->financialData->isAmountsGroupedBySubsidiary($p->id), $subsidiaryIds, $rates) as $code => $amount) {
                $ytdActual[$code] = ($ytdActual[$code] ?? 0) + $amount;
            }
            foreach ($this->convertAndSum($this->budgets->isAmountsGroupedBySubsidiary($p->id), $subsidiaryIds, $rates) as $code => $amount) {
                $ytdBudget[$code] = ($ytdBudget[$code] ?? 0) + $amount;
            }
        }

        $codes = ['REV', 'IC_REVENUE', 'COGS', 'OPEX_PERS', 'OPEX_OTHER', 'IC_EXPENSE', 'DA', 'FIN_INCOME', 'FIN_EXPENSE', 'TAX'];
        $expenseCodes = ['COGS', 'OPEX_PERS', 'OPEX_OTHER', 'IC_EXPENSE', 'DA', 'FIN_EXPENSE', 'TAX'];

        $build = function (array $actual, array $budget) use ($codes, $expenseCodes) {
            $rows = [];
            foreach ($codes as $code) {
                $higherIsFavorable = !in_array($code, $expenseCodes, true);
                $rows[$code] = $this->variance->compute($actual[$code] ?? 0, $budget[$code] ?? 0, $higherIsFavorable);
            }
            $rows['REVENUE_TOTAL'] = $this->variance->compute($this->validation->computeRevenue($actual), $this->validation->computeRevenue($budget), true);
            $rows['EBITDA_TOTAL'] = $this->variance->compute($this->validation->computeEbitda($actual), $this->validation->computeEbitda($budget), true);
            $rows['NET_INCOME_TOTAL'] = $this->variance->compute($this->validation->computeNetIncome($actual), $this->validation->computeNetIncome($budget), true);
            return $rows;
        };

        return [
            'month' => $build($monthActual, $monthBudget),
            'ytd' => $build($ytdActual, $ytdBudget),
        ];
    }

    /**
     * Alertes du dashboard CODIR : soumissions manquantes, écarts importants,
     * mismatchs intercompany, disponibilité d'un run de consolidation.
     * Les écarts vs budget sont comparés en devise locale (ratio invariant
     * par devise) : pas de conversion nécessaire pour cette vérification.
     */
    public function alerts(int $periodId): array
    {
        $alerts = [];
        $subsidiariesById = [];
        foreach ($this->subsidiaries->all(true) as $s) {
            $subsidiariesById[$s->id] = $s;
        }

        $inScope = array_values(array_filter(
            $this->subsidiaries->all(true),
            fn ($s) => in_array($s->consolidationMethod, ['full', 'equity'], true)
        ));

        $notValidated = [];
        foreach ($inScope as $s) {
            $status = $this->workflow->currentStatus($s->id, $periodId);
            if ($status !== 'validated') {
                $notValidated[] = ['subsidiary' => $s, 'status' => $status];
            }
        }
        if (!empty($notValidated)) {
            $alerts[] = [
                'type' => 'missing_submission',
                'severity' => 'warning',
                'message' => count($notValidated) . ' paquet(s) non validé(s) : ' . implode(', ', array_map(fn ($n) => $n['subsidiary']->code . ' (' . workflow_status_label($n['status']) . ')', $notValidated)),
            ];
        }

        $grouped = $this->financialData->isAmountsGroupedBySubsidiary($periodId);
        foreach ($this->fullMethodSubsidiaryIds() as $sid) {
            $actualRevenue = $this->validation->computeRevenue($grouped[$sid] ?? []);
            $budget = $this->budgets->forSubsidiaryPeriod($sid, $periodId);
            $budgetRevenue = $this->validation->computeRevenue($budget);
            $v = $this->variance->compute($actualRevenue, $budgetRevenue, true);
            if ($v['variancePct'] !== null && !$v['favorable'] && abs($v['variancePct']) >= self::LARGE_VARIANCE_THRESHOLD_PCT) {
                $alerts[] = [
                    'type' => 'large_variance',
                    'severity' => 'warning',
                    'message' => "{$subsidiariesById[$sid]->code} : chiffre d'affaires " . number_format($v['variancePct'], 1, ',', ' ') . '% vs budget.',
                ];
            }
        }

        $mismatches = array_filter($this->intercompany->forPeriod($periodId), fn ($r) => $r['match_status'] === 'mismatch');
        $countedPairs = [];
        foreach ($mismatches as $m) {
            $key = $m['matched_transaction_id'] ? min((int) $m['id'], (int) $m['matched_transaction_id']) . '-' . max((int) $m['id'], (int) $m['matched_transaction_id']) : 'u' . $m['id'];
            $countedPairs[$key] = true;
        }
        if (!empty($countedPairs)) {
            $alerts[] = [
                'type' => 'mismatch',
                'severity' => 'critical',
                'message' => count($countedPairs) . ' écart(s) intercompany non résolu(s) sur cette période.',
            ];
        }

        if (empty($notValidated) && !$this->runs->latestCompletedForPeriod($periodId)) {
            $alerts[] = [
                'type' => 'ready_to_consolidate',
                'severity' => 'info',
                'message' => 'Toutes les filiales du périmètre sont validées : la consolidation peut être lancée.',
            ];
        }

        return $alerts;
    }
}
