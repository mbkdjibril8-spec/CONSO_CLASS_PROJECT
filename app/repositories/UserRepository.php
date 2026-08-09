<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\User;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.code AS role_code
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function findById(int $id): ?User
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.code AS role_code
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function touchLastLogin(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    /** Compte les utilisateurs actifs, éventuellement filtré par filiale (0 = niveau groupe). */
    public function countActive(): int
    {
        $stmt = Database::connection()->query('SELECT COUNT(*) FROM users WHERE is_active = 1');
        return (int) $stmt->fetchColumn();
    }

    /** @return User[] */
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT u.*, r.code AS role_code
             FROM users u
             JOIN roles r ON r.id = u.role_id
             ORDER BY u.name'
        );
        return array_map(fn ($row) => User::fromRow($row), $stmt->fetchAll());
    }
}
