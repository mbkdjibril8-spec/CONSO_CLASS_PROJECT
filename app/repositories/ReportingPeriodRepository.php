<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\ReportingPeriod;

class ReportingPeriodRepository
{
    /** @return ReportingPeriod[] */
    public function all(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM reporting_periods ORDER BY year ASC, month ASC');
        return array_map(fn ($row) => ReportingPeriod::fromRow($row), $stmt->fetchAll());
    }

    public function findById(int $id): ?ReportingPeriod
    {
        $stmt = Database::connection()->prepare('SELECT * FROM reporting_periods WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? ReportingPeriod::fromRow($row) : null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE reporting_periods SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }
}
