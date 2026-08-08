<?php

namespace App\Repositories;

use App\Core\Database;

class NotificationRepository
{
    public function create(int $userId, string $type, string $message, ?string $relatedEntity = null, ?int $relatedId = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications (user_id, type, message, related_entity, related_id)
             VALUES (:uid, :type, :message, :related_entity, :related_id)'
        );
        $stmt->execute([
            'uid' => $userId, 'type' => $type, 'message' => $message,
            'related_entity' => $relatedEntity, 'related_id' => $relatedId,
        ]);
    }

    /** @return int[] identifiants des utilisateurs actifs d'un rôle donné, éventuellement restreint à une filiale */
    public function userIdsForRole(string $roleCode, ?int $subsidiaryId = null): array
    {
        $sql = 'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = :role AND u.is_active = 1';
        $params = ['role' => $roleCode];
        if ($subsidiaryId !== null) {
            $sql .= ' AND u.subsidiary_id = :sid';
            $params['sid'] = $subsidiaryId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
