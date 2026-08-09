<?php

namespace App\Repositories;

use App\Core\Database;

class MinorityInterestRepository
{
    public function upsert(int $runId, int $subsidiaryId, float $minorityPct, float $netIncomeShare, float $equityShare): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO minority_interests (run_id, subsidiary_id, minority_pct, net_income_share, equity_share)
             VALUES (:run_id, :subsidiary_id, :pct, :ni_share, :eq_share)
             ON DUPLICATE KEY UPDATE minority_pct = VALUES(minority_pct), net_income_share = VALUES(net_income_share), equity_share = VALUES(equity_share)'
        );
        $stmt->execute([
            'run_id' => $runId, 'subsidiary_id' => $subsidiaryId, 'pct' => $minorityPct,
            'ni_share' => $netIncomeShare, 'eq_share' => $equityShare,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function forRun(int $runId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT mi.*, s.name AS subsidiary_name, s.code AS subsidiary_code
             FROM minority_interests mi
             JOIN subsidiaries s ON s.id = mi.subsidiary_id
             WHERE mi.run_id = :run_id ORDER BY s.name'
        );
        $stmt->execute(['run_id' => $runId]);
        return $stmt->fetchAll();
    }

    public function totalsForRun(int $runId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(net_income_share),0) AS total_ni, COALESCE(SUM(equity_share),0) AS total_equity
             FROM minority_interests WHERE run_id = :run_id'
        );
        $stmt->execute(['run_id' => $runId]);
        $row = $stmt->fetch();
        return ['net_income' => (float) $row['total_ni'], 'equity' => (float) $row['total_equity']];
    }
}
