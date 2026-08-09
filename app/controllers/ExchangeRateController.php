<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\CurrencyRepository;
use App\Repositories\ExchangeRateRepository;
use App\Repositories\ReportingPeriodRepository;
use App\Services\ExchangeRateService;

class ExchangeRateController extends Controller
{
    private ReportingPeriodRepository $periods;

    public function __construct()
    {
        $this->periods = new ReportingPeriodRepository();
    }

    public function index(Request $request): void
    {
        $periods = $this->periods->all();
        $selectedId = (int) $request->query('period_id', 0);
        $period = $selectedId ? $this->periods->findById($selectedId) : end($periods);
        if (!$period && !empty($periods)) {
            $period = $periods[count($periods) - 1];
        }

        $this->view('reporting/exchange_rates', [
            'title' => 'Taux de change',
            'periods' => $periods,
            'period' => $period,
            'currencies' => (new CurrencyRepository())->foreign(),
            'rates' => $period ? (new ExchangeRateRepository())->forPeriod($period->id) : [],
        ], $request->isAjax() ? null : 'layouts/main');
    }

    public function store(Request $request): void
    {
        $periodId = (int) $request->input('period_id');
        $period = $this->periods->findById($periodId);

        if (!$period) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Période introuvable']);
            return;
        }

        $currencies = (new CurrencyRepository())->foreign();
        $ratesByCurrency = [];
        foreach ($currencies as $currency) {
            $code = $currency['code'];
            $ratesByCurrency[$code] = [
                'average' => (float) $request->input("rate_{$code}_average", 0),
                'closing' => (float) $request->input("rate_{$code}_closing", 0),
            ];
        }

        try {
            (new ExchangeRateService())->saveForPeriod($period, $ratesByCurrency, $this->currentUser(), $request);
            Session::flash('success', "Taux de change de la période {$period->label} enregistrés.");
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/exchange-rates?period_id=' . $periodId);
    }
}
