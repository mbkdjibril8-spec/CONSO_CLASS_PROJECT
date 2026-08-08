<?php
/** Liste complète des filiales (rôles groupe uniquement). */
$isAdmin = $user->roleCode === \App\Models\Role::GROUP_ADMIN;
?>
<div class="page-header">
    <div>
        <h1>Filiales</h1>
        <div class="subtitle"><?= count($subsidiaries) ?> entité(s) dans la structure de groupe</div>
    </div>
    <?php if ($isAdmin): ?>
        <a href="<?= h(base_url('subsidiaries/create')) ?>" class="btn btn-primary">+ Nouvelle filiale</a>
    <?php endif; ?>
</div>

<div class="panel">
    <table>
        <thead>
        <tr>
            <th>Code</th>
            <th>Nom</th>
            <th>Pays</th>
            <th>Devise</th>
            <th class="num">% Détention</th>
            <th class="num">% Contrôle</th>
            <th>Méthode</th>
            <th>Statut</th>
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
                <td class="num"><?= number_format($s->controlPct, 2, ',', ' ') ?> %</td>
                <td><span class="badge <?= consolidation_method_badge_class($s->consolidationMethod) ?>"><?= h(consolidation_method_label($s->consolidationMethod)) ?></span></td>
                <td><?= $s->isActive ? '<span class="badge badge-positive">Active</span>' : '<span class="badge badge-neutral">Inactive</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p><a href="<?= h(base_url('subsidiaries/tree')) ?>">Voir la hiérarchie &rarr;</a></p>
