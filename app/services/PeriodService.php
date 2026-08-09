<?php

namespace App\Services;

use App\Core\Request;
use App\Models\ReportingPeriod;
use App\Models\User;
use App\Repositories\ReportingPeriodRepository;

/**
 * Cycle de vie des périodes de reporting. La séquence est strictement
 * ordonnée et ne peut avancer que d'un cran à la fois (pas de saut, pas de
 * retour en arrière) : Open -> In Progress -> Submitted -> Under Review ->
 * Validated -> Consolidated -> Closed. Une période clôturée est définitive.
 */
class PeriodService
{
    private ReportingPeriodRepository $periods;
    private AuditService $audit;

    public function __construct()
    {
        $this->periods = new ReportingPeriodRepository();
        $this->audit = new AuditService();
    }

    /**
     * Fait avancer la période au statut suivant de la séquence.
     * @throws \RuntimeException si la transition demandée n'est pas la suivante autorisée.
     */
    public function advance(ReportingPeriod $period, string $requestedStatus, User $actor, Request $request): void
    {
        $expected = $period->nextStatus();

        if ($expected === null || $requestedStatus !== $expected) {
            throw new \RuntimeException(
                "Transition invalide : la période {$period->label} est en statut « {$period->status} », " .
                "seule la transition vers « {$expected} » est autorisée."
            );
        }

        $this->periods->updateStatus($period->id, $expected);
        $this->audit->logChange(
            $actor,
            'period_transition',
            'reporting_period',
            $period->id,
            ['status' => $period->status],
            ['status' => $expected],
            $request,
            null,
            $period->id
        );
    }
}
