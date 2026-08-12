<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\AccountRepository;
use App\Repositories\ConsolidationLineItemRepository;
use App\Repositories\ConsolidationRunRepository;
use App\Repositories\FinancialDataRepository;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;
use App\Services\ConsolidationService;
use App\Services\ReportingService;

/**
 * Export CSV (ouvrable dans Excel) des états consolidés, des paquets
 * filiale et de la vue dashboard courante — §2.14 du cahier des charges.
 * CSV plutôt que XLSX natif : aucune dépendance Composer requise pour
 * exécuter le projet (contrainte de la stack), et CSV est explicitement
 * un des deux formats acceptés par le cahier des charges.
 */
class ExportController extends Controller
{
    public function consolidationRun(Request $request, string $id): void
    {
        $runId = (int) $id;
        $run = (new ConsolidationRunRepository())->findById($runId);
        if (!$run || $run['status'] !== 'completed') {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Run introuvable ou non terminé']);
            return;
        }

        $lineItems = (new ConsolidationLineItemRepository())->forRun($runId);
        $accounts = (new AccountRepository())->allByCode();

        $rows = [['Code compte', 'Libellé', 'État financier', 'Montant XOF']];
        foreach ($accounts as $code => $account) {
            if (!isset($lineItems[$code])) {
                continue;
            }
            $rows[] = [$code, $account->label, $account->statementType, number_format($lineItems[$code], 2, ',', '')];
        }

        stream_csv_download('consolidation_' . $run['period_label'] . '.csv', $rows);
    }

    /**
     * Export CSV de la liasse groupe au format OHADA/SYCEBNL (compte de
     * résultat + bilan actif + bilan passif), tel qu'affiché sur l'écran
     * "Liasse groupe" (/financial-statements) — distinct de
     * consolidationRun() ci-dessus qui exporte les montants bruts par
     * compte interne, pas la présentation normalisée. Réutilise les mêmes
     * définitions de lignes que l'affichage écran (app/helpers/ohada.php)
     * pour que l'export ne puisse jamais diverger de ce qui est montré.
     */
    public function financialStatements(Request $request, string $runId): void
    {
        $runId = (int) $runId;
        $run = (new ConsolidationRunRepository())->findById($runId);
        if (!$run || $run['status'] !== 'completed') {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Run introuvable ou non terminé']);
            return;
        }

        $lineItems = (new ConsolidationLineItemRepository())->forRun($runId);
        $summary = (new ConsolidationService())->computeSummary($lineItems, $runId);
        $netIncome = $summary['netIncomeFullAgg'];

        $addSection = function (array &$rows, string $title, array $ohadaRows, array $values): void {
            $rows[] = [$title];
            $rows[] = ['Référence', 'Libellé', 'Montant XOF'];
            foreach ($ohadaRows as [$ref, $label, $kind]) {
                $prefix = $kind === 'total' ? '= ' : ($kind === 'subtotal' ? '- ' : '');
                $rows[] = [$ref, $prefix . $label, number_format($values[$ref] ?? 0.0, 2, ',', '')];
            }
            $rows[] = [];
        };

        $rows = [];
        $addSection($rows, 'COMPTE DE RESULTAT CONSOLIDE — ' . $run['period_label'], ohada_income_statement_rows(), ohada_income_statement_values($lineItems));
        $balanceValues = ohada_balance_sheet_values($lineItems, $netIncome);
        $addSection($rows, 'BILAN CONSOLIDE — ACTIF', ohada_balance_sheet_actif_rows(), $balanceValues);
        $addSection($rows, 'BILAN CONSOLIDE — PASSIF', ohada_balance_sheet_passif_rows(), $balanceValues);

        stream_csv_download('liasse_groupe_' . $run['period_label'] . '.csv', $rows);
    }

    public function subsidiaryPackage(Request $request, string $subsidiaryId, string $periodId): void
    {
        $subsidiary = (new SubsidiaryRepository())->findById((int) $subsidiaryId);
        $period = (new ReportingPeriodRepository())->findById((int) $periodId);
        if (!$subsidiary || !$period) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Introuvable']);
            return;
        }

        $accounts = (new AccountRepository())->enterable();
        $stored = (new FinancialDataRepository())->forSubsidiaryPeriod($subsidiary->id, $period->id);

        $rows = [['Code compte', 'Libellé', 'État financier', 'Montant (' . $subsidiary->currencyCode . ')']];
        foreach ($accounts as $account) {
            $amount = $stored[$account->id] ?? null;
            $rows[] = [$account->code, $account->label, $account->statementType, $amount !== null ? number_format($amount, 2, ',', '') : ''];
        }

        stream_csv_download("paquet_{$subsidiary->code}_{$period->label}.csv", $rows);
    }

    public function dashboard(Request $request): void
    {
        $user = $this->currentUser();
        $periodRepo = new ReportingPeriodRepository();
        $periods = $periodRepo->all();
        $selectedPeriodId = (int) $request->query('period_id', 0);
        $period = $selectedPeriodId ? $periodRepo->findById($selectedPeriodId) : null;
        if (!$period && !empty($periods)) {
            $period = $periods[count($periods) - 1];
        }
        if (!$period) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Aucune période disponible']);
            return;
        }

        $subsidiaryRepo = new SubsidiaryRepository();
        $reporting = new ReportingService();

        if ($user->isGroupLevel()) {
            $subsidiaryFilter = $request->query('subsidiary_id', '');
            $countryFilter = $request->query('country', '');
            $fullMethodSubsidiaries = array_values(array_filter($subsidiaryRepo->all(true), fn ($s) => $s->consolidationMethod === 'full'));
            $targetIds = array_map(fn ($s) => $s->id, $fullMethodSubsidiaries);
            if ($subsidiaryFilter !== '') {
                $targetIds = array_intersect($targetIds, [(int) $subsidiaryFilter]);
            } elseif ($countryFilter !== '') {
                $targetIds = array_map(fn ($s) => $s->id, array_values(array_filter($fullMethodSubsidiaries, fn ($s) => $s->country === $countryFilter)));
            }
        } else {
            $targetIds = $user->subsidiaryId ? [$user->subsidiaryId] : [];
        }

        $kpis = !empty($targetIds) ? $reporting->kpis($period->id, $targetIds) : null;

        $rows = [['Indicateur', 'Réel (XOF)', 'Budget (XOF)', 'Écart (XOF)', 'Écart %']];
        if ($kpis) {
            $labels = ["Chiffre d'affaires" => 'revenue', 'EBITDA' => 'ebitda', 'Résultat net' => 'netIncome'];
            foreach ($labels as $label => $key) {
                $k = $kpis[$key];
                $rows[] = [
                    $label,
                    number_format($k['actual'], 2, ',', ''),
                    number_format($k['budget'], 2, ',', ''),
                    number_format($k['variance'], 2, ',', ''),
                    $k['variancePct'] !== null ? number_format($k['variancePct'], 1, ',', '') . '%' : '',
                ];
            }
        }

        stream_csv_download('dashboard_' . $period->label . '.csv', $rows);
    }
}
