<?php

namespace App\Repositories;

use App\Core\Database;

class AuditLogRepository
{
    public function log(
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $ipAddress = null,
        ?int $subsidiaryId = null,
        ?int $periodId = null
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, subsidiary_id, period_id, old_value, new_value, ip_address, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :subsidiary_id, :period_id, :old_value, :new_value, :ip_address, NOW())'
        );
        $stmt->execute([
            'user_id'       => $userId,
            'action'        => $action,
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'subsidiary_id' => $subsidiaryId,
            'period_id'     => $periodId,
            'old_value'     => $oldValue,
            'new_value'     => $newValue,
            'ip_address'    => $ipAddress,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT al.*, u.name AS user_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Journal filtré pour le visualiseur d'audit (Phase 7).
     * @return array<int, array<string, mixed>>
     */
    public function filtered(?int $userId, ?int $subsidiaryId, ?int $periodId, int $limit = 200): array
    {
        $sql = 'SELECT al.*, u.name AS user_name, s.code AS subsidiary_code, rp.label AS period_label
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                LEFT JOIN subsidiaries s ON s.id = al.subsidiary_id
                LEFT JOIN reporting_periods rp ON rp.id = al.period_id
                WHERE 1=1';
        $params = [];

        if ($userId !== null) {
            $sql .= ' AND al.user_id = :user_id';
            $params['user_id'] = $userId;
        }
        if ($subsidiaryId !== null) {
            $sql .= ' AND al.subsidiary_id = :subsidiary_id';
            $params['subsidiary_id'] = $subsidiaryId;
        }
        if ($periodId !== null) {
            $sql .= ' AND al.period_id = :period_id';
            $params['period_id'] = $periodId;
        }
        $sql .= ' ORDER BY al.created_at DESC, al.id DESC LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
