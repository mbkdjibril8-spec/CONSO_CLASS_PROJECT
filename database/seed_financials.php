<?php

/**
 * Génère les données financières (réel + budget) 2026 pour les 6 filiales
 * opérationnelles de NOVA AFRICA GROUP, avec une trajectoire cohérente par
 * filiale (voir cahier des charges §9). Script déterministe : deux
 * exécutions successives produisent des résultats identiques (upsert),
 * ce qui le rend rejouable comme le reste de database/seed.sql.
 *
 * Usage : php database/seed_financials.php
 *
 * Convention de génération (voir docs/CONSOLIDATION_LOGIC.md pour les
 * règles de calcul appliquées par l'application elle-même) :
 *  - Revenu mensuel = revenu de base x (1 + croissance)^(mois-1).
 *  - Les charges d'exploitation sont des ratios du revenu du mois.
 *  - Le résultat net N'EST PAS stocké : il est recalculé à l'affichage.
 *  - CASH est la valeur d'ajustement ("plug") qui garantit l'équation
 *    bilancielle par construction : Actif = Passif + Capitaux propres + RN.
 *  - Le budget applique un facteur unique par filiale à l'ensemble du
 *    compte de résultat réel (permet de piloter simplement le scénario
 *    "Maroc en sous-performance vs budget").
 */

declare(strict_types=1);

require __DIR__ . '/../app/core/Database.php';

use App\Core\Database;

$pdo = Database::connection();

/** @var array<string, array<string, mixed>> $profiles */
$profiles = [
    'NOVA-SN' => [ // Senegal Retail — moteur de profit du groupe (~32% de l'EBITDA)
        'baseRevenue' => 450_000_000, 'growth' => 0.008,
        'cogsRatio' => 0.58, 'opexPersRatio' => 0.14, 'opexOtherRatio' => 0.08,
        'da' => 12_000_000, 'finIncome' => 1_500_000, 'finExpense' => 4_000_000, 'taxRate' => 0.27,
        'fixedAssets' => 380_000_000, 'receivablesRatio' => 0.55, 'payablesRatio' => 0.45,
        'financialDebt' => 120_000_000, 'shareCapital' => 200_000_000, 'retainedEarningsInit' => 150_000_000,
        'budgetAdj' => 0.97, // réel ~3% au-dessus du budget
    ],
    'NOVA-CI' => [ // Côte d'Ivoire — croissance rapide
        'baseRevenue' => 220_000_000, 'growth' => 0.022,
        'cogsRatio' => 0.60, 'opexPersRatio' => 0.15, 'opexOtherRatio' => 0.09,
        'da' => 6_000_000, 'finIncome' => 500_000, 'finExpense' => 3_000_000, 'taxRate' => 0.27,
        'fixedAssets' => 140_000_000, 'receivablesRatio' => 0.50, 'payablesRatio' => 0.42,
        'financialDebt' => 60_000_000, 'shareCapital' => 90_000_000, 'retainedEarningsInit' => 40_000_000,
        'budgetAdj' => 0.93, // forte surperformance vs budget
    ],
    'NOVA-ML' => [ // Mali — stable
        'baseRevenue' => 150_000_000, 'growth' => 0.002,
        'cogsRatio' => 0.61, 'opexPersRatio' => 0.16, 'opexOtherRatio' => 0.09,
        'da' => 4_000_000, 'finIncome' => 300_000, 'finExpense' => 2_500_000, 'taxRate' => 0.27,
        'fixedAssets' => 95_000_000, 'receivablesRatio' => 0.48, 'payablesRatio' => 0.40,
        'financialDebt' => 40_000_000, 'shareCapital' => 60_000_000, 'retainedEarningsInit' => 35_000_000,
        'budgetAdj' => 1.00, // conforme au budget
    ],
    'NOVA-FR' => [ // France — faible marge (devise EUR)
        'baseRevenue' => 380_000, 'growth' => 0.005,
        'cogsRatio' => 0.72, 'opexPersRatio' => 0.15, 'opexOtherRatio' => 0.08,
        'da' => 8_000, 'finIncome' => 500, 'finExpense' => 3_000, 'taxRate' => 0.25,
        'fixedAssets' => 210_000, 'receivablesRatio' => 0.60, 'payablesRatio' => 0.50,
        'financialDebt' => 90_000, 'shareCapital' => 100_000, 'retainedEarningsInit' => 20_000,
        'budgetAdj' => 1.02, // légèrement en-deçà du budget
    ],
    'NOVA-MA' => [ // Morocco — sous-performance vs budget (devise MAD)
        'baseRevenue' => 2_600_000, 'growth' => 0.003,
        'cogsRatio' => 0.63, 'opexPersRatio' => 0.16, 'opexOtherRatio' => 0.09,
        'da' => 60_000, 'finIncome' => 5_000, 'finExpense' => 30_000, 'taxRate' => 0.31,
        'fixedAssets' => 1_800_000, 'receivablesRatio' => 0.52, 'payablesRatio' => 0.44,
        'financialDebt' => 700_000, 'shareCapital' => 900_000, 'retainedEarningsInit' => 300_000,
        'budgetAdj' => 1.18, // nettement en-deçà du budget
    ],
    'NOVA-GH' => [ // Ghana — mise en équivalence (devise GHS)
        'baseRevenue' => 1_800_000, 'growth' => 0.010,
        'cogsRatio' => 0.59, 'opexPersRatio' => 0.15, 'opexOtherRatio' => 0.08,
        'da' => 40_000, 'finIncome' => 3_000, 'finExpense' => 20_000, 'taxRate' => 0.25,
        'fixedAssets' => 1_100_000, 'receivablesRatio' => 0.50, 'payablesRatio' => 0.42,
        'financialDebt' => 300_000, 'shareCapital' => 500_000, 'retainedEarningsInit' => 180_000,
        'budgetAdj' => 0.98,
    ],
];

