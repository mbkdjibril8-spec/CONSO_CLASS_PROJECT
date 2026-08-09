<?php

namespace App\Services;

use App\Core\Request;
use App\Models\ReportingPeriod;
use App\Models\User;
use App\Repositories\AccountRepository;
use App\Repositories\ConsolidationAdjustmentRepository;
use App\Repositories\ConsolidationLineItemRepository;
use App\Repositories\ConsolidationRunRepository;
use App\Repositories\EliminationRepository;
use App\Repositories\FinancialDataRepository;
use App\Repositories\IntercompanyRepository;
use App\Repositories\MinorityInterestRepository;
use App\Repositories\SubsidiaryRepository;

/**
 * Moteur de consolidation : périmètre -> conversion -> agrégation ->
 * éliminations intercos -> élimination des dividendes -> mise en
 * équivalence -> ajustements manuels -> intérêts minoritaires.
 * Chaque étape est journalisée dans consolidation_run_steps (traçabilité
 * exigée par le cahier des charges). Voir docs/CONSOLIDATION_LOGIC.md
 * pour le détail des formules et des choix retenus.
 */
class ConsolidationService
{
    private SubsidiaryRepository $subsidiaries;
    private AccountRepository $accounts;
    private FinancialDataRepository $financialData;
    private IntercompanyRepository $intercompany;
    private ConsolidationAdjustmentRepository $adjustments;
    private ConsolidationRunRepository $runs;
    private ConsolidationLineItemRepository $lineItems;
    private EliminationRepository $eliminations;
    private MinorityInterestRepository $minorityInterests;
    private CurrencyConversionService $conversion;
    private WorkflowService $workflow;
    private AuditService $audit;

    public function __construct()
    {
        $this->subsidiaries = new SubsidiaryRepository();
        $this->accounts = new AccountRepository();
        $this->financialData = new FinancialDataRepository();
        $this->intercompany = new IntercompanyRepository();
        $this->adjustments = new ConsolidationAdjustmentRepository();
        $this->runs = new ConsolidationRunRepository();
        $this->lineItems = new ConsolidationLineItemRepository();
        $this->eliminations = new EliminationRepository();
        $this->minorityInterests = new MinorityInterestRepository();
        $this->conversion = new CurrencyConversionService();
        $this->workflow = new WorkflowService();
        $this->audit = new AuditService();
    }

