<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\IntercompanyTransaction;

class IntercompanyRepository
{
    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO intercompany_transactions
                (period_id, subsidiary_id, counterparty_subsidiary_id, type, amount_local, amount_group,
                 matched_transaction_id, match_status, difference_amount, created_by)
             VALUES
                (:period_id, :subsidiary_id, :counterparty_subsidiary_id, :type, :amount_local, :amount_group,
                 :matched_transaction_id, :match_status, :difference_amount, :created_by)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public function updateMatch(int $id, ?int $matchedTransactionId, string $matchStatus, float $differenceAmount): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE intercompany_transactions
             SET matched_transaction_id = :matched_id, match_status = :status, difference_amount = :diff
             WHERE id = :id'
        );
        $stmt->execute(['matched_id' => $matchedTransactionId, 'status' => $matchStatus, 'diff' => $differenceAmount, 'id' => $id]);
    }

    public function findById(int $id): ?IntercompanyTransaction
    {
        $stmt = Database::connection()->prepare('SELECT * FROM intercompany_transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? IntercompanyTransaction::fromRow($row) : null;
    }

    /** Contrepartie non encore appariée pour une déclaration donnée (recherche du "mirror"). */
    public function findCounterpart(int $periodId, int $declaringSubsidiaryId, int $counterpartySubsidiaryId, string $type): ?IntercompanyTransaction
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM intercompany_transactions
             WHERE period_id = :period_id AND subsidiary_id = :counterparty_subsidiary_id
               AND counterparty_subsidiary_id = :declaring_subsidiary_id AND type = :type
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            'period_id' => $periodId,
            'counterparty_subsidiary_id' => $counterpartySubsidiaryId,
            'declaring_subsidiary_id' => $declaringSubsidiaryId,
            'type' => $type,
        ]);
        $row = $stmt->fetch();
        return $row ? IntercompanyTransaction::fromRow($row) : null;
    }

    /** @return array<int, array<string, mixed>> toutes les déclarations d'une période, avec noms de filiales */
    public function forPeriod(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ic.*, s1.name AS subsidiary_name, s1.code AS subsidiary_code,
                    s2.name AS counterparty_name, s2.code AS counterparty_code
             FROM intercompany_transactions ic
             JOIN subsidiaries s1 ON s1.id = ic.subsidiary_id
             JOIN subsidiaries s2 ON s2.id = ic.counterparty_subsidiary_id
             WHERE ic.period_id = :period_id
             ORDER BY ic.match_status = "mismatch" DESC, ic.created_at DESC'
        );
        $stmt->execute(['period_id' => $periodId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> déclarations impliquant une filiale donnée (déclarante ou contrepartie) */
    public function forSubsidiaryAndPeriod(int $subsidiaryId, int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ic.*, s1.name AS subsidiary_name, s1.code AS subsidiary_code,
                    s2.name AS counterparty_name, s2.code AS counterparty_code
             FROM intercompany_transactions ic
             JOIN subsidiaries s1 ON s1.id = ic.subsidiary_id
             JOIN subsidiaries s2 ON s2.id = ic.counterparty_subsidiary_id
             WHERE ic.period_id = :period_id AND (ic.subsidiary_id = :sid OR ic.counterparty_subsidiary_id = :sid2)
             ORDER BY ic.match_status = "mismatch" DESC, ic.created_at DESC'
        );
        $stmt->execute(['period_id' => $periodId, 'sid' => $subsidiaryId, 'sid2' => $subsidiaryId]);
        return $stmt->fetchAll();
    }
}
