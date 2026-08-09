<?php

namespace App\Repositories;

use App\Core\Database;

class ConsolidationLineItemRepository
{
    public function upsert(int $runId, int $accountId, float $amount): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO consolidation_line_items (run_id, account_id, amount)
             VALUES (:run_id, :account_id, :amount)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
        );
        $stmt->execute(['run_id' => $runId, 'account_id' => $accountId, 'amount' => $amount]);
    }

    /** @return array<string, float> montants indexés par code compte */
    public function forRun(int $runId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.code, cli.amount FROM consolidation_line_items cli
             JOIN accounts a ON a.id = cli.account_id
             WHERE cli.run_id = :run_id'
        );
        $stmt->execute(['run_id' => $runId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['code']] = (float) $row['amount'];
        }
        return $result;
    }
}
