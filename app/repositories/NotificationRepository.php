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

    public function unreadCount(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function forUser(int $userId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue('uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Marque une notification comme lue ; retourne false si elle n'appartient pas à l'utilisateur. */
    public function markRead(int $id, int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function markAllRead(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->execute(['uid' => $userId]);
    }
}
