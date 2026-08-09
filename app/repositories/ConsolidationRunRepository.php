<?php

namespace App\Repositories;

use App\Core\Database;

class ConsolidationRunRepository
{
    public function create(int $periodId, int $startedBy): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO consolidation_runs (period_id, status, started_by) VALUES (:period_id, :status, :started_by)'
        );
        $stmt->execute(['period_id' => $periodId, 'status' => 'running', 'started_by' => $startedBy]);
        return (int) Database::connection()->lastInsertId();
    }

    public function complete(int $runId, string $status, ?string $notes = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE consolidation_runs SET status = :status, completed_at = NOW(), notes = :notes WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'notes' => $notes, 'id' => $runId]);
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, rp.label AS period_label, u.name AS started_by_name
             FROM consolidation_runs r
             JOIN reporting_periods rp ON rp.id = r.period_id
             JOIN users u ON u.id = r.started_by
             WHERE r.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Dernier run terminé avec succès pour une période (utilisé pour l'affichage courant). */
    public function latestCompletedForPeriod(int $periodId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT r.*, u.name AS started_by_name FROM consolidation_runs r
             JOIN users u ON u.id = r.started_by
             WHERE r.period_id = :period_id AND r.status = 'completed'
             ORDER BY r.started_at DESC LIMIT 1"
        );
        $stmt->execute(['period_id' => $periodId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT r.*, rp.label AS period_label, u.name AS started_by_name
             FROM consolidation_runs r
             JOIN reporting_periods rp ON rp.id = r.period_id
             JOIN users u ON u.id = r.started_by
             ORDER BY r.started_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function addStep(int $runId, int $order, string $name): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO consolidation_run_steps (run_id, step_order, step_name, status, started_at)
             VALUES (:run_id, :order, :name, :status, NOW())'
        );
        $stmt->execute(['run_id' => $runId, 'order' => $order, 'name' => $name, 'status' => 'running']);
        return (int) Database::connection()->lastInsertId();
    }

    public function completeStep(int $stepId, string $status, string $details): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE consolidation_run_steps SET status = :status, details = :details, completed_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'details' => $details, 'id' => $stepId]);
    }

    /** @return array<int, array<string, mixed>> étapes d'un run, dans l'ordre */
    public function steps(int $runId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM consolidation_run_steps WHERE run_id = :run_id ORDER BY step_order'
        );
        $stmt->execute(['run_id' => $runId]);
        return $stmt->fetchAll();
    }
}
