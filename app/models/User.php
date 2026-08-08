<?php

namespace App\Models;

/** Utilisateur applicatif, hydraté depuis la table users (jointure roles). */
class User
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $passwordHash,
        public int $roleId,
        public string $roleCode,
        public ?int $subsidiaryId,
        public bool $isActive,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['name'],
            $row['email'],
            $row['password_hash'],
            (int) $row['role_id'],
            $row['role_code'],
            isset($row['subsidiary_id']) ? (int) $row['subsidiary_id'] : null,
            (bool) $row['is_active'],
        );
    }

    /** Un rôle groupe voit toutes les filiales ; un rôle filiale est restreint à la sienne. */
    public function isGroupLevel(): bool
    {
        return in_array($this->roleCode, Role::GROUP_LEVEL_ROLES, true);
    }

    public function canAccessSubsidiary(int $subsidiaryId): bool
    {
        return $this->isGroupLevel() || $this->subsidiaryId === $subsidiaryId;
    }
}
