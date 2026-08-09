<?php

namespace App\Repositories;

use App\Core\Database;

class BudgetRepository
{
    /** @return array<string, float> montant budget agrégé par code compte, pour une période (tous rôles/filiales confondus ou une liste donnée) */
    public function sumByAccountForPeriod(int $periodId, ?array $subsidiaryIds = null): array
    {
        $sql = 'SELECT a.code, SUM(b.amount) AS total
                FROM budgets b
                JOIN accounts a ON a.id = b.account_id
                WHERE b.period_id = :period_id';
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
            $sql .= ' AND b.subsidiary_id IN (' . implode(',', $placeholders) . ')';
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

    /** Budget d'un compte pour une filiale/période donnée (0 si non renseigné). */
    public function forSubsidiaryPeriod(int $subsidiaryId, int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.code, b.amount FROM budgets b JOIN accounts a ON a.id = b.account_id
             WHERE b.subsidiary_id = :sid AND b.period_id = :pid'
        );
        $stmt->execute(['sid' => $subsidiaryId, 'pid' => $periodId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['code']] = (float) $row['amount'];
        }
        return $result;
    }

    /**
     * Montants budget (IS) par filiale pour une période, indexés filiale puis
     * code compte — en devise locale, à convertir avant toute agrégation
     * multi-filiale (voir ReportingService).
     * @return array<int, array<string, float>>
     */
    public function isAmountsGroupedBySubsidiary(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT b.subsidiary_id, a.code, b.amount
             FROM budgets b
             JOIN accounts a ON a.id = b.account_id
             WHERE b.period_id = :period_id AND a.statement_type = 'IS'"
        );
        $stmt->execute(['period_id' => $periodId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['subsidiary_id']][$row['code']] = (float) $row['amount'];
        }
        return $result;
    }
}
