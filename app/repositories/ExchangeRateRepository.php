<?php

namespace App\Repositories;

use App\Core\Database;

class ExchangeRateRepository
{
    /** Taux (moyen + clôture) de toutes les devises étrangères pour une période donnée. */
    public function forPeriod(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT currency_code, rate_type, rate FROM exchange_rates WHERE period_id = :period_id'
        );
        $stmt->execute(['period_id' => $periodId]);

        $rates = [];
        foreach ($stmt->fetchAll() as $row) {
            $rates[$row['currency_code']][$row['rate_type']] = (float) $row['rate'];
        }
        return $rates;
    }

    public function upsert(string $currencyCode, int $periodId, string $rateType, float $rate): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO exchange_rates (currency_code, period_id, rate_type, rate)
             VALUES (:currency_code, :period_id, :rate_type, :rate)
             ON DUPLICATE KEY UPDATE rate = VALUES(rate), updated_at = NOW()'
        );
        $stmt->execute([
            'currency_code' => $currencyCode,
            'period_id'     => $periodId,
            'rate_type'     => $rateType,
            'rate'          => $rate,
        ]);
    }
}
