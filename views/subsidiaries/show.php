<?php
/** Fiche filiale en lecture seule (CRUD complet en Phase 2). */
?>
<div class="page-header">
    <div>
        <h1><?= h($subsidiary->name) ?></h1>
        <div class="subtitle">Code <?= h($subsidiary->code) ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel-title">Identité</div>
    <table>
        <tbody>
        <tr><td style="width:220px;color:var(--color-text-muted)">Pays</td><td><?= h($subsidiary->country) ?></td></tr>
        <tr><td style="color:var(--color-text-muted)">Zone</td><td><?= h($subsidiary->zone ?? '—') ?></td></tr>
        <tr><td style="color:var(--color-text-muted)">Activité</td><td><?= h($subsidiary->activity ?? '—') ?></td></tr>
        <tr><td style="color:var(--color-text-muted)">Devise</td><td><?= h($subsidiary->currencyCode) ?></td></tr>
        <tr><td style="color:var(--color-text-muted)">% Détention (ownership)</td><td class="num"><?= number_format($subsidiary->ownershipPct, 2, ',', ' ') ?> %</td></tr>
        <tr><td style="color:var(--color-text-muted)">% Contrôle</td><td class="num"><?= number_format($subsidiary->controlPct, 2, ',', ' ') ?> %</td></tr>
        <tr><td style="color:var(--color-text-muted)">Méthode de consolidation</td><td><span class="badge <?= consolidation_method_badge_class($subsidiary->consolidationMethod) ?>"><?= h(consolidation_method_label($subsidiary->consolidationMethod)) ?></span></td></tr>
        <tr><td style="color:var(--color-text-muted)">Statut</td><td><?= $subsidiary->isActive ? '<span class="badge badge-positive">Active</span>' : '<span class="badge badge-neutral">Inactive</span>' ?></td></tr>
        </tbody>
    </table>
</div>

<p><a href="<?= h(base_url('dashboard')) ?>">&larr; Retour au tableau de bord</a></p>
