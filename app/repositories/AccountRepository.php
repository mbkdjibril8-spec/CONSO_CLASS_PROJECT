<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\Account;

class AccountRepository
{
    /** @return Account[] Tous les comptes actifs, y compris les lignes de mise en équivalence (Phase 5). */
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM accounts WHERE is_active = 1 ORDER BY statement_type, display_order'
        );
        return array_map(fn ($row) => Account::fromRow($row), $stmt->fetchAll());
    }

    /** @return array<string, Account> comptes indexés par code (tous, y compris mise en équivalence) */
    public function allByCode(): array
    {
        $byCode = [];
        foreach ($this->all() as $account) {
            $byCode[$account->code] = $account;
        }
        return $byCode;
    }

    /**
     * Comptes réellement saisis par une filiale (exclut les lignes de mise
     * en équivalence, calculées par le moteur de consolidation — jamais
     * saisies dans un paquet filiale/période).
     * @return Account[]
     */
    public function enterable(): array
    {
        return array_values(array_filter($this->all(), fn ($a) => $a->section !== 'equity_method'));
    }

    /** @return array<string, Account> */
    public function enterableByCode(): array
    {
        $byCode = [];
        foreach ($this->enterable() as $account) {
            $byCode[$account->code] = $account;
        }
        return $byCode;
    }

    /** @return Account[] comptes saisissables d'un état financier donné (IS|BS|CF), triés pour l'affichage */
    public function forStatement(string $statementType): array
    {
        return array_values(array_filter($this->enterable(), fn ($a) => $a->statementType === $statementType));
    }
}
