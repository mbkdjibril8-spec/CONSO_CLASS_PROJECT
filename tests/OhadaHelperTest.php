<?php

declare(strict_types=1);

TestRunner::test('ohada_income_statement_values — soldes intermédiaires de gestion', function () {
    $amounts = [
        'REV' => 1000.0, 'IC_REVENUE' => 0.0,
        'COGS' => 300.0, 'OPEX_OTHER' => 100.0, 'IC_EXPENSE' => 0.0,
        'OPEX_PERS' => 200.0,
        'DA' => 50.0,
        'FIN_INCOME' => 30.0, 'FIN_EXPENSE' => 10.0,
        'TAX' => 40.0,
    ];
    $v = ohada_income_statement_values($amounts);

    assert_float_equal(1000.0, $v['XB'], 0.01, "Chiffre d'affaires");
    assert_float_equal(600.0, $v['XC'], 0.01, 'Valeur ajoutée = 1000 - 300 (COGS) - 100 (RH) - 0 (RJ)');
    assert_float_equal(400.0, $v['XD'], 0.01, "EBE = VA 600 - charges de personnel 200");
    assert_float_equal(350.0, $v['XE'], 0.01, "Résultat d'exploitation = EBE 400 - dotations 50");
    assert_float_equal(20.0, $v['XF'], 0.01, 'Résultat financier = 30 - 10');
    assert_float_equal(370.0, $v['XG'], 0.01, 'Résultat des activités ordinaires = 350 + 20');
    assert_float_equal(330.0, $v['XI'], 0.01, 'Résultat net = 370 + 0 (HAO) - 40 (impôt)');
});

TestRunner::test('ohada_balance_sheet_values — bilan déjà équilibré : pas de plug', function () {
    // Actif (AI+AR+BI+BJ+BS) = 500+0+100+0+400 = 1000
    // Passif (CA+CH+CJ+DA+DJ+DM) = 600+0+400+0+0+0 = 1000
    $amounts = ['FIXED_ASSETS' => 500.0, 'RECEIVABLES' => 100.0, 'CASH' => 400.0, 'SHARE_CAPITAL' => 600.0];
    $v = ohada_balance_sheet_values($amounts, 400.0);

    assert_float_equal(0.0, $v['BU'], 0.01, "Écart de conversion-Actif nul quand le bilan est déjà équilibré");
    assert_float_equal(0.0, $v['DV'], 0.01, "Écart de conversion-Passif nul quand le bilan est déjà équilibré");
    assert_float_equal($v['BZ'], $v['DZ'], 0.01, 'BZ (total actif) doit toujours égaler DZ (total passif)');
    assert_float_equal(400.0, $v['CJ'], 0.01, 'CJ (résultat net au bilan) doit être exactement le netIncome fourni, jamais un plug');
});

TestRunner::test('ohada_balance_sheet_values — actif excédentaire : le passif (plus petit) rattrape via DV', function () {
    // Actif = 500+0+100+0+450 = 1050 ; Passif de base = 600+0+400+0+0+0 = 1000 : le passif est le côté
    // le plus petit, c'est donc lui qui reçoit le plug (DV) pour rattraper l'actif — jamais l'inverse.
    $amounts = ['FIXED_ASSETS' => 500.0, 'RECEIVABLES' => 100.0, 'CASH' => 450.0, 'SHARE_CAPITAL' => 600.0];
    $v = ohada_balance_sheet_values($amounts, 400.0);

    assert_float_equal(0.0, $v['BU'], 0.01);
    assert_float_equal(50.0, $v['DV'], 0.01, "L'écart de 50 doit être absorbé côté passif (Écart de conversion-Passif)");
    assert_float_equal($v['BZ'], $v['DZ'], 0.01, "Le bilan doit rester équilibré au centime près après le plug");
    assert_float_equal(400.0, $v['CJ'], 0.01, "Le résultat net ne doit JAMAIS servir de variable d'ajustement (règle documentée dans CONSOLIDATION_LOGIC.md)");
});

TestRunner::test('ohada_balance_sheet_values — passif excédentaire : l\'actif (plus petit) rattrape via BU', function () {
    // Actif = 500+0+100+0+400 = 1000 ; Passif de base = 650+0+400+0+0+0 = 1050 : l'actif est le côté
    // le plus petit, c'est donc lui qui reçoit le plug (BU).
    $amounts = ['FIXED_ASSETS' => 500.0, 'RECEIVABLES' => 100.0, 'CASH' => 400.0, 'SHARE_CAPITAL' => 650.0];
    $v = ohada_balance_sheet_values($amounts, 400.0);

    assert_float_equal(50.0, $v['BU'], 0.01, "L'écart de 50 doit être absorbé côté actif (Écart de conversion-Actif)");
    assert_float_equal(0.0, $v['DV'], 0.01);
    assert_float_equal($v['BZ'], $v['DZ'], 0.01);
});

TestRunner::test('render_ohada_income_statement / render_ohada_balance_sheet_* — cohérence XI = CJ', function () {
    $incomeAmounts = ['REV' => 1000.0, 'COGS' => 300.0, 'OPEX_PERS' => 200.0, 'TAX' => 40.0];
    $netIncome = ohada_income_statement_values($incomeAmounts)['XI'];

    $balanceAmounts = ['FIXED_ASSETS' => 500.0, 'CASH' => 500.0, 'SHARE_CAPITAL' => 400.0];
    $balanceValues = ohada_balance_sheet_values($balanceAmounts, $netIncome);

    assert_float_equal($netIncome, $balanceValues['CJ'], 0.01, 'Le résultat net doit être identique entre le compte de résultat (XI) et le bilan (CJ), jamais divergent');
});
