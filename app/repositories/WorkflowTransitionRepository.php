<?php

namespace App\Repositories;

use App\Core\Database;

class WorkflowTransitionRepository
{
    /** Dernier statut connu pour un paquet filiale/période, 'draft' si aucune transition n'existe encore. */
    public function currentStatus(int $subsidiaryId, int $periodId): string
    {
        $stmt = Database::connection()->prepare(
            'SELECT to_status FROM workflow_transitions
             WHERE subsidiary_id = :sid AND period_id = :pid
             ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute(['sid' => $subsidiaryId, 'pid' => $periodId]);
        $status = $stmt->fetchColumn();
        return $status !== false ? $status : 'draft';
    }

    public function record(int $subsidiaryId, int $periodId, ?string $fromStatus, string $toStatus, int $userId, ?string $comment = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO workflow_transitions (subsidiary_id, period_id, from_status, to_status, user_id, comment)
             VALUES (:sid, :pid, :from_status, :to_status, :uid, :comment)'
        );
        $stmt->execute([
            'sid' => $subsidiaryId, 'pid' => $periodId, 'from_status' => $fromStatus,
            'to_status' => $toStatus, 'uid' => $userId, 'comment' => $comment,
        ]);
    }

    /** @return array<int, array<string, mixed>> historique complet, plus récent en premier */
    public function history(int $subsidiaryId, int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT wt.*, u.name AS user_name
             FROM workflow_transitions wt
             JOIN users u ON u.id = wt.user_id
             WHERE wt.subsidiary_id = :sid AND wt.period_id = :pid
             ORDER BY wt.created_at DESC, wt.id DESC'
        );
        $stmt->execute(['sid' => $subsidiaryId, 'pid' => $periodId]);
        return $stmt->fetchAll();
    }

    /** Dernier commentaire de rejet (affiché en bannière tant que le statut est 'rejected'). */
    public function lastRejectionReason(int $subsidiaryId, int $periodId): ?string
    {
        $stmt = Database::connection()->prepare(
            "SELECT comment FROM workflow_transitions
             WHERE subsidiary_id = :sid AND period_id = :pid AND to_status = 'rejected'
             ORDER BY created_at DESC, id DESC LIMIT 1"
        );
        $stmt->execute(['sid' => $subsidiaryId, 'pid' => $periodId]);
        $comment = $stmt->fetchColumn();
        return $comment !== false ? $comment : null;
    }
}
