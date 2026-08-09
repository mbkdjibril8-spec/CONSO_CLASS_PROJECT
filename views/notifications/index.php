<?php
/** Centre de notifications de l'utilisateur connecté. */
$unread = count(array_filter($notifications, fn ($n) => !$n['is_read']));
?>
<div id="ajax-content">
<div class="page-header">
    <div>
        <h1>Notifications</h1>
        <div class="subtitle"><?= $unread ?> non lue(s) sur <?= count($notifications) ?></div>
    </div>
    <?php if ($unread > 0): ?>
        <form method="post" action="<?= h(base_url('notifications/read-all')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline">Tout marquer comme lu</button>
        </form>
    <?php endif; ?>
</div>

<div class="panel">
    <?php if (empty($notifications)): ?>
        <div class="empty-state">Aucune notification.</div>
    <?php else: ?>
    <table>
        <thead><tr><th></th><th>Type</th><th>Message</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($notifications as $n): ?>
            <tr style="<?= $n['is_read'] ? 'opacity:.6' : 'font-weight:600' ?>">
                <td><?= $n['is_read'] ? '' : '<span class="alert-dot" style="background:var(--color-primary);display:inline-block"></span>' ?></td>
                <td><span class="badge <?= notification_type_badge_class($n['type']) ?>"><?= h(notification_type_label($n['type'])) ?></span></td>
                <td style="font-weight:normal"><?= h($n['message']) ?></td>
                <td class="text-faint"><?= h(format_date($n['created_at'])) ?></td>
                <td>
                    <?php if (!$n['is_read']): ?>
                        <form method="post" action="<?= h(base_url('notifications/' . $n['id'] . '/read')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline" style="padding:3px 9px;font-size:.76rem">Marquer lu</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>
