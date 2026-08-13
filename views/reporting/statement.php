<?php
/** État financier au format normalisé OHADA/SYCEBNL (lecture seule). */
?>
<?= render_breadcrumb([
    ['Données financières', 'financial-data/' . $subsidiary->id],
    [$period->label, 'financial-data/' . $subsidiary->id . '/' . $period->id],
    ['États financiers', null],
]) ?>
<div class="page-header">
    <div>
        <h1><?= h($subsidiary->name) ?></h1>
        <div class="subtitle">États financiers — <?= h($period->label) ?></div>
    </div>
    <a href="<?= h(base_url('financial-data/' . $subsidiary->id . '/' . $period->id)) ?>" class="btn btn-outline">&larr; Retour à la saisie</a>
</div>

<?php if (!$complete): ?>
    <div class="alert alert-warning">Saisie incomplète pour cette période : les montants manquants sont affichés à 0,00.</div>
<?php endif; ?>

<div class="panel">
    <div class="ohada-header">Compte de résultat au 31 <?= h($period->label) ?></div>
    <?= render_ohada_income_statement($amounts) ?>
</div>

<div style="display:flex;gap:20px;flex-wrap:wrap">
    <div class="panel" style="flex:1 1 460px">
        <div class="ohada-header">Bilan — Actif</div>
        <?= render_ohada_balance_sheet_actif($amounts, $netIncome) ?>
    </div>
    <div class="panel" style="flex:1 1 460px">
        <div class="ohada-header">Bilan — Passif</div>
        <?= render_ohada_balance_sheet_passif($amounts, $netIncome) ?>
    </div>
</div>

<p class="text-faint">Présentation normalisée OHADA/SYCEBNL construite à partir du plan de comptes interne (22 comptes) — les lignes sans correspondance affichent 0,00. Voir <code>docs/CONSOLIDATION_LOGIC.md</code>.</p>
