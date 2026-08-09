<?php
/** Taux de change moyen/clôture par devise pour une période sélectionnée. */
$isAdmin = $user->roleCode === \App\Models\Role::GROUP_ADMIN;
$readOnly = !$period || $period->isClosed() || !$isAdmin;
?>
<div id="ajax-content">
<div class="page-header">
    <div>
        <h1>Taux de change</h1>
        <div class="subtitle">1 unité de devise étrangère = X XOF</div>
    </div>
</div>

<div class="panel" style="max-width:280px">
    <form method="get" action="<?= h(base_url('exchange-rates')) ?>" data-ajax-filter>
        <div class="field">
            <label for="period_id">Période</label>
            <select id="period_id" name="period_id" onchange="this.form.submit()">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= $p->id ?>" <?= $period && $period->id === $p->id ? 'selected' : '' ?>>
                        <?= h($p->label) ?> — <?= h(period_status_label($p->status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if (!$period): ?>
    <div class="panel"><div class="empty-state">Aucune période disponible.</div></div>
<?php else: ?>

    <?php if ($period->isClosed()): ?>
        <div class="alert alert-info">Période clôturée : les taux affichés sont figés et non modifiables.</div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-title">Taux — <?= h($period->label) ?></div>
        <form method="post" action="<?= h(base_url('exchange-rates')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="period_id" value="<?= $period->id ?>">
            <table>
                <thead>
                <tr>
                    <th>Devise</th>
                    <th class="num">Taux moyen (résultat)</th>
                    <th class="num">Taux de clôture (bilan)</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($currencies as $c): $code = $c['code']; $avg = $rates[$code]['average'] ?? ''; $clo = $rates[$code]['closing'] ?? ''; ?>
                    <tr>
                        <td><?= h($code) ?> — <?= h($c['name']) ?></td>
                        <td class="num">
                            <input type="number" step="0.000001" min="0" name="rate_<?= h($code) ?>_average" value="<?= h((string) $avg) ?>" <?= $readOnly ? 'readonly' : 'required' ?> style="text-align:right;width:140px;display:inline-block">
                        </td>
                        <td class="num">
                            <input type="number" step="0.000001" min="0" name="rate_<?= h($code) ?>_closing" value="<?= h((string) $clo) ?>" <?= $readOnly ? 'readonly' : 'required' ?> style="text-align:right;width:140px;display:inline-block">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (!$readOnly): ?>
                <div style="margin-top:14px"><button type="submit" class="btn btn-primary">Enregistrer les taux</button></div>
            <?php endif; ?>
        </form>
    </div>

<?php endif; ?>
</div>
