<?php

declare(strict_types=1);

use App\Services\CurrencyConversionService;

$service = new CurrencyConversionService();
$rates = ['EUR' => ['average' => 655.96, 'closing' => 655.96], 'MAD' => ['average' => 65.60, 'closing' => 66.10]];

TestRunner::test('convert — XOF : identité (aucune conversion), même sans taux disponibles', function () use ($service) {
    $result = $service->convert(1000.0, 'XOF', 'BS', []);
    assert_float_equal(1000.0, $result);
});

TestRunner::test('convert — devise étrangère, compte de résultat (IS) : taux moyen', function () use ($service, $rates) {
    $result = $service->convert(1000.0, 'MAD', 'IS', $rates);
    assert_float_equal(65600.0, $result, 0.01, '1000 MAD x 65,60 (taux moyen)');
});

TestRunner::test('convert — devise étrangère, bilan (BS) : taux de clôture', function () use ($service, $rates) {
    $result = $service->convert(1000.0, 'MAD', 'BS', $rates);
    assert_float_equal(66100.0, $result, 0.01, '1000 MAD x 66,10 (taux de clôture) — distinct du taux moyen utilisé pour l\'IS');
});

TestRunner::test('convert — flux de trésorerie (CF) : taux moyen (comme IS, pas comme BS)', function () use ($service, $rates) {
    $result = $service->convert(1000.0, 'MAD', 'CF', $rates);
    assert_float_equal(65600.0, $result, 0.01);
});

TestRunner::test('convert — taux manquant pour la devise : null (jamais d\'estimation fictive)', function () use ($service) {
    $result = $service->convert(1000.0, 'GHS', 'IS', []);
    assert_null($result, 'Aucun taux GHS fourni : la conversion doit échouer explicitement, pas retourner une valeur inventée');
});
