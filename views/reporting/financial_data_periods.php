<?php
/** Liste des périodes pour la saisie financière d'une filiale, avec indicateur de complétude. */
$isPreparer = $user->roleCode === \App\Models\Role::PREPARER;
?>
<div class="page-header">
    <div>
        <h1>Données financières</h1>
        <div class="subtitle"><?= h($subsidiary->name) ?> (<?= h($subsidiary->code) ?>)</div>
    </div>
</div>

<div class="panel">
    <table>
        <thead>
        <tr>
            <th>Période</th>
            <th>Statut période</th>
            <th class="num">Complétude</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): $p = $row['period']; $complete = $row['filled'] >= $totalAccounts; ?>
            <tr>
                <td><?= h($p->label) ?></td>
                <td><span class="badge <?= $p->isClosed() ? 'badge-neutral' : 'badge-info' ?>"><?= h(period_status_label($p->status)) ?></span></td>
                <td class="num">
                    <span class="badge <?= $complete ? 'badge-positive' : 'badge-warning' ?>"><?= $row['filled'] ?> / <?= $totalAccounts ?></span>
                </td>
                <td>
                    <a href="<?= h(base_url('financial-data/' . $subsidiary->id . '/' . $p->id)) ?>">
                        <?= $isPreparer && !$p->isClosed() ? 'Saisir / Modifier' : 'Consulter' ?> &rarr;
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
