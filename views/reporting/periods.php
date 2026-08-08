<?php
/** Cycle de vie des périodes de reporting. Transition réservée admin/responsable consolidation. */
$canTransition = in_array($user->roleCode, [\App\Models\Role::GROUP_ADMIN, \App\Models\Role::CONSOLIDATION_MANAGER], true);
?>
<div class="page-header">
    <div>
        <h1>Périodes de reporting</h1>
        <div class="subtitle">Exercice 2026</div>
    </div>
</div>

<div class="panel">
    <table>
        <thead>
        <tr>
            <th>Période</th>
            <th>Statut</th>
            <?php if ($canTransition): ?><th>Action</th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($periods as $p): ?>
            <tr>
                <td><?= h($p->label) ?></td>
                <td><span class="badge <?= $p->isClosed() ? 'badge-neutral' : 'badge-info' ?>"><?= h(period_status_label($p->status)) ?></span></td>
                <?php if ($canTransition): ?>
                <td>
                    <?php $next = $p->nextStatus(); ?>
                    <?php if ($next): ?>
                        <form method="post" action="<?= h(base_url('periods/' . $p->id . '/transition')) ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="to_status" value="<?= h($next) ?>">
                            <button type="submit" class="btn btn-outline" style="padding:4px 10px;font-size:.78rem">
                                &rarr; <?= h(period_status_label($next)) ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="text-faint">Clôturée</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
