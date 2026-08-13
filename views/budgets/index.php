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
        echo '<td class="num bva-variance-cell">—</td>';
        return;
    }
    $cls = $row['favorable'] ? 'is-favorable' : 'is-unfavorable';
    $arrow = $row['variance'] >= 0 ? '&uarr;' : '&darr;';
    $barPct = min(abs($row['variancePct']), 30) / 30 * 100;
    echo '<td class="num bva-variance-cell">'
        . '<span class="bva-bar-track"><span class="bva-bar ' . $cls . '" style="width:' . number_format($barPct, 1, '.', '') . '%"></span></span>'
        . '<span class="kpi-delta ' . $cls . '">' . $arrow . ' ' . number_format(abs($row['variancePct']), 1, ',', ' ') . '%</span>'
        . '</td>';
};
?>
<div id="ajax-content">
<div class="page-header">
    <div>
        <h1>Budget vs Actual</h1>
        <div class="subtitle">Écarts mensuels et cumul depuis janvier (YTD)</div>
    </div>
</div>

<div class="panel">
    <form method="get" action="<?= h(base_url('budgets')) ?>" data-ajax-filter style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
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
    <?php
    // Tuiles de synthèse : les 3 soldes clés, immédiatement lisibles sans
    // parcourir le tableau détaillé (même données, autre niveau de lecture).
    $heroCodes = ['REVENUE_TOTAL' => "Chiffre d'affaires", 'EBITDA_TOTAL' => 'EBITDA', 'NET_INCOME_TOTAL' => 'Résultat net'];
    ?>
    <div class="kpi-hero-row">
        <?php foreach ($heroCodes as $code => $label): $m = $detail['month'][$code]; ?>
            <div class="kpi-hero">
                <div class="kpi-hero-value"><?= format_compact_amount($m['actual']) ?> <span class="kpi-hero-suffix">XOF</span></div>
                <div class="kpi-hero-label"><?= h($label) ?> — mois</div>
                <?php if ($m['variancePct'] !== null): ?>
                    <span class="kpi-hero-delta <?= $m['favorable'] ? 'is-favorable' : 'is-unfavorable' ?>">
                        <?= $m['variance'] >= 0 ? '&uarr;' : '&darr;' ?> <?= number_format(abs($m['variancePct']), 1, ',', ' ') ?>% vs budget
                    </span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="panel">
        <div class="bva-toolbar no-print">
            <div class="panel-title" style="margin:0"><?= $period ? h($period->label) : '' ?> — détail par compte (XOF)</div>
            <div class="bva-scope-toggle" role="group" aria-label="Période affichée">
                <button type="button" class="bva-scope-btn is-active" data-scope="both">Mois + Cumul</button>
                <button type="button" class="bva-scope-btn" data-scope="month">Mois seul</button>
                <button type="button" class="bva-scope-btn" data-scope="ytd">Cumul seul</button>
            </div>
        </div>
        <div class="table-scroll">
        <table class="bva-table">
            <thead>
            <tr>
                <th rowspan="2" class="bva-account-col">Compte</th>
                <th colspan="4" class="bva-group-head col-month">Mois</th>
                <th colspan="4" class="bva-group-head col-ytd">Cumul (YTD)</th>
            </tr>
            <tr>
                <th class="num col-month">Réel</th><th class="num col-month">Budget</th><th class="num col-month">Écart</th><th class="num col-month">Écart %</th>
                <th class="num col-ytd">Réel</th><th class="num col-ytd">Budget</th><th class="num col-ytd">Écart</th><th class="num col-ytd">Écart %</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rowLabels as $code => $label): $isSubtotal = in_array($code, $subtotalRows, true); $m = $detail['month'][$code]; $y = $detail['ytd'][$code]; ?>
                <tr class="<?= $isSubtotal ? 'bva-subtotal' : '' ?>">
                    <td class="bva-account-col"><?= h($label) ?></td>
                    <td class="num col-month"><?= format_amount($m['actual']) ?></td>
                    <td class="num col-month bva-budget"><?= format_amount($m['budget']) ?></td>
                    <td class="num col-month"><?= format_amount($m['variance']) ?></td>
                    <?php $renderVarianceCell($m); ?>
                    <td class="num col-ytd"><?= format_amount($y['actual']) ?></td>
                    <td class="num col-ytd bva-budget"><?= format_amount($y['budget']) ?></td>
                    <td class="num col-ytd"><?= format_amount($y['variance']) ?></td>
                    <?php $renderVarianceCell($y); ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="text-faint" style="margin-top:10px">Écart % : favorable (vert) si le réel dépasse le budget pour un produit, ou lui est inférieur pour une charge. La barre indique l'ampleur de l'écart (saturée au-delà de 30 %).</p>
    </div>

    <script>
    /* Bascule d'affichage Mois / Cumul : masque les colonnes du groupe non
       retenu (aucun rechargement, aucune requête — les deux jeux de données
       sont déjà dans la page). */
    (function () {
        var toolbar = document.querySelector('#ajax-content .bva-scope-toggle');
        if (!toolbar) { return; }
        var table = document.querySelector('#ajax-content .bva-table');
        toolbar.addEventListener('click', function (evt) {
            var btn = evt.target.closest('.bva-scope-btn');
            if (!btn) { return; }
            toolbar.querySelectorAll('.bva-scope-btn').forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            var scope = btn.getAttribute('data-scope');
            table.classList.toggle('hide-month', scope === 'ytd');
            table.classList.toggle('hide-ytd', scope === 'month');
        });
    })();
    </script>
<?php endif; ?>
</div>
