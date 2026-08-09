<?php
/**
 * Formulaire de saisie IS/BS/CF pour une filiale/période, avec workflow
 * de soumission/validation/rejet intégré (Phase 4).
 * $balance : résultat de ValidationService sur les données actuellement en
 * base (calculé uniquement si les 22 comptes sont déjà renseignés), ou null.
 * $errors : erreurs de validation d'une soumission qui vient d'échouer.
 */
$statementLabels = ['IS' => 'Compte de résultat', 'BS' => 'Bilan', 'CF' => 'Flux de trésorerie'];

$renderRow = function ($account) use ($rawAmounts, $errors, $canEdit) {
    $code = $account->code;
    $value = $rawAmounts[$code] ?? '';
    $hasError = isset($errors[$code]);
    ?>
    <tr>
        <td>
            <?= h($account->label) ?>
            <?php if ($account->isIntercompany): ?><span class="badge badge-info" style="margin-left:6px">Interco</span><?php endif; ?>
        </td>
        <td class="num" style="width:220px">
            <?php if ($canEdit): ?>
                <input type="number" step="0.01" name="amount_<?= h($code) ?>" value="<?= h((string) $value) ?>"
                       style="text-align:right;width:200px;display:inline-block;<?= $hasError ? 'border-color:var(--color-negative)' : '' ?>">
                <?php if ($hasError): ?><div class="text-faint" style="color:var(--color-negative)"><?= h($errors[$code]) ?></div><?php endif; ?>
            <?php else: ?>
                <span class="mono"><?= $value === '' ? '—' : format_amount((float) $value) ?></span>
            <?php endif; ?>
        </td>
    </tr>
    <?php
};
?>
<div class="page-header">
    <div>
        <h1><?= h($subsidiary->name) ?> — <?= h($period->label) ?></h1>
        <div class="subtitle">
            <span class="badge <?= $period->isClosed() ? 'badge-neutral' : 'badge-info' ?>"><?= h(period_status_label($period->status)) ?></span>
            <span class="badge <?= workflow_status_badge_class($workflowStatus) ?>"><?= h(workflow_status_label($workflowStatus)) ?></span>
        </div>
    </div>
    <div>
        <a href="<?= h(base_url('exports/financial-data/' . $subsidiary->id . '/' . $period->id)) ?>" class="btn btn-outline">Exporter (CSV)</a>
        <a href="<?= h(base_url('financial-data/' . $subsidiary->id . '/' . $period->id . '/statement')) ?>" class="btn btn-outline">Voir l'état financier &rarr;</a>
    </div>
</div>

<?php if ($workflowStatus === 'rejected' && $rejectionReason): ?>
    <div class="alert alert-error">
        <strong>Paquet rejeté par le contrôleur.</strong> Motif : <?= h($rejectionReason) ?>
        <?php if ($canEdit): ?><div style="margin-top:4px">Corrigez les données ci-dessous puis soumettez à nouveau.</div><?php endif; ?>
    </div>
<?php elseif ($workflowStatus === 'submitted'): ?>
    <div class="alert alert-info">Paquet soumis, en attente de validation par le contrôleur de filiale.</div>
<?php elseif ($workflowStatus === 'validated'): ?>
    <div class="alert alert-success">Paquet validé. Il sera repris par le prochain run de consolidation.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>La saisie n'a pas été enregistrée.</strong>
        <?= isset($errors['_balance']) ? '<div style="margin-top:6px">' . h($errors['_balance']) . '</div>' : ' Merci de corriger les champs signalés ci-dessous.' ?>
    </div>
<?php elseif ($balance !== null): ?>
    <?php if (isset($balance['errors']['_balance'])): ?>
        <div class="alert alert-error"><?= h($balance['errors']['_balance']) ?></div>
    <?php else: ?>
        <div class="alert alert-success">
            Bilan équilibré. Résultat net calculé : <strong><?= format_amount($balance['netIncome']) ?></strong>
        </div>
    <?php endif; ?>
    <?php foreach ($balance['warnings'] as $warning): ?>
        <div class="alert alert-warning"><?= h($warning) ?></div>
    <?php endforeach; ?>
<?php elseif ($canEdit): ?>
    <div class="alert alert-info">Saisie incomplète : le contrôle d'équilibre du bilan s'affichera une fois tous les comptes renseignés.</div>
