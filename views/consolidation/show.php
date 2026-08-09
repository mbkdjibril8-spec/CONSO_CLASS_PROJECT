<?php
/**
 * Détail d'un run de consolidation : journal des étapes, compte de
 * résultat consolidé, bilan consolidé, intérêts minoritaires, éliminations,
 * taux de change utilisés. Voir docs/CONSOLIDATION_LOGIC.md pour les formules.
 */
$stepBadge = ['pending' => 'badge-neutral', 'running' => 'badge-warning', 'done' => 'badge-positive', 'failed' => 'badge-negative'];
$stepLabel = ['pending' => 'En attente', 'running' => 'En cours', 'done' => 'Terminé', 'failed' => 'Échoué'];
$runBadge = ['completed' => 'badge-positive', 'failed' => 'badge-negative', 'running' => 'badge-warning'];
$runLabel = ['completed' => 'Terminé', 'failed' => 'Échoué', 'running' => 'En cours'];
$acc = fn (string $code) => $accounts[$code]->label ?? $code;
$val = fn (string $code) => $lineItems[$code] ?? 0.0;
?>
<div class="page-header">
    <div>
        <h1>Run de consolidation — <?= h($run['period_label']) ?></h1>
        <div class="subtitle">
            <span class="badge <?= $runBadge[$run['status']] ?? 'badge-neutral' ?>"><?= h($runLabel[$run['status']] ?? $run['status']) ?></span>
            Lancé par <?= h($run['started_by_name']) ?> le <?= h(format_date($run['started_at'])) ?>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-title">Journal d'exécution</div>
    <table>
        <thead><tr><th>#</th><th>Étape</th><th>Statut</th><th>Détails</th></tr></thead>
        <tbody>
        <?php foreach ($steps as $s): ?>
            <tr>
                <td><?= (int) $s['step_order'] ?></td>
                <td><?= h($s['step_name']) ?></td>
                <td><span class="badge <?= $stepBadge[$s['status']] ?? 'badge-neutral' ?>"><?= h($stepLabel[$s['status']] ?? $s['status']) ?></span></td>
                <td class="text-muted" style="font-size:.85rem"><?= h($s['details'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($summary): ?>

<div class="panel">
    <div class="panel-title">Taux de change utilisés</div>
    <table>
        <thead><tr><th>Devise</th><th class="num">Taux moyen (résultat)</th><th class="num">Taux de clôture (bilan)</th></tr></thead>
        <tbody>
        <?php foreach ($rates as $code => $r): ?>
            <tr><td><?= h($code) ?></td><td class="num"><?= number_format($r['average'], 6, ',', ' ') ?></td><td class="num"><?= number_format($r['closing'], 6, ',', ' ') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="panel">
    <div class="panel-title">Compte de résultat consolidé (XOF)</div>
    <table>
        <tbody>
        <tr><td><?= h($acc('REV')) ?></td><td class="num"><?= format_amount($val('REV')) ?></td></tr>
        <tr><td><?= h($acc('IC_REVENUE')) ?></td><td class="num"><?= format_amount($val('IC_REVENUE')) ?></td></tr>
        <tr><td><?= h($acc('COGS')) ?></td><td class="num">(<?= format_amount($val('COGS')) ?>)</td></tr>
        <tr style="font-weight:600"><td>Marge brute</td><td class="num"><?= format_amount($val('REV') + $val('IC_REVENUE') - $val('COGS')) ?></td></tr>
        <tr><td><?= h($acc('OPEX_PERS')) ?></td><td class="num">(<?= format_amount($val('OPEX_PERS')) ?>)</td></tr>
        <tr><td><?= h($acc('OPEX_OTHER')) ?></td><td class="num">(<?= format_amount($val('OPEX_OTHER')) ?>)</td></tr>
        <tr><td><?= h($acc('IC_EXPENSE')) ?></td><td class="num">(<?= format_amount($val('IC_EXPENSE')) ?>)</td></tr>
        <tr style="font-weight:600"><td>EBITDA</td><td class="num"><?= format_amount($val('REV') + $val('IC_REVENUE') - $val('COGS') - $val('OPEX_PERS') - $val('OPEX_OTHER') - $val('IC_EXPENSE')) ?></td></tr>
        <tr><td><?= h($acc('DA')) ?></td><td class="num">(<?= format_amount($val('DA')) ?>)</td></tr>
        <tr style="font-weight:600"><td>Résultat d'exploitation (EBIT)</td><td class="num"><?= format_amount($val('REV') + $val('IC_REVENUE') - $val('COGS') - $val('OPEX_PERS') - $val('OPEX_OTHER') - $val('IC_EXPENSE') - $val('DA')) ?></td></tr>
        <tr><td><?= h($acc('FIN_INCOME')) ?></td><td class="num"><?= format_amount($val('FIN_INCOME')) ?></td></tr>
        <tr><td><?= h($acc('FIN_EXPENSE')) ?></td><td class="num">(<?= format_amount($val('FIN_EXPENSE')) ?>)</td></tr>
        <tr><td><?= h($acc('EQ_METHOD_INCOME')) ?></td><td class="num"><?= format_amount($summary['eqIncome']) ?></td></tr>
        <tr><td><?= h($acc('TAX')) ?></td><td class="num">(<?= format_amount($val('TAX')) ?>)</td></tr>
        <tr style="font-weight:700;border-top:2px solid var(--color-border-strong)"><td>Résultat net consolidé</td><td class="num"><?= format_amount($summary['totalNetIncome']) ?></td></tr>
        <tr><td class="text-muted">&nbsp;&nbsp;dont part du groupe</td><td class="num"><?= format_amount($summary['groupNetIncome']) ?></td></tr>
        <tr><td class="text-muted">&nbsp;&nbsp;dont part des minoritaires</td><td class="num"><?= format_amount($summary['minorityNetIncome']) ?></td></tr>
        </tbody>
    </table>
</div>

<div class="panel">
    <div class="panel-title">Bilan consolidé (XOF)</div>
    <table>
        <thead><tr><th colspan="2">Actif</th></tr></thead>
        <tbody>
        <tr><td><?= h($acc('FIXED_ASSETS')) ?></td><td class="num"><?= format_amount($val('FIXED_ASSETS')) ?></td></tr>
        <tr><td><?= h($acc('RECEIVABLES')) ?></td><td class="num"><?= format_amount($val('RECEIVABLES')) ?></td></tr>
        <tr><td><?= h($acc('IC_RECEIVABLE')) ?></td><td class="num"><?= format_amount($val('IC_RECEIVABLE')) ?></td></tr>
        <tr><td><?= h($acc('CASH')) ?></td><td class="num"><?= format_amount($val('CASH')) ?></td></tr>
        <tr><td><?= h($acc('EQ_METHOD_INVESTMENT')) ?></td><td class="num"><?= format_amount($summary['eqInvestment']) ?></td></tr>
        <tr style="font-weight:700;border-top:2px solid var(--color-border-strong)"><td>Total actif</td><td class="num"><?= format_amount($summary['totalAssets']) ?></td></tr>
        </tbody>
        <thead><tr><th colspan="2">Passif</th></tr></thead>
        <tbody>
        <tr><td><?= h($acc('PAYABLES')) ?></td><td class="num"><?= format_amount($val('PAYABLES')) ?></td></tr>
        <tr><td><?= h($acc('IC_PAYABLE')) ?></td><td class="num"><?= format_amount($val('IC_PAYABLE')) ?></td></tr>
        <tr><td><?= h($acc('FINANCIAL_DEBT')) ?></td><td class="num"><?= format_amount($val('FINANCIAL_DEBT')) ?></td></tr>
        <tr style="font-weight:600"><td>Total passif (dettes)</td><td class="num"><?= format_amount($summary['totalLiabilities']) ?></td></tr>
        </tbody>
        <thead><tr><th colspan="2">Capitaux propres</th></tr></thead>
        <tbody>
        <tr><td>Capitaux propres — part du groupe</td><td class="num"><?= format_amount($summary['groupEquity']) ?></td></tr>
        <tr><td>Intérêts minoritaires</td><td class="num"><?= format_amount($summary['minorityEquity']) ?></td></tr>
        <tr style="font-weight:700;border-top:2px solid var(--color-border-strong)"><td>Total passif + capitaux propres</td><td class="num"><?= format_amount($summary['totalLiabilities'] + $summary['groupEquity'] + $summary['minorityEquity']) ?></td></tr>
        </tbody>
    </table>
</div>

<?php if (!empty($minorityInterests)): ?>
<div class="panel">
    <div class="panel-title">Détail des intérêts minoritaires</div>
    <table>
        <thead><tr><th>Filiale</th><th class="num">% Minoritaire</th><th class="num">Résultat net</th><th class="num">Capitaux propres</th></tr></thead>
        <tbody>
        <?php foreach ($minorityInterests as $mi): ?>
            <tr>
                <td><?= h($mi['subsidiary_name']) ?> (<?= h($mi['subsidiary_code']) ?>)</td>
                <td class="num"><?= number_format((float) $mi['minority_pct'], 2, ',', ' ') ?> %</td>
                <td class="num"><?= format_amount((float) $mi['net_income_share']) ?></td>
                <td class="num"><?= format_amount((float) $mi['equity_share']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($eliminations)): ?>
<div class="panel">
    <div class="panel-title">Écritures d'élimination</div>
    <table>
        <thead><tr><th>Type</th><th>Filiale</th><th>Compte</th><th class="num">Montant</th></tr></thead>
        <tbody>
        <?php foreach ($eliminations as $e): ?>
            <tr>
                <td><?= $e['type'] === 'dividend' ? 'Dividende' : 'Intercompany' ?></td>
                <td><?= h($e['subsidiary_name']) ?> (<?= h($e['subsidiary_code']) ?>)</td>
                <td><?= h($e['account_label']) ?></td>
                <td class="num"><?= format_amount((float) $e['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>

<p><a href="<?= h(base_url('consolidation')) ?>">&larr; Retour aux runs</a></p>
