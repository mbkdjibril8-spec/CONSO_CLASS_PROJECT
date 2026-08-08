<?php
/** Déclaration d'un solde/flux intercompany (Préparateur, pour sa propre filiale). */
?>
<div class="page-header">
    <div><h1>Déclarer un solde intercompany</h1></div>
</div>

<?php if (!empty($errors['_global'])): ?>
    <div class="alert alert-error"><?= h($errors['_global']) ?></div>
<?php endif; ?>

<div class="panel" style="max-width:520px">
    <form method="post" action="<?= h(base_url('intercompany')) ?>">
        <?= csrf_field() ?>

        <div class="field">
            <label for="period_id">Période</label>
            <select id="period_id" name="period_id" required>
                <option value="">— Sélectionner —</option>
                <?php foreach ($periods as $p): ?>
                    <option value="<?= $p->id ?>" <?= (string) $values['period_id'] === (string) $p->id ? 'selected' : '' ?>><?= h($p->label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="counterparty_subsidiary_id">Filiale contrepartie</label>
            <select id="counterparty_subsidiary_id" name="counterparty_subsidiary_id" required>
                <option value="">— Sélectionner —</option>
                <?php foreach ($subsidiaries as $s): ?>
                    <option value="<?= $s->id ?>" <?= (string) $values['counterparty_subsidiary_id'] === (string) $s->id ? 'selected' : '' ?>><?= h($s->code) ?> — <?= h($s->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="type">Type</label>
            <select id="type" name="type" required>
                <?php foreach (['receivable' => 'Créance', 'payable' => 'Dette', 'revenue' => 'Produit', 'expense' => 'Charge', 'dividend' => 'Dividende'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $values['type'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="amount_local">Montant (devise locale de votre filiale)</label>
            <input type="number" step="0.01" min="0.01" id="amount_local" name="amount_local" value="<?= h((string) $values['amount_local']) ?>" required>
        </div>

        <p class="text-faint">La conversion en XOF utilise le taux de clôture (créance/dette) ou moyen (produit/charge/dividende) de la période sélectionnée. Le rapprochement avec la contrepartie est automatique.</p>

        <button type="submit" class="btn btn-primary">Déclarer</button>
        <a href="<?= h(base_url('intercompany')) ?>" class="btn btn-outline">Annuler</a>
    </form>
</div>