    /** @return array{0: bool, 1: int, 2: string|null} [succès, run_id, message d'erreur] */
    public function run(ReportingPeriod $period, User $actor, Request $request): array
    {
        $runId = $this->runs->create($period->id, $actor->id);
        $order = 0;

        // --- Étape 1 : vérification du périmètre --------------------------
        $stepId = $this->runs->addStep($runId, ++$order, 'Vérification du périmètre');
        $subsidiaries = array_values(array_filter(
            $this->subsidiaries->all(true),
            fn ($s) => in_array($s->consolidationMethod, ['full', 'equity'], true)
        ));
        $notValidated = [];
        foreach ($subsidiaries as $s) {
            if ($this->workflow->currentStatus($s->id, $period->id) !== 'validated') {
                $notValidated[] = $s->code;
            }
        }
        if (!empty($notValidated)) {
            $details = 'Paquets non validés : ' . implode(', ', $notValidated);
            $this->runs->completeStep($stepId, 'failed', $details);
            $this->runs->complete($runId, 'failed', $details);
            $this->audit->logChange($actor, 'consolidation_run_failed', 'consolidation_run', $runId, null, ['reason' => $details], $request);
            return [false, $runId, $details];
        }
        $fullMethod = array_values(array_filter($subsidiaries, fn ($s) => $s->consolidationMethod === 'full'));
        $equityMethod = array_values(array_filter($subsidiaries, fn ($s) => $s->consolidationMethod === 'equity'));
        $this->runs->completeStep($stepId, 'done', sprintf(
            '%d filiale(s) en intégration globale, %d en mise en équivalence, toutes validées.',
            count($fullMethod), count($equityMethod)
        ));

        // --- Étape 2 : conversion des devises + agrégation -----------------
        $stepId = $this->runs->addStep($runId, ++$order, 'Conversion des devises et agrégation');
        $rates = $this->conversion->ratesForPeriod($period->id);
        $enterableAccounts = $this->accounts->enterable(); // IS+BS+CF saisis par les filiales
        $aggregate = []; // code compte => montant XOF agrégé (intégration globale uniquement)
        $perSubsidiaryConverted = []; // subsidiary_id => [code => montant XOF] (pour les intérêts minoritaires)
        $missingRates = [];

        foreach ($fullMethod as $s) {
            $stored = $this->financialData->forSubsidiaryPeriod($s->id, $period->id);
            $accountsById = [];
            foreach ($enterableAccounts as $a) {
                $accountsById[$a->id] = $a;
            }
            $converted = [];
            foreach ($stored as $accountId => $amountLocal) {
                $account = $accountsById[$accountId] ?? null;
                if (!$account || $account->statementType === 'CF') {
                    continue; // le CF n'est pas consolidé (voir CONSOLIDATION_LOGIC.md)
                }
                $amountGroup = $this->conversion->convert($amountLocal, $s->currencyCode, $account->statementType, $rates);
                if ($amountGroup === null) {
                    $missingRates[] = "{$s->code} ({$s->currencyCode})";
                    continue;
                }
                $converted[$account->code] = $amountGroup;
                $aggregate[$account->code] = ($aggregate[$account->code] ?? 0) + $amountGroup;
            }
            $perSubsidiaryConverted[$s->id] = $converted;
        }

        if (!empty($missingRates)) {
            $details = 'Taux de change manquant pour : ' . implode(', ', array_unique($missingRates));
            $this->runs->completeStep($stepId, 'failed', $details);
            $this->runs->complete($runId, 'failed', $details);
            return [false, $runId, $details];
        }
        $this->runs->completeStep($stepId, 'done', sprintf(
            '%d filiale(s) converties (moyen pour le résultat, clôture pour le bilan) et agrégées.',
            count($fullMethod)
        ));

        // --- Étape 3 : éliminations intercompany ---------------------------
        $stepId = $this->runs->addStep($runId, ++$order, 'Éliminations intercompany');
        $perimeterIds = array_map(fn ($s) => $s->id, $fullMethod);
        $icRows = $this->intercompany->forPeriod($period->id);
        $unresolvedMismatch = 0;
        $processedPairs = []; // clé = paire triée [id_min, id_max] : dédoublonne quel que soit le côté rencontré en premier
        $eliminatedPairs = 0;

        foreach ($icRows as $row) {
            if ($row['type'] === 'dividend') {
                continue; // traité à l'étape dividendes
            }

            // La paire (déclarant + contrepartie) est comptée une seule fois dans les
            // compteurs narratifs, mais CHAQUE ligne applique sa propre élimination
            // (comptes différents : ex. IC_RECEIVABLE côté A, IC_PAYABLE côté B).
            $pairKey = $row['matched_transaction_id']
                ? implode('-', [min((int) $row['id'], (int) $row['matched_transaction_id']), max((int) $row['id'], (int) $row['matched_transaction_id'])])
                : 'unpaired-' . $row['id'];
            $isFirstInPair = !isset($processedPairs[$pairKey]);
            $processedPairs[$pairKey] = true;

            if ($row['match_status'] === 'mismatch') {
                if ($isFirstInPair) {
                    $unresolvedMismatch++;
                }
                continue; // seules les paires MATCHED sont éliminées automatiquement
            }
            if ($row['match_status'] !== 'matched') {
                continue; // pending : contrepartie pas encore déclarée
            }
            if (!in_array((int) $row['subsidiary_id'], $perimeterIds, true) || !in_array((int) $row['counterparty_subsidiary_id'], $perimeterIds, true)) {
                continue; // une des deux parties hors périmètre (ex: mise en équivalence)
            }
            if ($isFirstInPair) {
                $eliminatedPairs++;
            }

            $accountCode = match ($row['type']) {
                'receivable' => 'IC_RECEIVABLE', 'payable' => 'IC_PAYABLE',
                'revenue' => 'IC_REVENUE', 'expense' => 'IC_EXPENSE',
                default => null,
            };
            if ($accountCode === null || !isset($aggregate[$accountCode])) {
                continue;
            }
            $amount = (float) $row['amount_group'];
            $aggregate[$accountCode] -= $amount;
            $account = $this->accounts->allByCode()[$accountCode];
            $this->eliminations->create($runId, 'intercompany', (int) $row['id'], (int) $row['subsidiary_id'], $account->id, $amount);
        }
        $this->runs->completeStep($stepId, 'done', sprintf(
            '%d paire(s) intercompany éliminée(s). %s',
            $eliminatedPairs,
            $unresolvedMismatch > 0
                ? "{$unresolvedMismatch} écart(s) non résolu(s) laissé(s) en l'état (voir module Intercompany / ajustement manuel)."
                : 'Aucun écart non résolu.'
        ));

        // --- Étape 4 : élimination des dividendes intra-groupe -------------
        $stepId = $this->runs->addStep($runId, ++$order, 'Élimination des dividendes intra-groupe');
        $dividendEliminated = 0;
        foreach ($icRows as $row) {
            if ($row['type'] !== 'dividend') {
                continue;
            }
            if (!in_array((int) $row['subsidiary_id'], $perimeterIds, true) || !in_array((int) $row['counterparty_subsidiary_id'], $perimeterIds, true)) {
                continue; // dividende versé hors périmètre consolidé (ex: vers la holding) : rien à éliminer
            }
            $amount = (float) $row['amount_group'];
            if (isset($aggregate['FIN_INCOME'])) {
                $aggregate['FIN_INCOME'] -= $amount;
            }
            $account = $this->accounts->allByCode()['FIN_INCOME'];
            $this->eliminations->create($runId, 'dividend', (int) $row['id'], (int) $row['counterparty_subsidiary_id'], $account->id, $amount);
            $dividendEliminated++;
        }
        $this->runs->completeStep($stepId, 'done', $dividendEliminated > 0
            ? "{$dividendEliminated} dividende(s) intra-groupe éliminé(s)."
            : 'Aucun dividende entre filiales du périmètre à éliminer sur cette période.');

        // --- Étape 5 : mise en équivalence ----------------------------------
        $stepId = $this->runs->addStep($runId, ++$order, 'Mise en équivalence');
        $equityDetails = [];
        foreach ($equityMethod as $s) {
            $stored = $this->financialData->forSubsidiaryPeriod($s->id, $period->id);
            $accountsById = [];
            foreach ($enterableAccounts as $a) {
                $accountsById[$a->id] = $a;
            }
            $converted = [];
            foreach ($stored as $accountId => $amountLocal) {
                $account = $accountsById[$accountId] ?? null;
                if (!$account || $account->statementType === 'CF') {
                    continue;
                }
                $converted[$account->code] = $this->conversion->convert($amountLocal, $s->currencyCode, $account->statementType, $rates);
            }
            $netIncome = (new ValidationService())->computeNetIncome($converted);
            $equity = ($converted['SHARE_CAPITAL'] ?? 0) + ($converted['RETAINED_EARNINGS'] ?? 0) + $netIncome;

            $incomeShare = round($netIncome * $s->ownershipPct / 100, 2);
            $investmentShare = round($equity * $s->ownershipPct / 100, 2);

            $aggregate['EQ_METHOD_INCOME'] = ($aggregate['EQ_METHOD_INCOME'] ?? 0) + $incomeShare;
            $aggregate['EQ_METHOD_INVESTMENT'] = ($aggregate['EQ_METHOD_INVESTMENT'] ?? 0) + $investmentShare;
            $equityDetails[] = "{$s->code} : quote-part résultat " . format_amount($incomeShare) . ", titres " . format_amount($investmentShare) . " ({$s->ownershipPct}%)";
        }
        $this->runs->completeStep($stepId, 'done', empty($equityDetails)
            ? 'Aucune filiale en mise en équivalence dans le périmètre.'
            : implode(' | ', $equityDetails));

        // --- Étape 6 : ajustements de consolidation manuels -----------------
        $stepId = $this->runs->addStep($runId, ++$order, 'Ajustements de consolidation');
        $posted = $this->adjustments->postedForPeriod($period->id);
        foreach ($posted as $adj) {
            $sign = $adj['normal_balance'] === 'debit'
                ? ($adj['debit_credit'] === 'debit' ? 1 : -1)
                : ($adj['debit_credit'] === 'credit' ? 1 : -1);
            $aggregate[$adj['account_code']] = ($aggregate[$adj['account_code']] ?? 0) + $sign * (float) $adj['amount'];
        }
        $this->runs->completeStep($stepId, 'done', count($posted) > 0
            ? count($posted) . ' ajustement(s) manuel(s) appliqué(s).'
            : 'Aucun ajustement manuel posté pour cette période.');

        // --- Étape 7 : intérêts minoritaires ---------------------------------
        $stepId = $this->runs->addStep($runId, ++$order, 'Calcul des intérêts minoritaires');
        $miDetails = [];
        foreach ($fullMethod as $s) {
            if ($s->ownershipPct >= 100) {
                continue;
            }
            $converted = $perSubsidiaryConverted[$s->id];
            $netIncome = (new ValidationService())->computeNetIncome($converted);
            // Capitaux propres dérivés de Actif - Passif (et non Capital+Réserves+RN) :
            // pour une filiale en devise étrangère, le compte de résultat est converti
            // au taux moyen et le bilan au taux de clôture (exigence du cahier des
            // charges), ce qui génère un écart de conversion. Le dériver depuis
            // Actif-Passif l'absorbe automatiquement et garantit que le bilan
            // consolidé reste équilibré au centime près — voir CONSOLIDATION_LOGIC.md.
            $assetsSub = ($converted['FIXED_ASSETS'] ?? 0) + ($converted['RECEIVABLES'] ?? 0)
                + ($converted['IC_RECEIVABLE'] ?? 0) + ($converted['CASH'] ?? 0);
            $liabSub = ($converted['PAYABLES'] ?? 0) + ($converted['IC_PAYABLE'] ?? 0) + ($converted['FINANCIAL_DEBT'] ?? 0);
            $equity = $assetsSub - $liabSub;
            $minorityPct = round(100 - $s->ownershipPct, 2);

            $niShare = round($netIncome * $minorityPct / 100, 2);
            $eqShare = round($equity * $minorityPct / 100, 2);

            $this->minorityInterests->upsert($runId, $s->id, $minorityPct, $niShare, $eqShare);
            $miDetails[] = "{$s->code} ({$minorityPct}%) : résultat " . format_amount($niShare) . ', capitaux propres ' . format_amount($eqShare);
        }
        $this->runs->completeStep($stepId, 'done', empty($miDetails)
            ? 'Aucune filiale à intérêts minoritaires (toutes détenues à 100%).'
            : implode(' | ', $miDetails));

        // --- Persistance du résultat consolidé --------------------------------
        $accountsByCode = $this->accounts->allByCode();
        foreach ($aggregate as $code => $amount) {
            if (!isset($accountsByCode[$code])) {
                continue;
            }
            $this->lineItems->upsert($runId, $accountsByCode[$code]->id, round($amount, 2));
        }

        $this->runs->complete($runId, 'completed');
        $this->audit->logChange($actor, 'consolidation_run_completed', 'consolidation_run', $runId, null, ['period_id' => $period->id], $request);

        return [true, $runId, null];
    }
}
