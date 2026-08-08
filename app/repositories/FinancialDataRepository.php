<?php

namespace App\Repositories;

use App\Core\Database;

class FinancialDataRepository
{
    /** @return array<int, float> montants indexés par account_id pour une filiale/période */
    public function forSubsidiaryPeriod(int $subsidiaryId, int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT account_id, amount FROM financial_data WHERE subsidiary_id = :sid AND period_id = :pid'
        );
        $stmt->execute(['sid' => $subsidiaryId, 'pid' => $periodId]);

        $values = [];
        foreach ($stmt->fetchAll() as $row) {
            $values[(int) $row['account_id']] = (float) $row['amount'];
        }
        return $values;
    }

    public function upsert(int $subsidiaryId, int $periodId, int $accountId, float $amount, int $userId): void
    {
        // PDO en mode natif (EMULATE_PREPARES=false) n'autorise pas la réutilisation
        // d'un même paramètre nommé à deux endroits : uid_created/uid_updated distincts.
        $stmt = Database::connection()->prepare(
            'INSERT INTO financial_data (subsidiary_id, period_id, account_id, amount, created_by, updated_by)
             VALUES (:sid, :pid, :aid, :amount, :uid_created, :uid_updated)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $stmt->execute([
            'sid' => $subsidiaryId, 'pid' => $periodId, 'aid' => $accountId, 'amount' => $amount,
            'uid_created' => $userId, 'uid_updated' => $userId,
        ]);
    }

    /** Nombre de comptes déjà renseignés (pour l'indicateur de complétude par période). */
    public function countFilled(int $subsidiaryId, int $periodId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM financial_data WHERE subsidiary_id = :sid AND period_id = :pid'
        );
        $stmt->execute(['sid' => $subsidiaryId, 'pid' => $periodId]);
        return (int) $stmt->fetchColumn();
    }

    /** Montant d'un compte pour la période précédente (comparaison mensuelle, anomalies). */
    public function previousPeriodAmount(int $subsidiaryId, int $previousPeriodId, int $accountId): ?float
    {
        $stmt = Database::connection()->prepare(
            'SELECT amount FROM financial_data WHERE subsidiary_id = :sid AND period_id = :pid AND account_id = :aid'
        );
        $stmt->execute(['sid' => $subsidiaryId, 'pid' => $previousPeriodId, 'aid' => $accountId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (float) $value;
    }
}
