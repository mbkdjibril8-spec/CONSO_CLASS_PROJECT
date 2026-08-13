<?php

namespace App\Services;

use App\Core\Request;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\User;
use App\Repositories\ExchangeRateRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\ReportingPeriodRepository;

/**
 * Cycle de vie des périodes de reporting. La séquence est strictement
 * ordonnée et ne peut avancer que d'un cran à la fois (pas de saut, pas de
 * retour en arrière) : Open -> In Progress -> Submitted -> Under Review ->
 * Validated -> Consolidated -> Closed. Une période clôturée est définitive.
 *
 * Bascule d'exercice : dès que les 12 mois d'une année sont tous clôturés
 * (quel que soit l'ordre dans lequel ils l'ont été — rien n'impose de
 * clôturer janvier avant février dans ce modèle), les 12 périodes de
 * l'année suivante sont créées automatiquement (statut 'open', début réel
 * du cycle de vie) et les taux de change de décembre sont recopiés sur les
 * 12 nouveaux mois — sans quoi la première saisie d'une filiale en devise
 * étrangère serait bloquée dès janvier faute de taux. Décision utilisateur
 * du 2026-08-12 (voir PROJECT_STATE.md).
 */
class PeriodService
{
    private ReportingPeriodRepository $periods;
    private ExchangeRateRepository $rates;
    private NotificationRepository $notifications;
    private AuditService $audit;

    public function __construct()
    {
        $this->periods = new ReportingPeriodRepository();
        $this->rates = new ExchangeRateRepository();
        $this->notifications = new NotificationRepository();
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

        if ($expected === 'closed') {
            $this->maybeOpenNextFiscalYear($period->year, $actor, $request);
        }
    }

    /**
     * Crée l'exercice suivant si l'année vient de se terminer (12/12 mois
     * clôturés) et qu'il n'a pas déjà été ouvert (idempotent — sans cette
     * garde, clôturer un 2ᵉ mois après le 12ᵉ recréerait l'année suivante).
     */
    private function maybeOpenNextFiscalYear(int $year, User $actor, Request $request): void
    {
        $yearPeriods = $this->periods->forYear($year);
        if (count($yearPeriods) < 12 || count(array_filter($yearPeriods, fn ($p) => $p->isClosed())) < 12) {
            return; // année incomplète ou pas encore entièrement clôturée
        }
        if (!empty($this->periods->forYear($year + 1))) {
            return; // déjà ouverte (garde d'idempotence)
        }

        $december = null;
        foreach ($yearPeriods as $p) {
            if ($p->month === 12) {
                $december = $p;
                break;
            }
        }
        $decemberRates = $december ? $this->rates->forPeriod($december->id) : [];

        $newYear = $year + 1;
        $firstNewPeriodId = null;
        for ($month = 1; $month <= 12; $month++) {
            $newId = $this->periods->create($newYear, $month, ReportingPeriod::labelFor($newYear, $month));
            $firstNewPeriodId ??= $newId;
            foreach ($decemberRates as $currencyCode => $byType) {
                foreach ($byType as $rateType => $rate) {
                    $this->rates->upsert($currencyCode, $newId, $rateType, $rate);
                }
            }
        }

        $this->audit->logChange(
            $actor, 'fiscal_year_opened', 'reporting_period', $firstNewPeriodId,
            null, ['year' => $newYear, 'source_year' => $year], $request, null, $firstNewPeriodId
        );

        $recipients = array_unique(array_merge(
            $this->notifications->userIdsForRole(Role::GROUP_ADMIN),
            $this->notifications->userIdsForRole(Role::CONSOLIDATION_MANAGER),
            $this->notifications->userIdsForRole(Role::CFO_READONLY)
        ));
        $message = "Exercice {$newYear} ouvert automatiquement (12 périodes) — taux de change repris de décembre {$year}, à vérifier.";
        foreach ($recipients as $userId) {
            $this->notifications->create($userId, 'fiscal_year_opened', $message, 'reporting_period', $firstNewPeriodId);
        }
    }
}
