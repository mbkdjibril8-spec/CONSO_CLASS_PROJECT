<?php
/**
 * Formulaire filiale (création + édition).
 * $values : tableau associatif snake_case (defaults, modèle existant, ou saisie invalide re-affichée).
 * $editingId : null en création, id en édition.
 */
$isEdit = $editingId !== null;
$action = $isEdit ? base_url('subsidiaries/' . $editingId) : base_url('subsidiaries');

$fieldError = fn (string $key): string => isset($errors[$key])
    ? '<div class="text-faint" style="color:var(--color-negative)">' . h($errors[$key]) . '</div>'
    : '';
?>
<div class="page-header">
    <div>
        <h1><?= h($title) ?></h1>
    </div>
</div>

<div class="panel" style="max-width:640px">
    <form method="post" action="<?= h($action) ?>">
        <?= csrf_field() ?>

        <div class="field">
            <label for="code">Code</label>
            <input type="text" id="code" name="code" value="<?= h((string) $values['code']) ?>" maxlength="20" required placeholder="ex: NOVA-XX">
            <?= $fieldError('code') ?>
        </div>

        <div class="field">
            <label for="name">Nom</label>
            <input type="text" id="name" name="name" value="<?= h((string) $values['name']) ?>" required>
            <?= $fieldError('name') ?>
        </div>

        <div class="field">
            <label for="country">Pays</label>
            <input type="text" id="country" name="country" value="<?= h((string) $values['country']) ?>" required>
            <?= $fieldError('country') ?>
        </div>

        <div class="field">
            <label for="zone">Zone</label>
            <input type="text" id="zone" name="zone" value="<?= h((string) $values['zone']) ?>" placeholder="ex: Afrique de l'Ouest">
        </div>

        <div class="field">
            <label for="activity">Activité</label>
            <input type="text" id="activity" name="activity" value="<?= h((string) $values['activity']) ?>" placeholder="ex: Distribution retail">
        </div>

        <div class="field">
            <label for="currency_code">Devise</label>
            <select id="currency_code" name="currency_code" required>
                <option value="">— Sélectionner —</option>
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= h($c['code']) ?>" <?= $values['currency_code'] === $c['code'] ? 'selected' : '' ?>>
                        <?= h($c['code']) ?> — <?= h($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('currency_code') ?>
        </div>

        <div class="field">
            <label for="parent_id">Société mère</label>
            <select id="parent_id" name="parent_id">
                <option value="">— Aucune (tête de groupe) —</option>
                <?php foreach ($parents as $p): ?>
                    <option value="<?= $p->id ?>" <?= (string) $values['parent_id'] === (string) $p->id ? 'selected' : '' ?>>
                        <?= h($p->code) ?> — <?= h($p->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('parent_id') ?>
        </div>

        <div class="field">
            <label for="ownership_pct">% Détention (ownership)</label>
            <input type="number" id="ownership_pct" name="ownership_pct" value="<?= h((string) $values['ownership_pct']) ?>" min="0" max="100" step="0.01" required>
            <?= $fieldError('ownership_pct') ?>
        </div>

        <div class="field">
            <label for="control_pct">% Contrôle</label>
            <input type="number" id="control_pct" name="control_pct" value="<?= h((string) $values['control_pct']) ?>" min="0" max="100" step="0.01" required>
            <?= $fieldError('control_pct') ?>
        </div>

        <div class="field">
            <label for="consolidation_method">Méthode de consolidation</label>
            <select id="consolidation_method" name="consolidation_method" required>
                <?php foreach (['full' => 'Intégration globale', 'equity' => 'Mise en équivalence', 'excluded' => 'Exclue du périmètre'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $values['consolidation_method'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('consolidation_method') ?>
        </div>

        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Enregistrer les modifications' : 'Créer la filiale' ?></button>
        <a href="<?= h(base_url($isEdit ? 'subsidiaries/' . $editingId : 'subsidiaries')) ?>" class="btn btn-outline">Annuler</a>
    </form>
</div>
