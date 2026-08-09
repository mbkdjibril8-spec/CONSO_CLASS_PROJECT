<?php

declare(strict_types=1);

use App\Services\BudgetVarianceService;

$service = new BudgetVarianceService();

TestRunner::test('compute — produit (CA) : dépassement du budget = favorable', function () use ($service) {
    $r = $service->compute(1100.0, 1000.0, true);
    assert_float_equal(100.0, $r['variance']);
    assert_float_equal(10.0, $r['variancePct']);
    assert_true($r['favorable'], "Un CA au-dessus du budget est favorable");
});

TestRunner::test('compute — produit (CA) : en-dessous du budget = défavorable', function () use ($service) {
    $r = $service->compute(900.0, 1000.0, true);
    assert_true(!$r['favorable']);
});

TestRunner::test('compute — charge : dépenser plus que le budget = défavorable', function () use ($service) {
    $r = $service->compute(1100.0, 1000.0, false);
    assert_true(!$r['favorable'], 'Dépasser un budget de charges est défavorable, même sens de variance inversé vs un produit');
});

TestRunner::test('compute — charge : dépenser moins que le budget = favorable', function () use ($service) {
    $r = $service->compute(900.0, 1000.0, false);
    assert_true($r['favorable']);
});

TestRunner::test('compute — budget nul : pourcentage non calculable (évite une division par zéro)', function () use ($service) {
    $r = $service->compute(500.0, 0.0, true);
    assert_null($r['variancePct']);
    assert_float_equal(500.0, $r['variance']);
});
