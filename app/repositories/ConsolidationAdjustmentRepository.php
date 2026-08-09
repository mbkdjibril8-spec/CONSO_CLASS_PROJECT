<?php

namespace App\Repositories;

use App\Core\Database;

class ConsolidationAdjustmentRepository
{
    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO consolidation_adjustments (period_id, subsidiary_id, account_id, debit_credit, amount, reason, status, created_by)
             VALUES (:period_id, :subsidiary_id, :account_id, :debit_credit, :amount, :reason, :status, :created_by)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    /** Écritures "posted" (prises en compte par le moteur) pour une période. @return array<int, array<string, mixed>> */
    public function postedForPeriod(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ca.*, a.code AS account_code, a.normal_balance
             FROM consolidation_adjustments ca
             JOIN accounts a ON a.id = ca.account_id
             WHERE ca.period_id = :period_id AND ca.status = 'posted'"
        );
        $stmt->execute(['period_id' => $periodId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> toutes les écritures d'une période, pour affichage */
    public function forPeriod(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ca.*, a.label AS account_label, a.code AS account_code, u.name AS created_by_name,
                    s.name AS subsidiary_name
             FROM consolidation_adjustments ca
             JOIN accounts a ON a.id = ca.account_id
             JOIN users u ON u.id = ca.created_by
             LEFT JOIN subsidiaries s ON s.id = ca.subsidiary_id
             WHERE ca.period_id = :period_id
             ORDER BY ca.created_at DESC'
        );
        $stmt->execute(['period_id' => $periodId]);
        return $stmt->fetchAll();
    }
}
