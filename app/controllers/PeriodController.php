<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\ReportingPeriodRepository;
use App\Services\PeriodService;

class PeriodController extends Controller
{
    private ReportingPeriodRepository $periods;

    public function __construct()
    {
        $this->periods = new ReportingPeriodRepository();
    }

    public function index(Request $request): void
    {
        $this->view('reporting/periods', [
            'title' => 'Périodes de reporting',
            'periods' => $this->periods->all(),
        ]);
    }

    public function transition(Request $request, string $id): void
    {
        $period = $this->periods->findById((int) $id);
        if (!$period) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Période introuvable']);
            return;
        }

        $toStatus = (string) $request->input('to_status', '');

        try {
            (new PeriodService())->advance($period, $toStatus, $this->currentUser(), $request);
            Session::flash('success', "Période {$period->label} : statut mis à jour vers « " . period_status_label($toStatus) . ' ».');
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/periods');
    }
}
