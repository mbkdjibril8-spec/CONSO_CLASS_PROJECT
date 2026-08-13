<?php

namespace App\Models;

/** Période de reporting mensuelle et son statut dans le cycle de clôture groupe. */
class ReportingPeriod
{
    public const SEQUENCE = ['open', 'in_progress', 'submitted', 'under_review', 'validated', 'consolidated', 'closed'];

    public function __construct(
        public int $id,
        public int $year,
        public int $month,
        public string $label,
        public string $status,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['year'],
            (int) $row['month'],
            $row['label'],
            $row['status'],
        );
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /** Statut suivant dans la séquence, ou null si déjà clôturée. */
    public function nextStatus(): ?string
    {
        $index = array_search($this->status, self::SEQUENCE, true);
        if ($index === false || $index === count(self::SEQUENCE) - 1) {
            return null;
        }
        return self::SEQUENCE[$index + 1];
    }

    /** Libellé standard "AAAA-MM" (mois toujours sur 2 chiffres) — utilisé à la fois par le seed et par
     *  l'ouverture automatique de l'exercice suivant (PeriodService), pour ne jamais diverger. */
    public static function labelFor(int $year, int $month): string
    {
        return sprintf('%d-%02d', $year, $month);
    }
}
