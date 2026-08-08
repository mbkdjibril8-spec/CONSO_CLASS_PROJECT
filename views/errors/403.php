<?php
/** Accès refusé. Rendu à l'intérieur du layout applicatif principal. */
?>
<div class="panel" style="max-width:480px;margin:40px auto;text-align:center">
    <h1>403 — Accès refusé</h1>
    <p class="text-muted">Vous n'êtes pas autorisé à accéder à cette ressource. Cette tentative a été journalisée.</p>
    <a href="<?= h(base_url('dashboard')) ?>" class="btn btn-outline">Retour au tableau de bord</a>
</div>
