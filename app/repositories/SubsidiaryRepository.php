<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\Subsidiary;

class SubsidiaryRepository
{
    public function findById(int $id): ?Subsidiary
    {
        $stmt = Database::connection()->prepare('SELECT * FROM subsidiaries WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? Subsidiary::fromRow($row) : null;
    }

    /** @return Subsidiary[] */
    public function all(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM subsidiaries';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY parent_id IS NULL DESC, name ASC';

        $stmt = Database::connection()->query($sql);
        return array_map(fn ($row) => Subsidiary::fromRow($row), $stmt->fetchAll());
    }

    public function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM subsidiaries')->fetchColumn();
    }
}
