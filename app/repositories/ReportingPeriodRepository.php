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

    /** @return ReportingPeriod[] les 12 (ou moins) périodes d'une année, triées par mois. */
    public function forYear(int $year): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM reporting_periods WHERE year = :year ORDER BY month ASC');
        $stmt->execute(['year' => $year]);
        return array_map(fn ($row) => ReportingPeriod::fromRow($row), $stmt->fetchAll());
    }

    /** Crée une nouvelle période (statut initial 'open', début réel du cycle de vie). */
    public function create(int $year, int $month, string $label): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO reporting_periods (year, month, label, status) VALUES (:year, :month, :label, :status)'
        );
        $stmt->execute(['year' => $year, 'month' => $month, 'label' => $label, 'status' => 'open']);
        return (int) Database::connection()->lastInsertId();
    }
}
