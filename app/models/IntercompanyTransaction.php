<?php

namespace App\Models;

/** Déclaration intercompany (receivable/payable/revenue/expense/dividend). */
class IntercompanyTransaction
{
    public function __construct(
        public int $id,
        public int $periodId,
        public int $subsidiaryId,
        public int $counterpartySubsidiaryId,
        public string $type,
        public float $amountLocal,
        public float $amountGroup,
        public ?int $matchedTransactionId,
        public string $matchStatus, // pending | matched | mismatch
        public float $differenceAmount,
        public string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['period_id'],
            (int) $row['subsidiary_id'],
            (int) $row['counterparty_subsidiary_id'],
            $row['type'],
            (float) $row['amount_local'],
            (float) $row['amount_group'],
            isset($row['matched_transaction_id']) ? (int) $row['matched_transaction_id'] : null,
            $row['match_status'],
            (float) $row['difference_amount'],
            $row['created_at'],
        );
    }

    /** Type de la déclaration miroir attendue chez la contrepartie. */
    public static function counterpartType(string $type): string
    {
        return match ($type) {
            'receivable' => 'payable',
            'payable' => 'receivable',
            'revenue' => 'expense',
            'expense' => 'revenue',
            default => $type, // dividend : pas de contrepartie attendue (voir CONSOLIDATION_LOGIC.md)
        };
    }
}
