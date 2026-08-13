<?php
/** Fiche filiale. Actions de gestion (modifier/activer-désactiver) réservées à l'administrateur groupe. */
$isAdmin = $user->roleCode === \App\Models\Role::GROUP_ADMIN;
?>
<?php if ($user->isGroupLevel()): ?>
    <?= render_breadcrumb([['Filiales', 'subsidiaries'], [$subsidiary->code, null]]) ?>
<?php endif; ?>
<div class="page-header">
    <div>
        <h1><?= h($subsidiary->name) ?></h1>
        <div class="subtitle">Code <?= h($subsidiary->code) ?><?= $parent ? ' · Filiale de ' . h($parent->name) : '' ?></div>
    </div>
    <div>
        <?php if ($subsidiary->consolidationMethod !== 'excluded'): ?>
            <a href="<?= h(base_url('financial-data/' . $subsidiary->id)) ?>" class="btn btn-outline">Données financières</a>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
            <a href="<?= h(base_url('subsidiaries/' . $subsidiary->id . '/edit')) ?>" class="btn btn-outline">Modifier</a>
            <form method="post" action="<?= h(base_url('subsidiaries/' . $subsidiary->id . '/toggle-active')) ?>" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline"><?= $subsidiary->isActive ? 'Désactiver' : 'Réactiver' ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-title">Identité</div>
    <table>
        <tbody>
        <tr><td style="width:220px;color:var(--color-text-muted)">Pays</td><td><?= h($subsidiary->country) ?></td></tr>
        <tr><td style="color:var(--color-text-muted)">Zone</td><td><?= h($subsidiary->zone ?? '—') ?></td></tr>
        <tr><td style="color:var(--color-text-muted)">Activité</td><td><?= h($subsidiary->activity ?? '—') ?></td></tr>
        <tr><td style="color:var(--color-text-muted)">Société mère</td><td><?= $parent ? '<a href="' . h(base_url('subsidiaries/' . $parent->id)) . '">' . h($parent->name) . '</a>' : '— (tête de groupe)' ?></td></tr>
        <tr><td style="color:var(--color-text-muted)">Devise</td><td><?= h($subsidiary->currencyCode) ?></td></tr>
        <tr><td style="color:var(--color-text-muted)">% Détention (ownership)</td><td class="num"><?= number_format($subsidiary->ownershipPct, 2, ',', ' ') ?> %</td></tr>
        <tr><td style="color:var(--color-text-muted)">% Contrôle</td><td class="num"><?= number_format($subsidiary->controlPct, 2, ',', ' ') ?> %</td></tr>
        <tr><td style="color:var(--color-text-muted)">Méthode de consolidation</td><td><span class="badge <?= consolidation_method_badge_class($subsidiary->consolidationMethod) ?>"><?= h(consolidation_method_label($subsidiary->consolidationMethod)) ?></span></td></tr>
        <tr><td style="color:var(--color-text-muted)">Statut</td><td><?= $subsidiary->isActive ? '<span class="badge badge-positive">Active</span>' : '<span class="badge badge-neutral">Inactive</span>' ?></td></tr>
        </tbody>
    </table>
</div>

<p><a href="<?= h(base_url($user->isGroupLevel() ? 'subsidiaries' : 'dashboard')) ?>">&larr; Retour</a></p>