// Cas particulier du scénario de démonstration (cahier des charges §9) :
// créance interco Sénégal (100M XOF) vs dette interco France (95M XOF
// équivalent) en décembre 2026, déclarées comme mismatch en Phase 4.
$decemberIntercompany = [
    'NOVA-SN' => ['account' => 'IC_RECEIVABLE', 'amount' => 100_000_000.0],
    'NOVA-FR' => ['account' => 'IC_PAYABLE', 'amount' => 95_000_000 / 655.957],
];

$subsidiaryIds = [];
foreach ($pdo->query("SELECT id, code FROM subsidiaries WHERE code != 'NOVA-HLD'") as $row) {
    $subsidiaryIds[$row['code']] = (int) $row['id'];
}

$periodIds = [];
foreach ($pdo->query('SELECT id, month FROM reporting_periods WHERE year = 2026') as $row) {
    $periodIds[(int) $row['month']] = (int) $row['id'];
}

$accountIds = [];
foreach ($pdo->query('SELECT id, code FROM accounts') as $row) {
    $accountIds[$row['code']] = (int) $row['id'];
}

$upsertData = $pdo->prepare(
    'INSERT INTO financial_data (subsidiary_id, period_id, account_id, amount)
     VALUES (:subsidiary_id, :period_id, :account_id, :amount)
     ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_at = NOW()'
);
$upsertBudget = $pdo->prepare(
    'INSERT INTO budgets (subsidiary_id, period_id, account_id, amount)
     VALUES (:subsidiary_id, :period_id, :account_id, :amount)
     ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_at = NOW()'
);

function saveAmount(PDOStatement $stmt, int $subsidiaryId, int $periodId, int $accountId, float $amount): void
{
    $stmt->execute([
        'subsidiary_id' => $subsidiaryId,
        'period_id'     => $periodId,
        'account_id'    => $accountId,
        'amount'        => round($amount, 2),
    ]);
}

$totalRows = 0;

