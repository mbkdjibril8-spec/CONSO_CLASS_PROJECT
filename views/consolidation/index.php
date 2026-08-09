<?php
/** Liste des runs de consolidation + déclenchement d'un nouveau run. */
$canRun = in_array($user->roleCode, [\App\Models\Role::GROUP_ADMIN, \App\Models\Role::CONSOLIDATION_MANAGER], true);
?>
<div class="page-header">
    <div>
        <h1>Consolidation</h1>
        <div class="subtitle">Historique des runs et lancement d'une nouvelle consolidation</div>
    </div>
</div>

<?php if ($canRun): ?>
<div class="panel" style="max-width:420px">
    <div class="panel-title">Lancer une consolidation</div>
    <form method="post" action="<?= h(base_url('consolidation/run')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="period_id">Période</label>
            <select id="period_id" name="period_id" required>
                <?php foreach ($periods as $p): ?>
                    <option value="<?= $p->id ?>"><?= h($p->label) ?> — <?= h(period_status_label($p->status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Lancer le run</button>
    </form>
    <p class="text-faint" style="margin-top:10px">Le run échoue si un paquet filiale n'est pas encore validé pour la période choisie.</p>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-title">Runs précédents</div>
    <?php if (empty($runs)): ?>
        <div class="empty-state">Aucun run de consolidation exécuté pour l'instant.</div>
    <?php else: ?>
    <table>
        <thead>
        <tr><th>Période</th><th>Statut</th><th>Lancé par</th><th>Démarré</th><th>Terminé</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($runs as $r): ?>
            <tr>
                <td><?= h($r['period_label']) ?></td>
                <td>
                    <?php $cls = ['completed' => 'badge-positive', 'failed' => 'badge-negative', 'running' => 'badge-warning'][$r['status']] ?? 'badge-neutral'; ?>
                    <span class="badge <?= $cls ?>"><?= h(['completed' => 'Terminé', 'failed' => 'Échoué', 'running' => 'En cours'][$r['status']] ?? $r['status']) ?></span>
                </td>
                <td><?= h($r['started_by_name']) ?></td>
                <td><?= h(format_date($r['started_at'])) ?></td>
                <td><?= h(format_date($r['completed_at'])) ?></td>
                <td><a href="<?= h(base_url('consolidation/' . $r['id'])) ?>">Voir le détail &rarr;</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<p><a href="<?= h(base_url('consolidation/adjustments')) ?>">Gérer les ajustements de consolidation &rarr;</a></p>
