<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;
use App\Services\WorkflowService;

/** Actions de workflow (Soumettre / Valider / Rejeter) sur un paquet filiale/période. */
class WorkflowController extends Controller
{
    private SubsidiaryRepository $subsidiaries;
    private ReportingPeriodRepository $periods;

    public function __construct()
    {
        $this->subsidiaries = new SubsidiaryRepository();
        $this->periods = new ReportingPeriodRepository();
    }

    public function submit(Request $request, string $subsidiaryId, string $periodId): void
    {
        [$subsidiary, $period] = $this->resolve($subsidiaryId, $periodId);
        if (!$subsidiary || !$period) {
            return;
        }

        [$ok, $error] = (new WorkflowService())->submit($subsidiary, $period, $this->currentUser(), $request);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Paquet soumis pour validation.' : $error);
        $this->redirect("/financial-data/{$subsidiary->id}/{$period->id}");
    }

    public function validatePackage(Request $request, string $subsidiaryId, string $periodId): void
    {
        [$subsidiary, $period] = $this->resolve($subsidiaryId, $periodId);
        if (!$subsidiary || !$period) {
            return;
        }

        [$ok, $error] = (new WorkflowService())->validatePackage($subsidiary, $period, $this->currentUser(), $request);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Paquet validé.' : $error);
        $this->redirect("/financial-data/{$subsidiary->id}/{$period->id}");
    }

    public function reject(Request $request, string $subsidiaryId, string $periodId): void
    {
        [$subsidiary, $period] = $this->resolve($subsidiaryId, $periodId);
        if (!$subsidiary || !$period) {
            return;
        }

        $reason = (string) $request->input('reason', '');
        [$ok, $error] = (new WorkflowService())->reject($subsidiary, $period, $this->currentUser(), $reason, $request);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Paquet rejeté ; le préparateur a été notifié.' : $error);
        $this->redirect("/financial-data/{$subsidiary->id}/{$period->id}");
    }

    private function resolve(string $subsidiaryId, string $periodId): array
    {
        $subsidiary = $this->subsidiaries->findById((int) $subsidiaryId);
        $period = $this->periods->findById((int) $periodId);
        if (!$subsidiary || !$period) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Introuvable']);
            return [null, null];
        }
        return [$subsidiary, $period];
    }
}
