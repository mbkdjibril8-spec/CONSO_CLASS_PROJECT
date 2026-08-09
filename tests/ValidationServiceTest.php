<?php

declare(strict_types=1);

use App\Models\Account;
use App\Services\ValidationService;

/**
 * Compte réduit (5 comptes) pour isoler l'équation bilancielle sans
 * dépendre du plan de comptes complet (22 comptes, Phase 3) ni de la base.
 * @return Account[]
 */
function make_test_accounts(): array
{
    return [
        new Account(1, 'REV', "Chiffre d'affaires", 'IS', 'Produits', 'credit', false, 1),
        new Account(2, 'COGS', 'Coût des ventes', 'IS', 'Charges', 'debit', false, 2),
        new Account(3, 'FIXED_ASSETS', 'Immobilisations', 'BS', 'Actif', 'debit', false, 3),
        new Account(4, 'CASH', 'Trésorerie', 'BS', 'Actif', 'debit', false, 4),
        new Account(5, 'SHARE_CAPITAL', 'Capital', 'BS', 'Capitaux propres', 'credit', false, 5),
        new Account(6, 'NET_CASH_FLOW', 'Flux net de trésorerie', 'CF', 'Flux', 'debit', false, 6),
    ];
}

$service = new ValidationService();

TestRunner::test('computeRevenue/Ebitda/NetIncome — cas simple', function () use ($service) {
    $amounts = ['REV' => 1000.0, 'COGS' => 400.0, 'OPEX_PERS' => 100.0, 'DA' => 50.0, 'TAX' => 30.0];
    assert_float_equal(1000.0, $service->computeRevenue($amounts));
    assert_float_equal(500.0, $service->computeEbitda($amounts), 0.01, 'EBITDA = 1000 - 400 - 100');
    assert_float_equal(420.0, $service->computeNetIncome($amounts), 0.01, 'Résultat net = 500 - 50 (DA) - 30 (impôt)');
});

TestRunner::test('validate — bilan équilibré, aucune erreur', function () use ($service) {
    $accounts = make_test_accounts();
    $raw = ['REV' => '1000', 'COGS' => '400', 'FIXED_ASSETS' => '200', 'CASH' => '800', 'SHARE_CAPITAL' => '400', 'NET_CASH_FLOW' => '0'];
    $result = $service->validate($accounts, $raw);
    assert_equal([], $result['errors'], 'Actif 1000 = Passif+CP+RN (400 capital + 600 résultat net)');
    assert_float_equal(600.0, $result['netIncome']);
});

TestRunner::test('validate — bilan déséquilibré au-delà de la tolérance : erreur bloquante', function () use ($service) {
    $accounts = make_test_accounts();
    // Actif 1100 vs Passif+CP+RN 1000 : écart de 100, largement au-dessus de la tolérance de 1 XOF.
    $raw = ['REV' => '1000', 'COGS' => '400', 'FIXED_ASSETS' => '200', 'CASH' => '900', 'SHARE_CAPITAL' => '400', 'NET_CASH_FLOW' => '0'];
    $result = $service->validate($accounts, $raw);
    assert_true(isset($result['errors']['_balance']), 'Un écart de 100 doit être rejeté');
});

TestRunner::test('validate — bruit d\'arrondi sous la tolérance (1 XOF) : accepté', function () use ($service) {
    $accounts = make_test_accounts();
    // Écart de 0,50 XOF : sous la tolérance fixée en Phase 4 pour absorber l'arrondi DECIMAL(18,2).
    $raw = ['REV' => '1000', 'COGS' => '400', 'FIXED_ASSETS' => '200', 'CASH' => '800.50', 'SHARE_CAPITAL' => '400', 'NET_CASH_FLOW' => '0'];
    $result = $service->validate($accounts, $raw);
    assert_true(!isset($result['errors']['_balance']), 'Un écart de 0,50 XOF ne doit pas bloquer (tolérance = 1 XOF)');
});

TestRunner::test('validate — champ obligatoire manquant', function () use ($service) {
    $accounts = make_test_accounts();
    $raw = ['REV' => '1000', 'COGS' => '400', 'FIXED_ASSETS' => '200', 'CASH' => '800', 'NET_CASH_FLOW' => '0']; // SHARE_CAPITAL manquant
    $result = $service->validate($accounts, $raw);
    assert_equal('Champ obligatoire.', $result['errors']['SHARE_CAPITAL'] ?? null);
});

TestRunner::test('validate — montant négatif refusé hors compte de flux (CF)', function () use ($service) {
    $accounts = make_test_accounts();
    $raw = ['REV' => '1000', 'COGS' => '-400', 'FIXED_ASSETS' => '200', 'CASH' => '800', 'SHARE_CAPITAL' => '400', 'NET_CASH_FLOW' => '0'];
    $result = $service->validate($accounts, $raw);
    assert_equal('Doit être positif ou nul.', $result['errors']['COGS'] ?? null);
});

TestRunner::test('validate — montant négatif accepté sur un compte CF', function () use ($service) {
    $accounts = make_test_accounts();
    $raw = ['REV' => '1000', 'COGS' => '400', 'FIXED_ASSETS' => '200', 'CASH' => '800', 'SHARE_CAPITAL' => '400', 'NET_CASH_FLOW' => '-150'];
    $result = $service->validate($accounts, $raw);
    assert_true(!isset($result['errors']['NET_CASH_FLOW']), 'Un flux de trésorerie négatif est une donnée normale');
});

TestRunner::test('validate — anomalie non bloquante : variation de revenu > 50%', function () use ($service) {
    $accounts = make_test_accounts();
    $raw = ['REV' => '2000', 'COGS' => '400', 'FIXED_ASSETS' => '200', 'CASH' => '1800', 'SHARE_CAPITAL' => '400', 'NET_CASH_FLOW' => '0'];
    $previous = ['REV' => 1000.0];
    $result = $service->validate($accounts, $raw, $previous);
    assert_equal([], $result['errors'], 'Anomalie non bloquante : ne doit jamais empêcher la sauvegarde');
    assert_true(count($result['warnings']) === 1, 'Doublement du CA (+100%) doit déclencher un avertissement');
});

TestRunner::test('validate — variation de revenu sous le seuil : aucun avertissement', function () use ($service) {
    $accounts = make_test_accounts();
    $raw = ['REV' => '1300', 'COGS' => '400', 'FIXED_ASSETS' => '200', 'CASH' => '1100', 'SHARE_CAPITAL' => '400', 'NET_CASH_FLOW' => '0'];
    $previous = ['REV' => 1000.0];
    $result = $service->validate($accounts, $raw, $previous);
    assert_equal([], $result['warnings'], 'Variation de +30% reste sous le seuil de 50%');
});
