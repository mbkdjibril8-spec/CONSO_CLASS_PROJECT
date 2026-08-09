<?php
/** Ajustements de consolidation manuels (débit/crédit), pleinement auditables. */
$canCreate = in_array($user->roleCode, [\App\Models\Role::GROUP_ADMIN, \App\Models\Role::CONSOLIDATION_MANAGER], true);
?>
<div class="page-header">
    <div>
        <h1>Ajustements de consolidation</h1>
        <div class="subtitle">Écritures de retraitement manuelles au niveau groupe</div>
    </div>
</div>

<div class="panel" style="max-width:280px">
    <form method="get" action="<?= h(base_url('consolidation/adjustments')) ?>">
        <div class="field">
            <label for="period_id">Période</label>
            <select id="period_id" name="period_id" onchange="this.form.submit()">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= $p->id ?>" <?= $period && $period->id === $p->id ? 'selected' : '' ?>><?= h($p->label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($period && $canCreate && !$period->isClosed()): ?>
<div class="panel" style="max-width:520px">
    <div class="panel-title">Nouvel ajustement</div>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">Merci de corriger les champs signalés.</div>
    <?php endif; ?>
    <form method="post" action="<?= h(base_url('consolidation/adjustments')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="period_id" value="<?= $period->id ?>">

        <div class="field">
            <label for="account_id">Compte</label>
            <select id="account_id" name="account_id" required>
                <?php foreach ($accounts as $a): ?>
                    <option value="<?= $a->id ?>"><?= h($a->code) ?> — <?= h($a->label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="subsidiary_id">Filiale (optionnel — vide = écriture groupe)</label>
            <select id="subsidiary_id" name="subsidiary_id">
                <option value="">— Écriture au niveau groupe —</option>
                <?php foreach ($subsidiaries as $s): ?>
                    <option value="<?= $s->id ?>"><?= h($s->code) ?> — <?= h($s->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="debit_credit">Sens</label>
            <select id="debit_credit" name="debit_credit" required>
                <option value="debit">Débit</option>
                <option value="credit">Crédit</option>
            </select>
        </div>

        <div class="field">
            <label for="amount">Montant (XOF)</label>
            <input type="number" step="0.01" min="0.01" id="amount" name="amount" required>
        </div>

        <div class="field">
            <label for="reason">Motif (obligatoire)</label>
            <textarea id="reason" name="reason" rows="2" required></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Enregistrer l'ajustement</button>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-title">Historique <?= $period ? '— ' . h($period->label) : '' ?></div>
    <?php if (empty($adjustments)): ?>
        <div class="empty-state">Aucun ajustement pour cette période.</div>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>Compte</th><th>Filiale</th><th>Sens</th><th class="num">Montant</th><th>Motif</th><th>Par</th></tr></thead>
        <tbody>
        <?php foreach ($adjustments as $a): ?>
            <tr>
                <td><?= h(format_date($a['created_at'])) ?></td>
                <td><?= h($a['account_code']) ?></td>
                <td><?= h($a['subsidiary_name'] ?? 'Groupe') ?></td>
                <td><?= $a['debit_credit'] === 'debit' ? 'Débit' : 'Crédit' ?></td>
                <td class="num"><?= format_amount((float) $a['amount']) ?></td>
                <td><?= h($a['reason']) ?></td>
                <td><?= h($a['created_by_name']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
