<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\AccountRepository;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;
use App\Services\ReportingService;

/** Écran dédié Budget vs Actual (mensuel + cumul YTD), §2.10 du cahier des charges. */
class BudgetController extends Controller
{
    public function index(Request $request): void
    {
        $user = $this->currentUser();
        $periodRepo = new ReportingPeriodRepository();
        $subsidiaryRepo = new SubsidiaryRepository();

        $periods = $periodRepo->all();
        $selectedPeriodId = (int) $request->query('period_id', 0);
        $period = $selectedPeriodId ? $periodRepo->findById($selectedPeriodId) : null;
        if (!$period && !empty($periods)) {
            $period = $periods[count($periods) - 1];
        }

        $fullMethodSubsidiaries = array_values(array_filter($subsidiaryRepo->all(true), fn ($s) => $s->consolidationMethod === 'full'));

        if ($user->isGroupLevel()) {
            $subsidiaryFilter = $request->query('subsidiary_id', '');
            $targetIds = $subsidiaryFilter !== ''
                ? [(int) $subsidiaryFilter]
                : array_map(fn ($s) => $s->id, $fullMethodSubsidiaries);
        } else {
            $subsidiaryFilter = (string) $user->subsidiaryId;
            $targetIds = $user->subsidiaryId ? [$user->subsidiaryId] : [];
        }

        $detail = $period && !empty($targetIds)
            ? (new ReportingService())->budgetVsActualDetail($period, $targetIds)
            : null;

        $this->view('budgets/index', [
            'title' => 'Budget vs Actual',
            'periods' => $periods,
            'period' => $period,
            'subsidiaries' => $fullMethodSubsidiaries,
            'subsidiaryFilter' => $subsidiaryFilter,
            'detail' => $detail,
            'accounts' => (new AccountRepository())->allByCode(),
        ], $request->isAjax() ? null : 'layouts/main');
    }
}