foreach ($profiles as $code => $p) {
    $subsidiaryId = $subsidiaryIds[$code];
    $cumulativeNetIncome = 0.0; // net income cumulé des mois PRÉCÉDENTS (exclut le mois en cours)

    for ($month = 1; $month <= 12; $month++) {
        $periodId = $periodIds[$month];

        // --- Compte de résultat réel -----------------------------------
        $revenue = $p['baseRevenue'] * (1 + $p['growth']) ** ($month - 1);
        $icRevenue = 0.0;
        $cogs = $revenue * $p['cogsRatio'];
        $opexPers = $revenue * $p['opexPersRatio'];
        $opexOther = $revenue * $p['opexOtherRatio'];
        $icExpense = 0.0;
        $da = $p['da'];
        $finIncome = $p['finIncome'];
        $finExpense = $p['finExpense'];

        $ebitda = $revenue + $icRevenue - $cogs - $opexPers - $opexOther - $icExpense;
        $ebit = $ebitda - $da;
        $preTax = $ebit + $finIncome - $finExpense;
        $tax = max(0, $preTax) * $p['taxRate'];
        $netIncome = $preTax - $tax;

        $isActual = [
            'REV' => $revenue, 'IC_REVENUE' => $icRevenue, 'COGS' => $cogs,
            'OPEX_PERS' => $opexPers, 'OPEX_OTHER' => $opexOther, 'IC_EXPENSE' => $icExpense,
            'DA' => $da, 'FIN_INCOME' => $finIncome, 'FIN_EXPENSE' => $finExpense, 'TAX' => $tax,
        ];

        foreach ($isActual as $accCode => $amount) {
            saveAmount($upsertData, $subsidiaryId, $periodId, $accountIds[$accCode], $amount);
            $totalRows++;
        }

        // --- Budget (même structure, facteur unique par filiale) -------
        $adj = $p['budgetAdj'];
        foreach ($isActual as $accCode => $amount) {
            saveAmount($upsertBudget, $subsidiaryId, $periodId, $accountIds[$accCode], $amount * $adj);
            $totalRows++;
        }

        // --- Bilan réel ---------------------------------------------------
        $icReceivable = 0.0;
        $icPayable = 0.0;
        if (isset($decemberIntercompany[$code]) && $month === 12) {
            $entry = $decemberIntercompany[$code];
            if ($entry['account'] === 'IC_RECEIVABLE') {
                $icReceivable = $entry['amount'];
            } else {
                $icPayable = $entry['amount'];
            }
        }

        $fixedAssets = $p['fixedAssets'];
        $receivables = $revenue * $p['receivablesRatio'];
        $payables = $revenue * $p['payablesRatio'];
        $financialDebt = $p['financialDebt'];
        $shareCapital = $p['shareCapital'];
        $retainedEarnings = $p['retainedEarningsInit'] + $cumulativeNetIncome;

        // CASH = valeur d'ajustement garantissant l'équation bilancielle.
        $cash = ($payables + $icPayable + $financialDebt + $shareCapital + $retainedEarnings + $netIncome)
              - ($fixedAssets + $receivables + $icReceivable);

        $bsActual = [
            'FIXED_ASSETS' => $fixedAssets, 'RECEIVABLES' => $receivables, 'IC_RECEIVABLE' => $icReceivable,
            'CASH' => $cash, 'PAYABLES' => $payables, 'IC_PAYABLE' => $icPayable,
            'FINANCIAL_DEBT' => $financialDebt, 'SHARE_CAPITAL' => $shareCapital,
            'RETAINED_EARNINGS' => $retainedEarnings,
        ];
        foreach ($bsActual as $accCode => $amount) {
            saveAmount($upsertData, $subsidiaryId, $periodId, $accountIds[$accCode], $amount);
            $totalRows++;
        }

        // --- Flux de trésorerie (informatif, non consolidé) --------------
        $cfActual = [
            'CF_OPERATING' => $netIncome + $da,
            'CF_INVESTING' => -0.6 * $da,
            'CF_FINANCING' => -0.1 * $da,
        ];
        foreach ($cfActual as $accCode => $amount) {
            saveAmount($upsertData, $subsidiaryId, $periodId, $accountIds[$accCode], $amount);
            $totalRows++;
        }

        $cumulativeNetIncome += $netIncome;
    }

    echo "OK  {$code} : 12 mois générés (réel + budget)\n";
}

echo "Terminé — {$totalRows} lignes insérées/mises à jour (financial_data + budgets).\n";
