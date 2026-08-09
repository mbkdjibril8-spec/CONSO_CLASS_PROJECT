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
<div class="page-header">
    <div>
        <h1>Tableau de bord</h1>
        <div class="subtitle">Connecté en tant que <?= h(role_label($user->roleCode)) ?><?= $period ? ' · ' . h($period->label) : '' ?></div>
    </div>
</div>

<?php if (!$period): ?>
    <div class="panel"><div class="empty-state">Aucune période disponible.</div></div>
    <?php return; ?>
<?php endif; ?>

<div class="panel">
    <form method="get" action="<?= h(base_url('dashboard')) ?>" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
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

    <div class="panel">
        <div class="panel-title">Évolution <?= h((string) $period->year) ?> — vision cumulée<?= $user->isGroupLevel() && $subsidiaryFilter === '' ? ' du groupe' : '' ?></div>
        <?= render_trend_chart($trend, 'trend-main') ?>
        <?php if ($user->isGroupLevel()): ?>
            <p class="text-faint" style="margin-top:10px">Somme des filiales en intégration globale, hors éliminations intercos et hors mise en équivalence — le résultat officiellement consolidé est produit par un run (module Consolidation).</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($user->isGroupLevel()): ?>

    <?php if (!empty($contribution)): ?>
        <div class="panel">
            <div class="panel-title">Contribution EBITDA par filiale — <?= h($period->label) ?></div>
            <?= render_contribution_chart($contribution) ?>
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
