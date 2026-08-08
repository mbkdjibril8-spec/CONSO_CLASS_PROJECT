<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\Account;

class AccountRepository
{
    /** @return Account[] */
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM accounts WHERE is_active = 1 ORDER BY statement_type, display_order'
        );
        return array_map(fn ($row) => Account::fromRow($row), $stmt->fetchAll());
    }

    /** @return array<string, Account> comptes indexés par code */
    public function allByCode(): array
    {
        $byCode = [];
        foreach ($this->all() as $account) {
            $byCode[$account->code] = $account;
        }
        return $byCode;
    }

    /** @return Account[] comptes d'un état financier donné (IS|BS|CF), triés pour l'affichage */
    public function forStatement(string $statementType): array
    {
        return array_values(array_filter($this->all(), fn ($a) => $a->statementType === $statementType));
    }
}
