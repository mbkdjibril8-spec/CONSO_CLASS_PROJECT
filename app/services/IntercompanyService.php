<?php

namespace App\Services;

use App\Core\Request;
use App\Models\IntercompanyTransaction;
use App\Models\Role;
use App\Models\User;
use App\Repositories\ExchangeRateRepository;
use App\Repositories\IntercompanyRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;

/**
 * Déclaration et rapprochement automatique des soldes/flux intercompany.
 * Voir docs/CONSOLIDATION_LOGIC.md pour la règle de conversion (taux de
 * clôture pour receivable/payable, taux moyen pour revenue/expense/dividend)
 * et la simplification retenue pour les dividendes (déclaration à sens
 * unique, NOVA Holding ne soumettant pas de paquet financier propre).
 */
class IntercompanyService
{
    private const MATCH_TOLERANCE = 0.01;

    private IntercompanyRepository $transactions;
    private SubsidiaryRepository $subsidiaries;
    private ExchangeRateRepository $rates;
    private NotificationRepository $notifications;
    private AuditService $audit;

    public function __construct()
    {
        $this->transactions = new IntercompanyRepository();
        $this->subsidiaries = new SubsidiaryRepository();
        $this->rates = new ExchangeRateRepository();
        $this->notifications = new NotificationRepository();
        $this->audit = new AuditService();
    }

    /** @return array{0: bool, 1: string|null} [succès, message d'erreur] */
    public function declare(
        int $subsidiaryId,
        int $periodId,
        int $counterpartySubsidiaryId,
        string $type,
        float $amountLocal,
        User $actor,
        Request $request
    ): array {
        if ($subsidiaryId === $counterpartySubsidiaryId) {
            return [false, 'Une filiale ne peut pas être sa propre contrepartie.'];
        }
        if ($amountLocal <= 0) {
            return [false, 'Le montant doit être positif.'];
        }

        $subsidiary = $this->subsidiaries->findById($subsidiaryId);
        if (!$subsidiary) {
            return [false, 'Filiale introuvable.'];
        }

        $amountGroup = $this->convertToGroupCurrency($subsidiary->currencyCode, $periodId, $type, $amountLocal);
        if ($amountGroup === null) {
            return [false, "Taux de change manquant pour {$subsidiary->currencyCode} sur cette période."];
        }

        $id = $this->transactions->create([
            'period_id' => $periodId,
            'subsidiary_id' => $subsidiaryId,
            'counterparty_subsidiary_id' => $counterpartySubsidiaryId,
            'type' => $type,
            'amount_local' => $amountLocal,
            'amount_group' => $amountGroup,
            'matched_transaction_id' => null,
            'match_status' => 'pending',
            'difference_amount' => 0,
            'created_by' => $actor->id,
        ]);

        $this->audit->logChange($actor, 'intercompany_declare', 'intercompany_transaction', $id, null, [
            'subsidiary_id' => $subsidiaryId, 'counterparty_subsidiary_id' => $counterpartySubsidiaryId,
            'type' => $type, 'amount_local' => $amountLocal, 'amount_group' => $amountGroup,
        ], $request, $subsidiaryId, $periodId);

        if ($type === 'dividend') {
            // Déclaration à sens unique : considérée rapprochée dès la saisie (voir CONSOLIDATION_LOGIC.md).
            $this->transactions->updateMatch($id, null, 'matched', 0);
            return [true, null];
        }

        $counterpartType = IntercompanyTransaction::counterpartType($type);
        $counterpart = $this->transactions->findCounterpart($periodId, $subsidiaryId, $counterpartySubsidiaryId, $counterpartType);

        if ($counterpart) {
            $difference = round(abs($amountGroup - $counterpart->amountGroup), 2);
            $status = $difference < self::MATCH_TOLERANCE ? 'matched' : 'mismatch';

            $this->transactions->updateMatch($id, $counterpart->id, $status, $difference);
            $this->transactions->updateMatch($counterpart->id, $id, $status, $difference);

            if ($status === 'mismatch') {
                $this->notifyMismatch($subsidiary->name, $subsidiaryId, $counterpartySubsidiaryId, $difference, $periodId);
            }
        }

        return [true, null];
    }

    private function convertToGroupCurrency(string $currencyCode, int $periodId, string $type, float $amountLocal): ?float
    {
        if ($currencyCode === 'XOF') {
            return $amountLocal;
        }

        $rateType = in_array($type, ['receivable', 'payable'], true) ? 'closing' : 'average';
        $rates = $this->rates->forPeriod($periodId);
        $rate = $rates[$currencyCode][$rateType] ?? null;

        return $rate !== null ? round($amountLocal * $rate, 2) : null;
    }

    private function notifyMismatch(string $subsidiaryName, int $subsidiaryId, int $counterpartyId, float $difference, int $periodId): void
    {
        $message = "Écart intercompany détecté impliquant {$subsidiaryName} : différence de " . format_amount($difference) . '.';
        $recipients = array_merge(
            $this->notifications->userIdsForRole(Role::SUBSIDIARY_CONTROLLER, $subsidiaryId),
            $this->notifications->userIdsForRole(Role::SUBSIDIARY_CONTROLLER, $counterpartyId),
            $this->notifications->userIdsForRole(Role::CONSOLIDATION_MANAGER)
        );
        foreach (array_unique($recipients) as $userId) {
            $this->notifications->create($userId, 'mismatch', $message, 'intercompany_transaction', $periodId);
        }
    }
}
