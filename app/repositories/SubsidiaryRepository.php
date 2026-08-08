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

    public function findByCode(string $code): ?Subsidiary
    {
        $stmt = Database::connection()->prepare('SELECT * FROM subsidiaries WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row ? Subsidiary::fromRow($row) : null;
    }

    /** @return Subsidiary[] */
    public function all(bool $activeOnly = false): array
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

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO subsidiaries (code, name, country, zone, activity, currency_code, parent_id, ownership_pct, control_pct, consolidation_method, is_active)
             VALUES (:code, :name, :country, :zone, :activity, :currency_code, :parent_id, :ownership_pct, :control_pct, :consolidation_method, 1)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE subsidiaries SET
                code = :code, name = :name, country = :country, zone = :zone, activity = :activity,
                currency_code = :currency_code, parent_id = :parent_id, ownership_pct = :ownership_pct,
                control_pct = :control_pct, consolidation_method = :consolidation_method
             WHERE id = :id'
        );
        $stmt->execute($data + ['id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE subsidiaries SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /** Chaîne des ancêtres (utilisé pour détecter les cycles avant rattachement). */
    public function ancestorIds(int $subsidiaryId): array
    {
        $ids = [];
        $currentId = $subsidiaryId;
        $connection = Database::connection();

        while (true) {
            $stmt = $connection->prepare('SELECT parent_id FROM subsidiaries WHERE id = :id');
            $stmt->execute(['id' => $currentId]);
            $parentId = $stmt->fetchColumn();

            if (!$parentId) {
                break;
            }
            $ids[] = (int) $parentId;
            $currentId = (int) $parentId;
        }

        return $ids;
    }
}
