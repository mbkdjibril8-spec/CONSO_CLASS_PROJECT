<?php
/** Visualiseur du journal d'audit, filtrable par utilisateur/filiale/période. */
?>
<div id="ajax-content">
<div class="page-header">
    <div>
        <h1>Journal d'audit</h1>
        <div class="subtitle"><?= count($logs) ?> entrée(s) (200 max affichées)</div>
    </div>
</div>

<div class="panel">
    <form method="get" action="<?= h(base_url('audit')) ?>" data-ajax-filter style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="margin:0">
            <label for="user_id">Utilisateur</label>
            <select id="user_id" name="user_id" onchange="this.form.submit()">
                <option value="">Tous</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u->id ?>" <?= $userIdFilter === $u->id ? 'selected' : '' ?>><?= h($u->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0">
            <label for="subsidiary_id">Filiale</label>
            <select id="subsidiary_id" name="subsidiary_id" onchange="this.form.submit()">
                <option value="">Toutes</option>
                <?php foreach ($subsidiaries as $s): ?>
                    <option value="<?= $s->id ?>" <?= $subsidiaryIdFilter === $s->id ? 'selected' : '' ?>><?= h($s->code) ?> — <?= h($s->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0">
            <label for="period_id">Période</label>
            <select id="period_id" name="period_id" onchange="this.form.submit()">
                <option value="">Toutes</option>
                <?php foreach ($periods as $p): ?>
                    <option value="<?= $p->id ?>" <?= $periodIdFilter === $p->id ? 'selected' : '' ?>><?= h($p->label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="panel">
    <?php if (empty($logs)): ?>
        <div class="empty-state">Aucune entrée pour ce filtre.</div>
    <?php else: ?>
    <table>
        <thead>
        <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Entité</th><th>Filiale</th><th>Période</th><th>Détail</th></tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td class="text-faint" style="white-space:nowrap"><?= h(format_date($log['created_at'])) ?></td>
                <td><?= h($log['user_name'] ?? 'Système') ?></td>
                <td><span class="badge badge-neutral"><?= h($log['action']) ?></span></td>
                <td><?= h($log['entity_type']) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></td>
                <td><?= h($log['subsidiary_code'] ?? '—') ?></td>
                <td><?= h($log['period_label'] ?? '—') ?></td>
                <td style="max-width:320px;overflow-wrap:break-word;font-size:.78rem" class="text-muted">
                    <?php if ($log['old_value']): ?><div>Avant : <?= h($log['old_value']) ?></div><?php endif; ?>
                    <?php if ($log['new_value']): ?><div>Après : <?= h($log['new_value']) ?></div><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>
