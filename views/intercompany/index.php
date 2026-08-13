<?php
/** Consultation des déclarations intercompany (rapprochement automatique). */
$isPreparer = $user->roleCode === \App\Models\Role::PREPARER;
?>
<div id="ajax-content">
<div class="page-header">
    <div>
        <h1>Intercompany</h1>
        <div class="subtitle">Rapprochement automatique des soldes et flux intra-groupe</div>
    </div>
    <?php if ($isPreparer): ?>
        <a href="<?= h(base_url('intercompany/create')) ?>" class="btn btn-primary">+ Déclarer</a>
    <?php endif; ?>
</div>

<div class="panel" style="max-width:280px">
    <form method="get" action="<?= h(base_url('intercompany')) ?>" data-ajax-filter class="filter-bar">
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

<div class="panel">
    <?php if (empty($rows)): ?>
        <div class="empty-state">Aucune déclaration intercompany pour cette période.</div>
    <?php else: ?>
    <table>
        <thead>
        <tr>
            <th>Filiale déclarante</th>
            <th>Contrepartie</th>
            <th>Type</th>
            <th class="num">Montant local</th>
            <th class="num">Montant XOF</th>
            <th>Statut</th>
            <th class="num">Écart (XOF)</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= h($row['subsidiary_name']) ?> <span class="text-faint">(<?= h($row['subsidiary_code']) ?>)</span></td>
                <td><?= h($row['counterparty_name']) ?> <span class="text-faint">(<?= h($row['counterparty_code']) ?>)</span></td>
                <td><?= h(intercompany_type_label($row['type'])) ?><?= $row['type'] === 'dividend' ? ' <span class="text-faint">(déclaration unilatérale)</span>' : '' ?></td>
                <td class="num"><?= format_amount((float) $row['amount_local']) ?></td>
                <td class="num"><?= format_amount((float) $row['amount_group']) ?></td>
                <td><span class="badge <?= match_status_badge_class($row['match_status']) ?>"><?= h(match_status_label($row['match_status'])) ?></span></td>
                <td class="num"><?= $row['match_status'] === 'mismatch' ? format_amount((float) $row['difference_amount']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>
