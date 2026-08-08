<?php
/** Arbre de hiérarchie du groupe (récursif). */
$renderTreeNode = function (array $node) use (&$renderTreeNode): void {
    $s = $node['subsidiary'];
    echo '<li>';
    echo '<a href="' . h(base_url('subsidiaries/' . $s->id)) . '"><strong>' . h($s->code) . '</strong></a> ';
    echo h($s->name) . ' <span class="text-faint">(' . h($s->country) . ')</span> ';
    echo '<span class="badge ' . consolidation_method_badge_class($s->consolidationMethod) . '">' . h(consolidation_method_label($s->consolidationMethod)) . '</span>';
    if ($s->consolidationMethod !== 'excluded') {
        echo ' <span class="text-faint">' . number_format($s->ownershipPct, 0) . '% détenu</span>';
    }
    if (!empty($node['children'])) {
        echo '<ul>';
        foreach ($node['children'] as $child) {
            $renderTreeNode($child);
        }
        echo '</ul>';
    }
    echo '</li>';
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
        <ul class="tree-root">
            <?php foreach ($tree as $node): $renderTreeNode($node); endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<style>
.tree-root, .tree-root ul { list-style: none; margin: 0; padding-left: 22px; }
.tree-root { padding-left: 0; }
.tree-root li { margin: 10px 0; padding-left: 14px; border-left: 2px solid var(--color-border-strong); }
.tree-root > li { border-left: none; padding-left: 0; }
</style>

<p><a href="<?= h(base_url('subsidiaries')) ?>">&larr; Retour à la liste</a></p>
