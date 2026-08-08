<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Repositories\AccountRepository;
use App\Repositories\FinancialDataRepository;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;
use App\Services\CsvImportService;
use App\Services\ValidationService;

/**
 * Collecte des données financières (IS/BS/CF) par filiale et par période.
 * Seul le rôle Préparateur peut saisir/modifier (règle métier : le
 * Contrôleur valide ou rejette mais ne ressaisit pas les données brutes,
 * cf. workflow Phase 4). Les autres rôles autorisés à voir la filiale
 * consultent en lecture seule.
 */
class FinancialDataController extends Controller
{
    private SubsidiaryRepository $subsidiaries;
    private ReportingPeriodRepository $periods;
    private AccountRepository $accounts;
    private FinancialDataRepository $financialData;

    public function __construct()
    {
        $this->subsidiaries = new SubsidiaryRepository();
        $this->periods = new ReportingPeriodRepository();
        $this->accounts = new AccountRepository();
        $this->financialData = new FinancialDataRepository();
    }

    public function periodsIndex(Request $request, string $subsidiaryId): void
    {
        $subsidiary = $this->subsidiaries->findById((int) $subsidiaryId);
        if (!$subsidiary) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Filiale introuvable']);
            return;
        }

        $totalAccounts = count($this->accounts->all());
        $rows = [];
        foreach ($this->periods->all() as $period) {
            $rows[] = [
                'period' => $period,
                'filled' => $this->financialData->countFilled($subsidiary->id, $period->id),
            ];
        }

        $this->view('reporting/financial_data_periods', [
            'title' => 'Données financières — ' . $subsidiary->name,
            'subsidiary' => $subsidiary,
            'rows' => $rows,
            'totalAccounts' => $totalAccounts,
        ]);
    }

    public function show(Request $request, string $subsidiaryId, string $periodId, array $importResult = []): void
    {
        $subsidiary = $this->subsidiaries->findById((int) $subsidiaryId);
        $period = $this->periods->findById((int) $periodId);
        if (!$subsidiary || !$period) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Introuvable']);
            return;
        }

        $accountsByCode = $this->accounts->allByCode();
        $stored = $this->financialData->forSubsidiaryPeriod($subsidiary->id, $period->id);

        $rawAmounts = [];
        foreach ($accountsByCode as $code => $account) {
            $rawAmounts[$code] = array_key_exists($account->id, $stored) ? (string) $stored[$account->id] : '';
        }

        $balance = null;
        if (!in_array('', $rawAmounts, true)) {
            $previousAmounts = $this->previousAmounts($subsidiary->id, $period, $accountsByCode);
            $balance = (new ValidationService())->validate(array_values($accountsByCode), $rawAmounts, $previousAmounts);
        }

        $user = $this->currentUser();
        $canEdit = $user->roleCode === Role::PREPARER && !$period->isClosed();

        $this->view('reporting/financial_data_form', [
            'title' => 'Saisie financière — ' . $subsidiary->name . ' — ' . $period->label,
            'subsidiary' => $subsidiary,
            'period' => $period,
            'accountsByStatement' => [
                'IS' => $this->accounts->forStatement('IS'),
                'BS' => $this->accounts->forStatement('BS'),
                'CF' => $this->accounts->forStatement('CF'),
            ],
            'rawAmounts' => $rawAmounts,
            'errors' => [],
            'balance' => $balance,
            'canEdit' => $canEdit,
            'importResult' => $importResult,
        ]);
    }

    public function save(Request $request, string $subsidiaryId, string $periodId): void
    {
        $subsidiary = $this->subsidiaries->findById((int) $subsidiaryId);
        $period = $this->periods->findById((int) $periodId);
        if (!$subsidiary || !$period) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Introuvable']);
            return;
        }
        if ($period->isClosed()) {
            Session::flash('error', 'Cette période est clôturée : la saisie est verrouillée.');
            $this->redirect("/financial-data/{$subsidiary->id}/{$period->id}");
            return;
        }

        $accountsByCode = $this->accounts->allByCode();
        $rawAmounts = [];
        foreach ($accountsByCode as $code => $account) {
            $rawAmounts[$code] = (string) $request->input('amount_' . $code, '');
        }

        $previousAmounts = $this->previousAmounts($subsidiary->id, $period, $accountsByCode);
        $result = (new ValidationService())->validate(array_values($accountsByCode), $rawAmounts, $previousAmounts);

        if (!empty($result['errors'])) {
            $this->view('reporting/financial_data_form', [
                'title' => 'Saisie financière — ' . $subsidiary->name . ' — ' . $period->label,
                'subsidiary' => $subsidiary,
                'period' => $period,
                'accountsByStatement' => [
                    'IS' => $this->accounts->forStatement('IS'),
                    'BS' => $this->accounts->forStatement('BS'),
                    'CF' => $this->accounts->forStatement('CF'),
                ],
                'rawAmounts' => $rawAmounts,
                'errors' => $result['errors'],
                'balance' => null,
                'canEdit' => true,
                'importResult' => [],
            ]);
            return;
        }

        $user = $this->currentUser();
        foreach ($accountsByCode as $code => $account) {
            $this->financialData->upsert($subsidiary->id, $period->id, $account->id, $result['parsed'][$code], $user->id);
        }

        if (!empty($result['warnings'])) {
            foreach ($result['warnings'] as $warning) {
                Session::flash('warning', $warning);
            }
        }
        Session::flash('success', 'Données enregistrées avec succès.');
        $this->redirect("/financial-data/{$subsidiary->id}/{$period->id}");
    }

    public function import(Request $request, string $subsidiaryId, string $periodId): void
    {
        $subsidiary = $this->subsidiaries->findById((int) $subsidiaryId);
        $period = $this->periods->findById((int) $periodId);
        if (!$subsidiary || !$period) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Introuvable']);
            return;
        }
        if ($period->isClosed()) {
            Session::flash('error', 'Cette période est clôturée : la saisie est verrouillée.');
            $this->redirect("/financial-data/{$subsidiary->id}/{$period->id}");
            return;
        }

        $accountsByCode = $this->accounts->allByCode();
        $result = (new CsvImportService())->import(
            $_FILES['csv'] ?? [],
            $subsidiary->id,
            $period->id,
            $accountsByCode,
            $this->currentUser(),
            $request
        );

        $this->show($request, $subsidiaryId, $periodId, $result);
    }

    /** @return array<string,float> */
    private function previousAmounts(int $subsidiaryId, ReportingPeriod $period, array $accountsByCode): array
    {
        $previousMonth = $period->month === 1 ? 12 : $period->month - 1;
        $previousYear = $period->month === 1 ? $period->year - 1 : $period->year;

        $previous = null;
        foreach ($this->periods->all() as $p) {
            if ($p->year === $previousYear && $p->month === $previousMonth) {
                $previous = $p;
                break;
            }
        }
        if (!$previous) {
            return [];
        }

        $amounts = [];
        foreach (['REV', 'IC_REVENUE'] as $code) {
            if (!isset($accountsByCode[$code])) {
                continue;
            }
            $value = $this->financialData->previousPeriodAmount($subsidiaryId, $previous->id, $accountsByCode[$code]->id);
            if ($value !== null) {
                $amounts[$code] = $value;
            }
        }
        return $amounts;
    }
}
