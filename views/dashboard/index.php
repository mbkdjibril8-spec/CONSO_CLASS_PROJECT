<?php
/**
 * Accueil applicatif. Contenu dépendant du rôle :
 *  - rôles groupe (admin, responsable consolidation, DAF) : vue d'ensemble des filiales ;
 *  - rôles filiale (préparateur, contrôleur) : fiche de leur propre filiale.
 * Toutes les données proviennent de la base (aucune valeur en dur).
 */
?>
<div class="page-header">
    <div>
        <h1>Tableau de bord</h1>
        <div class="subtitle">Connecté en tant que <?= h(role_label($user->roleCode)) ?></div>
    </div>
</div>

<?php if ($user->isGroupLevel()): ?>

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
