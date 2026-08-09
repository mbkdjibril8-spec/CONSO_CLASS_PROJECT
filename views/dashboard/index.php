<?php
/**
 * Tableau de bord. Vue CODIR (KPIs, tendance, contribution, alertes) pour
 * les rôles groupe ; vue filiale pour préparateur/contrôleur. Toutes les
 * données proviennent de ReportingService (aucune valeur en dur).
 */
$renderKpi = function (string $label, array $kpi, string $chartId) {
    $deltaClass = $kpi['favorable'] ? 'is-favorable' : 'is-unfavorable';
    $arrow = $kpi['variance'] >= 0 ? '&uarr;' : '&darr;';
    ?>
    <div class="kpi">
        <div class="kpi-label"><?= h($label) ?></div>
        <div class="kpi-value"><?= format_compact_amount($kpi['actual']) ?> <span class="text-faint" style="font-size:.7rem">XOF</span></div>
        <div class="kpi-sub">
            Budget <?= format_compact_amount($kpi['budget']) ?>
            <?php if ($kpi['variancePct'] !== null): ?>
                &middot; <span class="kpi-delta <?= $deltaClass ?>"><?= $arrow ?> <?= number_format(abs($kpi['variancePct']), 1, ',', ' ') ?>%</span>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
?>
<div id="ajax-content">
<div class="print-header">
    <div class="print-header-brand">NOVA AFRICA GROUP</div>
    <div>Tableau de bord CODIR<?= $period ? ' — ' . h($period->label) : '' ?></div>
    <div class="print-header-meta">Généré le <?= h(date('d/m/Y \à H:i')) ?> par <?= h($user->name) ?></div>
</div>
<div class="page-header">
    <div>
        <h1>Tableau de bord</h1>
        <div class="subtitle">Connecté en tant que <?= h(role_label($user->roleCode)) ?><?= $period ? ' · ' . h($period->label) : '' ?></div>
    </div>
    <?php if (isset($kpis)): ?>
        <div class="no-print">
            <button type="button" class="btn btn-outline" onclick="window.print()">Exporter en PDF</button>
        </div>
    <?php endif; ?>
</div>

<?php if (!$period): ?>
    <div class="panel"><div class="empty-state">Aucune période disponible.</div></div>
<?php else: ?>

<div class="panel no-print">
    <form method="get" action="<?= h(base_url('dashboard')) ?>" data-ajax-filter style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="margin:0">
            <label for="period_id">Période</label>
            <select id="period_id" name="period_id" onchange="this.form.submit()">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= $p->id ?>" <?= $period->id === $p->id ? 'selected' : '' ?>><?= h($p->label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($user->isGroupLevel()): ?>
            <div class="field" style="margin:0">
                <label for="country">Pays</label>
                <select id="country" name="country" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= h($c) ?>" <?= $countryFilter === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin:0">
                <label for="subsidiary_id">Filiale</label>
                <select id="subsidiary_id" name="subsidiary_id" onchange="this.form.submit()">
                    <option value="">Toutes (groupe)</option>
                    <?php foreach ($allSubsidiaries as $s): ?>
                        <option value="<?= $s->id ?>" <?= (string) $subsidiaryFilter === (string) $s->id ? 'selected' : '' ?>><?= h($s->code) ?> — <?= h($s->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </form>
    <?php if (isset($kpis)): ?>
        <?php
        $exportParams = ['period_id' => $period->id];
        if ($user->isGroupLevel()) {
            if ($subsidiaryFilter !== '') { $exportParams['subsidiary_id'] = $subsidiaryFilter; }
            if ($countryFilter !== '') { $exportParams['country'] = $countryFilter; }
        }
        ?>
        <a href="<?= h(base_url('exports/dashboard?' . http_build_query($exportParams))) ?>" class="btn btn-outline" style="margin-top:12px;display:inline-block">Exporter cette vue (CSV)</a>
    <?php endif; ?>
</div>

<?php if ($user->isGroupLevel() && !empty($alerts)): ?>
    <div class="panel">
        <div class="panel-title">Alertes</div>
        <div class="alert-list">
            <?php foreach ($alerts as $a): ?>
                <div class="alert-item sev-<?= h($a['severity']) ?>">
                    <span class="alert-dot"></span>
                    <span><?= h($a['message']) ?></span>
                    <?php if ($a['type'] === 'ready_to_consolidate'): ?>
                        <a href="<?= h(base_url('consolidation')) ?>" class="btn btn-outline" style="padding:4px 10px;font-size:.78rem">Lancer maintenant &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($kpis)): ?>
    <div class="kpi-row">
        <?php $renderKpi("Chiffre d'affaires", $kpis['revenue'], 'kpi-rev'); ?>
        <?php $renderKpi('EBITDA', $kpis['ebitda'], 'kpi-ebitda'); ?>
        <?php $renderKpi('Résultat net', $kpis['netIncome'], 'kpi-ni'); ?>
    </div>

    <?php
    $rev = $kpis['revenue']['actual'];
    $ebitdaMarginPct = $rev != 0.0 ? ($kpis['ebitda']['actual'] / $rev) * 100 : null;
    $netMarginPct = $rev != 0.0 ? ($kpis['netIncome']['actual'] / $rev) * 100 : null;
    ?>
    <?php if ($ebitdaMarginPct !== null): ?>
    <div class="kpi-row" style="margin-top:-10px">
        <div class="kpi">
            <div class="kpi-label">Marge EBITDA</div>
            <div class="kpi-value"><?= number_format($ebitdaMarginPct, 1, ',', ' ') ?> %</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Marge nette</div>
            <div class="kpi-value"><?= number_format($netMarginPct, 1, ',', ' ') ?> %</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($balanceSheetSummary)): ?>
    <div class="panel">
        <div class="panel-title">
            Situation bilancielle groupe — <?= h($period->label) ?>
            <a href="<?= h(base_url('financial-statements?period_id=' . $balanceSheetRun['period_id'])) ?>" style="float:right;font-weight:normal;text-transform:none;letter-spacing:normal">Voir la liasse complète &rarr;</a>
        </div>
        <?php $debtToEquity = ($balanceSheetSummary['groupEquity'] + $balanceSheetSummary['minorityEquity']) != 0.0
            ? ($balanceSheetSummary['totalLiabilities'] / ($balanceSheetSummary['groupEquity'] + $balanceSheetSummary['minorityEquity'])) * 100
            : null; ?>
        <div class="kpi-row">
            <div class="kpi">
                <div class="kpi-label">Total bilan consolidé</div>
                <div class="kpi-value"><?= format_compact_amount($balanceSheetSummary['totalAssets']) ?> <span class="text-faint" style="font-size:.7rem">XOF</span></div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Capitaux propres (groupe)</div>
                <div class="kpi-value"><?= format_compact_amount($balanceSheetSummary['groupEquity']) ?> <span class="text-faint" style="font-size:.7rem">XOF</span></div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Dettes totales</div>
                <div class="kpi-value"><?= format_compact_amount($balanceSheetSummary['totalLiabilities']) ?> <span class="text-faint" style="font-size:.7rem">XOF</span></div>
            </div>
            <?php if ($debtToEquity !== null): ?>
            <div class="kpi">
                <div class="kpi-label">Ratio d'endettement</div>
                <div class="kpi-value"><?= number_format($debtToEquity, 0, ',', ' ') ?> %</div>
                <div class="kpi-sub">Dettes / Capitaux propres totaux</div>
            </div>
            <?php endif; ?>
        </div>
        <p class="text-faint" style="margin-top:6px">Issu du dernier run de consolidation officiel de cette période (Phase 5) — distinct de la vision cumulée ci-dessus. Voir <a href="<?= h(base_url('financial-statements')) ?>">Liasse groupe</a> pour le détail complet.</p>
    </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-title">Évolution <?= h((string) $period->year) ?> — vision cumulée<?= $user->isGroupLevel() && $subsidiaryFilter === '' ? ' du groupe' : '' ?></div>
        <?= render_trend_chart($trend, 'trend-main') ?>
        <?php if ($user->isGroupLevel()): ?>
            <p class="text-faint" style="margin-top:10px">Somme des filiales en intégration globale, hors éliminations intercos et hors mise en équivalence — le résultat officiellement consolidé est produit par un run (module Consolidation).</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($user->isGroupLevel()): ?>

    <?php if (!empty($scorecard)): ?>
        <div class="panel">
            <div class="panel-title">Répartition par filiale — <?= h($period->label) ?></div>
            <?= render_composition_donut($scorecard, 'donut-repartition') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($contribution)): ?>
        <div class="panel">
            <div class="panel-title">Contribution EBITDA par filiale — <?= h($period->label) ?></div>
            <?= render_contribution_chart($contribution) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($scorecard)): ?>
        <div class="panel">
            <div class="panel-title">Performance par filiale — <?= h($period->label) ?></div>
            <table>
                <thead>
                <tr>
                    <th>Filiale</th>
                    <th class="num">CA Actual</th>
                    <th class="num">CA vs Budget</th>
                    <th class="num">EBITDA Actual</th>
                    <th class="num">EBITDA vs Budget</th>
                    <th class="num">Marge EBITDA</th>
                    <th class="num">Résultat net</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($scorecard as $row): ?>
                    <tr>
                        <td><a href="<?= h(base_url('subsidiaries/' . $row['subsidiary']->id)) ?>"><?= h($row['subsidiary']->code) ?></a> <span class="text-faint"><?= h($row['subsidiary']->country) ?></span></td>
                        <td class="num"><?= format_compact_amount($row['revenue']['actual']) ?></td>
                        <td class="num">
                            <?php if ($row['revenue']['variancePct'] !== null): ?>
                                <span class="kpi-delta <?= $row['revenue']['favorable'] ? 'is-favorable' : 'is-unfavorable' ?>"><?= $row['revenue']['variance'] >= 0 ? '&uarr;' : '&darr;' ?> <?= number_format(abs($row['revenue']['variancePct']), 1, ',', ' ') ?>%</span>
                            <?php else: ?>&mdash;<?php endif; ?>
                        </td>
                        <td class="num"><?= format_compact_amount($row['ebitda']['actual']) ?></td>
                        <td class="num">
                            <?php if ($row['ebitda']['variancePct'] !== null): ?>
                                <span class="kpi-delta <?= $row['ebitda']['favorable'] ? 'is-favorable' : 'is-unfavorable' ?>"><?= $row['ebitda']['variance'] >= 0 ? '&uarr;' : '&darr;' ?> <?= number_format(abs($row['ebitda']['variancePct']), 1, ',', ' ') ?>%</span>
                            <?php else: ?>&mdash;<?php endif; ?>
                        </td>
                        <td class="num"><?= $row['ebitdaMarginPct'] !== null ? number_format($row['ebitdaMarginPct'], 1, ',', ' ') . ' %' : '—' ?></td>
                        <td class="num"><?= format_compact_amount($row['netIncome']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="kpi-row">
        <div class="kpi">
            <div class="kpi-label">Filiales du groupe</div>
            <div class="kpi-value"><?= (int) $subsidiaryCount ?></div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Utilisateurs actifs</div>
            <div class="kpi-value"><?= (int) $userCount ?></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-title">
            Structure de groupe
            <a href="<?= h(base_url('subsidiaries/tree')) ?>" style="float:right;font-weight:normal;text-transform:none;letter-spacing:normal">Voir la hiérarchie &rarr;</a>
        </div>
        <?php if (empty($subsidiaries)): ?>
            <div class="empty-state">Aucune filiale enregistrée.</div>
        <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Code</th>
                <th>Nom</th>
                <th>Pays</th>
                <th>Devise</th>
                <th class="num">% Détention</th>
                <th>Méthode</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($subsidiaries as $s): ?>
                <tr>
                    <td><a href="<?= h(base_url('subsidiaries/' . $s->id)) ?>"><?= h($s->code) ?></a></td>
                    <td><?= h($s->name) ?></td>
                    <td><?= h($s->country) ?></td>
                    <td><?= h($s->currencyCode) ?></td>
                    <td class="num"><?= number_format($s->ownershipPct, 2, ',', ' ') ?> %</td>
                    <td><span class="badge <?= consolidation_method_badge_class($s->consolidationMethod) ?>"><?= h(consolidation_method_label($s->consolidationMethod)) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<?php else: ?>

    <?php if ($mySubsidiary): ?>
        <div class="panel">
            <div class="panel-title">Ma filiale</div>
            <h2><?= h($mySubsidiary->name) ?> <span class="text-faint">(<?= h($mySubsidiary->code) ?>)</span></h2>
            <p class="text-muted">
                <?= h($mySubsidiary->country) ?> · Devise <?= h($mySubsidiary->currencyCode) ?>
                · <span class="badge <?= consolidation_method_badge_class($mySubsidiary->consolidationMethod) ?>"><?= h(consolidation_method_label($mySubsidiary->consolidationMethod)) ?></span>
            </p>
            <a href="<?= h(base_url('subsidiaries/' . $mySubsidiary->id)) ?>" class="btn btn-outline">Voir la fiche filiale</a>
            <a href="<?= h(base_url('financial-data/' . $mySubsidiary->id)) ?>" class="btn btn-primary">Données financières</a>
        </div>
    <?php else: ?>
        <div class="panel">
            <div class="empty-state">Aucune filiale n'est affectée à votre compte. Contactez l'administrateur groupe.</div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php endif; ?>
</div>
