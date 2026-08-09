<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\AuditLogRepository;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;
use App\Repositories\UserRepository;

/** Visualiseur du journal d'audit, filtrable par utilisateur/filiale/période. */
class AuditController extends Controller
{
    public function index(Request $request): void
    {
        $userId = $request->query('user_id', '') !== '' ? (int) $request->query('user_id') : null;
        $subsidiaryId = $request->query('subsidiary_id', '') !== '' ? (int) $request->query('subsidiary_id') : null;
        $periodId = $request->query('period_id', '') !== '' ? (int) $request->query('period_id') : null;

        $logs = (new AuditLogRepository())->filtered($userId, $subsidiaryId, $periodId);

        $this->view('audit/index', [
            'title' => "Journal d'audit",
            'logs' => $logs,
            'users' => (new UserRepository())->all(),
            'subsidiaries' => (new SubsidiaryRepository())->all(true),
            'periods' => (new ReportingPeriodRepository())->all(),
            'userIdFilter' => $userId,
            'subsidiaryIdFilter' => $subsidiaryId,
            'periodIdFilter' => $periodId,
        ], $request->isAjax() ? null : 'layouts/main');
    }
}
