<?php

namespace App\Repositories;

use App\Core\Database;

class EliminationRepository
{
    public function create(int $runId, string $type, ?int $sourceTransactionId, int $subsidiaryId, int $accountId, float $amount): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO eliminations (run_id, type, source_transaction_id, subsidiary_id, account_id, amount)
             VALUES (:run_id, :type, :source_transaction_id, :subsidiary_id, :account_id, :amount)'
        );
        $stmt->execute([
            'run_id' => $runId, 'type' => $type, 'source_transaction_id' => $sourceTransactionId,
            'subsidiary_id' => $subsidiaryId, 'account_id' => $accountId, 'amount' => $amount,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function forRun(int $runId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*, s.name AS subsidiary_name, s.code AS subsidiary_code, a.label AS account_label
             FROM eliminations e
             JOIN subsidiaries s ON s.id = e.subsidiary_id
             JOIN accounts a ON a.id = e.account_id
             WHERE e.run_id = :run_id ORDER BY e.id'
        );
        $stmt->execute(['run_id' => $runId]);
        return $stmt->fetchAll();
    }
}
