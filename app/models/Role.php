<?php

namespace App\Models;

/** Rôle applicatif (référentiel RBAC). */
class Role
{
    public const GROUP_ADMIN = 'group_admin';
    public const PREPARER = 'preparer';
    public const SUBSIDIARY_CONTROLLER = 'subsidiary_controller';
    public const CONSOLIDATION_MANAGER = 'consolidation_manager';
    public const CFO_READONLY = 'cfo_readonly';

    /** Rôles ayant une visibilité groupe (non restreinte à une filiale). */
    public const GROUP_LEVEL_ROLES = [
        self::GROUP_ADMIN,
        self::CONSOLIDATION_MANAGER,
        self::CFO_READONLY,
    ];

    public function __construct(
        public int $id,
        public string $code,
        public string $label,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], $row['code'], $row['label']);
    }
}
