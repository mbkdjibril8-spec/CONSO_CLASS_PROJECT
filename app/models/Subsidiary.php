<?php

namespace App\Models;

/** Filiale ou entité de la structure de groupe. */
class Subsidiary
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $country,
        public ?string $zone,
        public ?string $activity,
        public string $currencyCode,
        public ?int $parentId,
        public float $ownershipPct,
        public float $controlPct,
        public string $consolidationMethod, // full | equity | excluded
        public bool $isActive,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['code'],
            $row['name'],
            $row['country'],
            $row['zone'],
            $row['activity'],
            $row['currency_code'],
            isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            (float) $row['ownership_pct'],
            (float) $row['control_pct'],
            $row['consolidation_method'],
            (bool) $row['is_active'],
        );
    }
}
