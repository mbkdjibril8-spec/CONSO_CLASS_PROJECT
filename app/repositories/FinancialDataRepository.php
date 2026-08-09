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

    /**
     * Somme des montants réels par compte pour une période, filiales combinées
     * (vision "cumulée" non consolidée — pas d'élimination interco ni de
     * répartition minoritaire : voir docs/CONSOLIDATION_LOGIC.md §Dashboards).
     * @return array<string, float>
     */
    public function sumByAccountForPeriod(int $periodId, ?array $subsidiaryIds = null): array
    {
        $sql = 'SELECT a.code, SUM(fd.amount) AS total
                FROM financial_data fd
                JOIN accounts a ON a.id = fd.account_id
                WHERE fd.period_id = :period_id';
        $params = ['period_id' => $periodId];

        if ($subsidiaryIds !== null) {
            if (empty($subsidiaryIds)) {
                return [];
            }
            $placeholders = [];
            foreach ($subsidiaryIds as $i => $id) {
                $key = "sid{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $id;
            }
            $sql .= ' AND fd.subsidiary_id IN (' . implode(',', $placeholders) . ')';
        }
        $sql .= ' GROUP BY a.code';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['code']] = (float) $row['total'];
        }
        return $result;
    }

    /**
     * Somme des montants réels par compte ET par période sur une année (tendance
     * 12 mois), filiales combinées.
     * @return array<int, array<string, float>> period_id => [code => total]
     */
    public function sumByAccountForYear(int $year, ?array $subsidiaryIds = null): array
    {
        $sql = 'SELECT rp.id AS period_id, a.code, SUM(fd.amount) AS total
                FROM financial_data fd
                JOIN accounts a ON a.id = fd.account_id
                JOIN reporting_periods rp ON rp.id = fd.period_id
                WHERE rp.year = :year';
        $params = ['year' => $year];

        if ($subsidiaryIds !== null) {
            if (empty($subsidiaryIds)) {
                return [];
            }
            $placeholders = [];
            foreach ($subsidiaryIds as $i => $id) {
                $key = "sid{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $id;
            }
            $sql .= ' AND fd.subsidiary_id IN (' . implode(',', $placeholders) . ')';
        }
        $sql .= ' GROUP BY rp.id, a.code';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['period_id']][$row['code']] = (float) $row['total'];
        }
        return $result;
    }

    /**
     * Montants IS par filiale pour une période, indexés filiale puis code
     * compte — permet de recalculer EBITDA/résultat net par filiale en PHP
     * (les comptes de charges doivent être soustraits, pas sommés bruts).
     * @return array<int, array<string, float>> subsidiary_id => [code => amount]
     */
    public function isAmountsGroupedBySubsidiary(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT fd.subsidiary_id, a.code, fd.amount
             FROM financial_data fd
             JOIN accounts a ON a.id = fd.account_id
             WHERE fd.period_id = :period_id AND a.statement_type = 'IS'"
        );
        $stmt->execute(['period_id' => $periodId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['subsidiary_id']][$row['code']] = (float) $row['amount'];
        }
        return $result;
    }
}
