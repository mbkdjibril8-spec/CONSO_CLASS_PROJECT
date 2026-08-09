<?php

namespace App\Services;

use App\Repositories\ExchangeRateRepository;

/**
 * Conversion en devise de consolidation (XOF) : taux moyen pour le compte
 * de résultat (flux de la période), taux de clôture pour le bilan (soldes
 * à date). Voir docs/CONSOLIDATION_LOGIC.md.
 */
class CurrencyConversionService
{
    private ExchangeRateRepository $rates;

    public function __construct()
    {
        $this->rates = new ExchangeRateRepository();
    }

    /** @return array<string, array{average: float, closing: float}> taux par devise pour la période */
    public function ratesForPeriod(int $periodId): array
    {
        return $this->rates->forPeriod($periodId);
    }

    /**
     * Convertit un montant en devise locale vers XOF.
     * @param array<string, array{average: float, closing: float}> $rates
     * @return float|null null si le taux requis est indisponible
     */
    public function convert(float $amountLocal, string $currencyCode, string $statementType, array $rates): ?float
    {
        if ($currencyCode === 'XOF') {
            return round($amountLocal, 2);
        }

        $rateType = $statementType === 'BS' ? 'closing' : 'average';
        $rate = $rates[$currencyCode][$rateType] ?? null;

        return $rate !== null ? round($amountLocal * $rate, 2) : null;
    }
}
