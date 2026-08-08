<?php

namespace App\Models;

/** Ligne du plan de comptes (IS/BS/CF). */
class Account
{
    public function __construct(
        public int $id,
        public string $code,
        public string $label,
        public string $statementType, // IS | BS | CF
        public string $section,
        public string $normalBalance, // debit | credit
        public bool $isIntercompany,
        public int $displayOrder,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['code'],
            $row['label'],
            $row['statement_type'],
            $row['section'],
            $row['normal_balance'],
            (bool) $row['is_intercompany'],
            (int) $row['display_order'],
        );
    }

    /** Seuls les comptes de flux de trésorerie acceptent un montant négatif (flux net). */
    public function allowsNegativeAmount(): bool
    {
        return $this->statementType === 'CF';
    }
}
