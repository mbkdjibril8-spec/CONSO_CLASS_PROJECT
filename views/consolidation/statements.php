<?php
/**
 * Liasse groupe complète : compte de résultat + bilan consolidés au format
 * OHADA/SYCEBNL, pour la période sélectionnée (dernier run terminé de
 * cette période). Document de synthèse type "présentation CODIR" —
 * sélecteur de période, export CSV (données brutes) et PDF (impression).
 */
?>
<div id="ajax-content">
<div class="print-header">
    <div class="print-header-brand">NOVA AFRICA GROUP</div>
    <div>Liasse consolidée — <?= h($run['period_label'] ?? '') ?></div>
    <div class="print-header-meta">Généré le <?= h(date('d/m/Y \à H:i')) ?></div>
</div>

<div class="page-header">
    <div>
        <h1>Liasse groupe</h1>
        <div class="subtitle">États financiers consolidés au format OHADA/SYCEBNL</div>
    </div>
    <?php if ($run): ?>
    <div class="no-print">
        <a href="<?= h(base_url('exports/financial-statements/' . $run['id'])) ?>" class="btn btn-outline">Exporter la liasse (CSV)</a>
        <button type="button" class="btn btn-outline" onclick="window.print()">Exporter (PDF)</button>
        <a href="<?= h(base_url('consolidation/' . $run['id'])) ?>" class="btn btn-outline">Détail du run &rarr;</a>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($periodsWithRuns)): ?>
    <div class="panel no-print"><div class="empty-state">Aucune consolidation terminée pour l'instant. Lancez un run depuis le module <a href="<?= h(base_url('consolidation')) ?>">Consolidation</a>.</div></div>
<?php else: ?>

<div class="panel no-print">
    <form method="get" action="<?= h(base_url('financial-statements')) ?>" data-ajax-filter class="filter-bar">
        <div class="field">
            <label for="period_id">Période (dernier run terminé)</label>
            <select id="period_id" name="period_id" onchange="this.form.submit()">
                <?php foreach ($periodsWithRuns as $pid => $r): ?>
                    <option value="<?= (int) $pid ?>" <?= $selectedPeriodId === (int) $pid ? 'selected' : '' ?>><?= h($r['period_label']) ?> (run du <?= h(format_date($r['started_at'])) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($run && $summary): ?>

<div class="kpi-row">
    <div class="kpi">
        <div class="kpi-label">Résultat net consolidé</div>
        <div class="kpi-value"><?= format_compact_amount($summary['totalNetIncome']) ?> <span class="text-faint" style="font-size:.7rem">XOF</span></div>
        <div class="kpi-sub">dont part groupe <?= format_compact_amount($summary['groupNetIncome']) ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Total bilan (actif = passif)</div>
        <div class="kpi-value"><?= format_compact_amount($summary['totalAssets']) ?> <span class="text-faint" style="font-size:.7rem">XOF</span></div>
        <div class="kpi-sub">Capitaux propres part groupe <?= format_compact_amount($summary['groupEquity']) ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Intérêts minoritaires</div>
        <div class="kpi-value"><?= format_compact_amount($summary['minorityEquity']) ?> <span class="text-faint" style="font-size:.7rem">XOF</span></div>
        <div class="kpi-sub">Résultat minoritaires <?= format_compact_amount($summary['minorityNetIncome']) ?></div>
    </div>
</div>

<div class="panel">
    <div class="ohada-header">Compte de résultat consolidé au 31 <?= h($run['period_label']) ?></div>
    <?= render_ohada_income_statement($lineItems) ?>
</div>

<div class="panel-row">
    <div class="panel">
        <div class="ohada-header">Bilan consolidé — Actif</div>
        <?= render_ohada_balance_sheet_actif($lineItems, $summary['netIncomeFullAgg']) ?>
    </div>
    <div class="panel">
        <div class="ohada-header">Bilan consolidé — Passif</div>
        <?= render_ohada_balance_sheet_passif($lineItems, $summary['netIncomeFullAgg']) ?>
    </div>
</div>

<p class="text-faint">Présentation groupe (avant répartition part du groupe / minoritaires). Le résultat net ci-dessus (XI/CJ) est hors quote-part des sociétés mises en équivalence (<?= format_amount($summary['eqIncome']) ?> XOF, présente dans les titres mis en équivalence à l'actif) — voir le résumé ci-dessus pour le résultat net consolidé total.</p>

<?php endif; ?>
<?php endif; ?>
</div>
