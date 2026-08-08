<?php

namespace App\Services;

use App\Core\Request;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\Subsidiary;
use App\Models\User;
use App\Repositories\AccountRepository;
use App\Repositories\FinancialDataRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\WorkflowTransitionRepository;

/**
 * Cycle de vie d'un paquet filiale/période : draft -> submitted -> validated
 * ou submitted -> rejected -> (retour à l'édition) -> submitted -> ...
 * Le statut courant est déduit de la dernière transition enregistrée
 * (aucune colonne de statut dupliquée : source unique de vérité, cf.
 * même principe que reporting_periods mais à la maille filiale/période).
 */
class WorkflowService
{
    private WorkflowTransitionRepository $transitions;
    private AccountRepository $accounts;
    private FinancialDataRepository $financialData;
    private NotificationRepository $notifications;
    private AuditService $audit;

    public function __construct()
    {
        $this->transitions = new WorkflowTransitionRepository();
        $this->accounts = new AccountRepository();
        $this->financialData = new FinancialDataRepository();
        $this->notifications = new NotificationRepository();
        $this->audit = new AuditService();
    }

    public function currentStatus(int $subsidiaryId, int $periodId): string
    {
        return $this->transitions->currentStatus($subsidiaryId, $periodId);
    }

    /** La saisie n'est modifiable que si le paquet est à corriger (draft ou rejected) et la période non clôturée. */
    public function isEditable(int $subsidiaryId, int $periodId, ReportingPeriod $period): bool
    {
        if ($period->isClosed()) {
            return false;
        }
        return in_array($this->currentStatus($subsidiaryId, $periodId), ['draft', 'rejected'], true);
    }

    /** @return array{0: bool, 1: string|null} [succès, message d'erreur] */
    public function submit(Subsidiary $subsidiary, ReportingPeriod $period, User $actor, Request $request): array
    {
        $status = $this->currentStatus($subsidiary->id, $period->id);
        if (!in_array($status, ['draft', 'rejected'], true)) {
            return [false, 'Ce paquet a déjà été soumis pour cette période.'];
        }

        $accountsByCode = $this->accounts->allByCode();
        $stored = $this->financialData->forSubsidiaryPeriod($subsidiary->id, $period->id);
        $rawAmounts = [];
        foreach ($accountsByCode as $code => $account) {
            $rawAmounts[$code] = array_key_exists($account->id, $stored) ? (string) $stored[$account->id] : '';
        }
        $result = (new ValidationService())->validate(array_values($accountsByCode), $rawAmounts);
        if (!empty($result['errors'])) {
            $reason = $result['errors']['_balance'] ?? 'La saisie est incomplète ou invalide.';
            return [false, 'Impossible de soumettre : ' . $reason];
        }

        $this->transitions->record($subsidiary->id, $period->id, $status, 'submitted', $actor->id);
        $this->audit->logChange($actor, 'workflow_submit', 'financial_package', $subsidiary->id, ['status' => $status], ['status' => 'submitted'], $request);

        foreach ($this->notifications->userIdsForRole(Role::SUBSIDIARY_CONTROLLER, $subsidiary->id) as $userId) {
            $this->notifications->create(
                $userId,
                'submission',
                "Paquet {$subsidiary->name} — {$period->label} soumis pour validation.",
                'financial_package',
                $subsidiary->id
            );
        }

        return [true, null];
    }

    /** @return array{0: bool, 1: string|null} */
    public function validatePackage(Subsidiary $subsidiary, ReportingPeriod $period, User $actor, Request $request): array
    {
        $status = $this->currentStatus($subsidiary->id, $period->id);
        if ($status !== 'submitted') {
            return [false, 'Seul un paquet soumis peut être validé.'];
        }

        $this->transitions->record($subsidiary->id, $period->id, $status, 'validated', $actor->id);
        $this->audit->logChange($actor, 'workflow_validate', 'financial_package', $subsidiary->id, ['status' => $status], ['status' => 'validated'], $request);

        return [true, null];
    }

    /** @return array{0: bool, 1: string|null} */
    public function reject(Subsidiary $subsidiary, ReportingPeriod $period, User $actor, string $reason, Request $request): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return [false, 'Un motif de rejet est obligatoire.'];
        }

        $status = $this->currentStatus($subsidiary->id, $period->id);
        if ($status !== 'submitted') {
            return [false, 'Seul un paquet soumis peut être rejeté.'];
        }

        $this->transitions->record($subsidiary->id, $period->id, $status, 'rejected', $actor->id, $reason);
        $this->audit->logChange($actor, 'workflow_reject', 'financial_package', $subsidiary->id, ['status' => $status], ['status' => 'rejected', 'reason' => $reason], $request);

        foreach ($this->notifications->userIdsForRole(Role::PREPARER, $subsidiary->id) as $userId) {
            $this->notifications->create(
                $userId,
                'rejection',
                "Paquet {$subsidiary->name} — {$period->label} rejeté : {$reason}",
                'financial_package',
                $subsidiary->id
            );
        }

        return [true, null];
    }

    public function history(int $subsidiaryId, int $periodId): array
    {
        return $this->transitions->history($subsidiaryId, $periodId);
    }

    public function lastRejectionReason(int $subsidiaryId, int $periodId): ?string
    {
        return $this->transitions->lastRejectionReason($subsidiaryId, $periodId);
    }
}
