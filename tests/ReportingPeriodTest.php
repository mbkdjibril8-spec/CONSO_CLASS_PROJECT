<?php

declare(strict_types=1);

use App\Models\ReportingPeriod;

TestRunner::test('labelFor — mois sur 2 chiffres', function () {
    assert_equal('2026-01', ReportingPeriod::labelFor(2026, 1), 'Le mois doit être zéro-paddé (pas "2026-1")');
    assert_equal('2026-12', ReportingPeriod::labelFor(2026, 12));
});

TestRunner::test('labelFor — bascule d\'année', function () {
    assert_equal('2027-01', ReportingPeriod::labelFor(2027, 1), 'Premier mois de l\'exercice suivant (bascule PeriodService)');
});

TestRunner::test('nextStatus — enchaînement complet jusqu\'à clôture', function () {
    $sequence = ['open', 'in_progress', 'submitted', 'under_review', 'validated', 'consolidated', 'closed'];
    foreach ($sequence as $i => $status) {
        $period = new ReportingPeriod(1, 2026, 1, '2026-01', $status);
        $expected = $sequence[$i + 1] ?? null;
        assert_equal($expected, $period->nextStatus(), "Depuis « {$status} »");
    }
});

TestRunner::test('isClosed', function () {
    assert_true((new ReportingPeriod(1, 2026, 12, '2026-12', 'closed'))->isClosed());
    assert_true(!(new ReportingPeriod(1, 2026, 12, '2026-12', 'validated'))->isClosed());
});
