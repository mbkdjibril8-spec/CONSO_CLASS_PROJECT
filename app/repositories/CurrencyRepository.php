<?php

namespace App\Repositories;

use App\Core\Database;

class CurrencyRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM currencies ORDER BY is_group_currency DESC, code ASC')
            ->fetchAll();
    }

    /** Devises étrangères uniquement (hors devise de consolidation), pour les écrans de taux de change. */
    public function foreign(): array
    {
        return Database::connection()
            ->query('SELECT * FROM currencies WHERE is_group_currency = 0 ORDER BY code ASC')
            ->fetchAll();
    }

    public function exists(string $code): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM currencies WHERE code = :code');
        $stmt->execute(['code' => $code]);
        return (bool) $stmt->fetchColumn();
    }
}
