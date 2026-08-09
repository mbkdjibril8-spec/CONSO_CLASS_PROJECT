<?php
/** Budget vs Actual : écart mensuel + cumul depuis janvier (YTD), par compte. */
$rowLabels = [
    'REVENUE_TOTAL' => "Chiffre d'affaires total",
    'REV' => 'dont Chiffre d\'affaires', 'IC_REVENUE' => 'dont Produits intercos',
    'COGS' => 'Coût des ventes', 'OPEX_PERS' => 'Charges de personnel', 'OPEX_OTHER' => 'Autres charges d\'exploitation',
    'IC_EXPENSE' => 'Charges intercos', 'DA' => 'Dotations aux amortissements',
    'EBITDA_TOTAL' => 'EBITDA',
    'FIN_INCOME' => 'Produits financiers', 'FIN_EXPENSE' => 'Charges financières', 'TAX' => 'Impôt sur les sociétés',
    'NET_INCOME_TOTAL' => 'Résultat net',
];
$subtotalRows = ['REVENUE_TOTAL', 'EBITDA_TOTAL', 'NET_INCOME_TOTAL'];

$renderVarianceCell = function (array $row) {
    if ($row['variancePct'] === null) {
        echo '<td class="num text-faint">—</td>';
        return;
    }
    $cls = $row['favorable'] ? 'is-favorable' : 'is-unfavorable';
    $arrow = $row['variance'] >= 0 ? '&uarr;' : '&darr;';
    echo '<td class="num"><span class="kpi-delta ' . $cls . '">' . $arrow . ' ' . number_format(abs($row['variancePct']), 1, ',', ' ') . '%</span></td>';
};
?>
<div class="page-header">
    <div>
        <h1>Budget vs Actual</h1>
        <div class="subtitle">Écarts mensuels et cumul depuis janvier (YTD)</div>
    </div>
</div>

<div class="panel">
    <form method="get" action="<?= h(base_url('budgets')) ?>" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="margin:0">
            <label for="period_id">Période</label>
            <select id="period_id" name="period_id" onchange="this.form.submit()">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= $p->id ?>" <?= $period && $period->id === $p->id ? 'selected' : '' ?>><?= h($p->label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($user->isGroupLevel()): ?>
            <div class="field" style="margin:0">
                <label for="subsidiary_id">Filiale</label>
                <select id="subsidiary_id" name="subsidiary_id" onchange="this.form.submit()">
                    <option value="">Toutes (groupe)</option>
                    <?php foreach ($subsidiaries as $s): ?>
                        <option value="<?= $s->id ?>" <?= (string) $subsidiaryFilter === (string) $s->id ? 'selected' : '' ?>><?= h($s->code) ?> — <?= h($s->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php if (!$detail): ?>
    <div class="panel"><div class="empty-state">Aucune donnée disponible pour cette sélection.</div></div>
<?php else: ?>
    <div class="panel">
        <div class="panel-title"><?= $period ? h($period->label) : '' ?> — mois vs cumul annuel (XOF)</div>
        <table>
            <thead>
            <tr>
                <th rowspan="2" style="vertical-align:bottom">Compte</th>
                <th colspan="4" style="text-align:center;border-bottom:1px solid var(--color-border)">Mois</th>
                <th colspan="4" style="text-align:center;border-bottom:1px solid var(--color-border)">Cumul (YTD)</th>
            </tr>
            <tr>
                <th class="num">Réel</th><th class="num">Budget</th><th class="num">Écart</th><th class="num">Écart %</th>
                <th class="num">Réel</th><th class="num">Budget</th><th class="num">Écart</th><th class="num">Écart %</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rowLabels as $code => $label): $isSubtotal = in_array($code, $subtotalRows, true); $m = $detail['month'][$code]; $y = $detail['ytd'][$code]; ?>
                <tr style="<?= $isSubtotal ? 'font-weight:700;border-top:1px solid var(--color-border-strong)' : '' ?>">
                    <td><?= h($label) ?></td>
                    <td class="num"><?= format_amount($m['actual']) ?></td>
                    <td class="num"><?= format_amount($m['budget']) ?></td>
                    <td class="num"><?= format_amount($m['variance']) ?></td>
                    <?php $renderVarianceCell($m); ?>
                    <td class="num"><?= format_amount($y['actual']) ?></td>
                    <td class="num"><?= format_amount($y['budget']) ?></td>
                    <td class="num"><?= format_amount($y['variance']) ?></td>
                    <?php $renderVarianceCell($y); ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-faint" style="margin-top:10px">Écart % : favorable (vert) si le réel dépasse le budget pour un produit, ou lui est inférieur pour une charge.</p>
    </div>
<?php endif; ?>
