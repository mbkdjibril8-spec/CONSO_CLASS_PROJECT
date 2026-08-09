<?php

namespace App\Services;

use App\Core\Request;
use App\Models\ReportingPeriod;
use App\Models\User;
use App\Repositories\ExchangeRateRepository;

/**
 * Gestion des taux de change moyen (compte de résultat) et de clôture
 * (bilan) par devise et par période. Une période clôturée est verrouillée :
 * ses taux ne peuvent plus être modifiés (traçabilité de la donnée figée).
 */
class ExchangeRateService
{
    private ExchangeRateRepository $rates;
    private AuditService $audit;

    public function __construct()
    {
        $this->rates = new ExchangeRateRepository();
        $this->audit = new AuditService();
    }

    /**
     * @param array<string, array{average: float, closing: float}> $ratesByCurrency
     * @throws \RuntimeException si la période est clôturée.
     */
    public function saveForPeriod(ReportingPeriod $period, array $ratesByCurrency, User $actor, Request $request): void
    {
        if ($period->isClosed()) {
            throw new \RuntimeException("La période {$period->label} est clôturée : ses taux ne sont plus modifiables.");
        }

        $before = $this->rates->forPeriod($period->id);

        foreach ($ratesByCurrency as $currencyCode => $values) {
            $this->rates->upsert($currencyCode, $period->id, 'average', (float) $values['average']);
            $this->rates->upsert($currencyCode, $period->id, 'closing', (float) $values['closing']);
        }

        $this->audit->logChange(
            $actor,
            'update',
            'exchange_rates',
            $period->id,
            $before,
            $ratesByCurrency,
            $request,
            null,
            $period->id
        );
    }
}
