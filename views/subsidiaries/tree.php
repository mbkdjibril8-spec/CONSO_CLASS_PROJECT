<?php
/**
 * Arbre de hiérarchie du groupe — org-chart visuel (boîtes reliées par des
 * traits, comme un organigramme), avec repli/dépli dynamique par filiale
 * (aucun rechargement de page). Remplace l'ancienne liste à puces imbriquée
 * (Phase 2) — même donnée ($tree, construite par SubsidiaryService::tree()),
 * seule la présentation change.
 */
$nodeCounter = 0;
$renderOrgNode = function (array $node) use (&$renderOrgNode, &$nodeCounter): void {
    $s = $node['subsidiary'];
    $nodeId = 'org-node-' . (++$nodeCounter);
    $hasChildren = !empty($node['children']);
    ?>
    <li class="org-node-wrap">
        <div class="org-node <?= $s->consolidationMethod === 'excluded' ? 'is-root' : '' ?>">
            <?php if ($hasChildren): ?>
                <button type="button" class="org-toggle" data-target="<?= h($nodeId) ?>" data-count="<?= count($node['children']) ?>" aria-expanded="true" title="Replier/déplier">&minus;</button>
            <?php endif; ?>
            <a href="<?= h(base_url('subsidiaries/' . $s->id)) ?>" class="org-node-link">
                <div class="org-node-code"><?= h($s->code) ?></div>
                <div class="org-node-name"><?= h($s->name) ?></div>
                <div class="org-node-meta">
                    <span class="text-faint"><?= h($s->country) ?></span>
                    <span class="badge <?= consolidation_method_badge_class($s->consolidationMethod) ?>"><?= h(consolidation_method_label($s->consolidationMethod)) ?></span>
                </div>
                <?php if ($s->consolidationMethod !== 'excluded'): ?>
                    <div class="org-node-pct"><?= number_format($s->ownershipPct, 0) ?>% détenu</div>
                <?php endif; ?>
            </a>
        </div>
        <?php if ($hasChildren): ?>
            <ul class="org-level" id="<?= h($nodeId) ?>">
                <?php foreach ($node['children'] as $child): $renderOrgNode($child); ?><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </li>
    <?php
};
?>
<div class="page-header">
    <div>
        <h1>Hiérarchie de groupe</h1>
        <div class="subtitle">NOVA AFRICA GROUP</div>
    </div>
</div>

<div class="panel">
    <?php if (empty($tree)): ?>
        <div class="empty-state">Aucune filiale enregistrée.</div>
    <?php else: ?>
        <div class="org-tree-scroll">
            <ul class="org-tree org-level">
                <?php foreach ($tree as $node): $renderOrgNode($node); endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    document.querySelectorAll('.org-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (evt) {
            evt.preventDefault();
            var target = document.getElementById(btn.getAttribute('data-target'));
            if (!target) { return; }
            var collapsed = target.classList.toggle('is-collapsed');
            btn.textContent = collapsed ? '+' + btn.getAttribute('data-count') : '−';
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            btn.closest('.org-node-wrap').classList.toggle('has-collapsed-children', collapsed);
        });
    });
})();
</script>

<p><a href="<?= h(base_url('subsidiaries')) ?>">&larr; Retour à la liste</a></p>