<?php endif; ?>

<?php if (!empty($importResult)): ?>
    <div class="alert <?= empty($importResult['errors']) ? 'alert-success' : 'alert-warning' ?>">
        Import CSV : <?= (int) $importResult['imported'] ?> ligne(s) enregistrée(s)<?= !empty($importResult['errors']) ? ', ' . count($importResult['errors']) . ' erreur(s)' : '' ?>.
        <?php if (!empty($importResult['errors'])): ?>
            <ul style="margin:8px 0 0 0">
                <?php foreach ($importResult['errors'] as $err): ?>
                    <li>Ligne <?= (int) $err['line'] ?> : <?= h($err['message']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($canReview): ?>
    <div class="panel" style="border-color:var(--color-info)">
        <div class="panel-title">Revue du contrôleur</div>
        <form method="post" action="<?= h(base_url('financial-data/' . $subsidiary->id . '/' . $period->id . '/validate')) ?>" style="display:inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Valider le paquet</button>
        </form>
        <form method="post" action="<?= h(base_url('financial-data/' . $subsidiary->id . '/' . $period->id . '/reject')) ?>" style="display:inline-block;margin-left:8px">
            <?= csrf_field() ?>
            <input type="text" name="reason" placeholder="Motif du rejet (obligatoire)" required style="padding:7px 10px;border:1px solid var(--color-border-strong);border-radius:var(--radius);width:260px">
            <button type="submit" class="btn btn-outline">Rejeter</button>
        </form>
    </div>
<?php endif; ?>

<form method="post" action="<?= h(base_url('financial-data/' . $subsidiary->id . '/' . $period->id)) ?>">
    <?= csrf_field() ?>

    <?php foreach (['IS', 'BS', 'CF'] as $statementType): ?>
        <div class="panel">
            <div class="panel-title"><?= h($statementLabels[$statementType]) ?></div>
            <table>
                <tbody>
                <?php foreach ($accountsByStatement[$statementType] as $account): $renderRow($account); ?><?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <?php if ($canEdit): ?>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    <?php endif; ?>
    <a href="<?= h(base_url('financial-data/' . $subsidiary->id)) ?>" class="btn btn-outline">Retour aux périodes</a>
</form>

<?php if ($canEdit): ?>
    <div class="panel" style="max-width:520px">
        <div class="panel-title">Import CSV</div>
        <p class="text-muted" style="font-size:.83rem">
            Format attendu : 2 colonnes <code>account_code,amount</code> (avec en-tête), un fichier couvrant
            tout ou partie des <?= array_sum(array_map('count', $accountsByStatement)) ?> comptes du plan.
            Taille max. 1 Mo.
        </p>
        <form method="post" action="<?= h(base_url('financial-data/' . $subsidiary->id . '/' . $period->id . '/import')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field">
                <input type="file" name="csv" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-outline">Importer</button>
        </form>
    </div>
<?php endif; ?>

<?php if ($canSubmit): ?>
    <div class="panel" style="max-width:520px">
        <div class="panel-title">Soumission</div>
        <p class="text-muted" style="font-size:.83rem">Une fois soumis, le paquet n'est plus modifiable tant que le contrôleur ne l'a pas rejeté.</p>
        <form method="post" action="<?= h(base_url('financial-data/' . $subsidiary->id . '/' . $period->id . '/submit')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Soumettre pour validation</button>
        </form>
    </div>
<?php endif; ?>

<?php if (!empty($history)): ?>
    <div class="panel">
        <div class="panel-title">Historique du workflow</div>
        <table>
            <thead>
            <tr><th>Date</th><th>Utilisateur</th><th>Transition</th><th>Commentaire</th></tr>
            </thead>
            <tbody>
            <?php foreach ($history as $entry): ?>
                <tr>
                    <td><?= h(format_date($entry['created_at'])) ?></td>
                    <td><?= h($entry['user_name']) ?></td>
                    <td>
                        <?= $entry['from_status'] ? h(workflow_status_label($entry['from_status'])) . ' &rarr; ' : '' ?>
                        <span class="badge <?= workflow_status_badge_class($entry['to_status']) ?>"><?= h(workflow_status_label($entry['to_status'])) ?></span>
                    </td>
                    <td><?= h($entry['comment'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
