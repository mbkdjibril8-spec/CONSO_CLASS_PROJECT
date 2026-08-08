<?php
/** Ressource introuvable. Rendu à l'intérieur du layout applicatif principal. */
?>
<div class="panel" style="max-width:480px;margin:40px auto;text-align:center">
    <h1>404 — Introuvable</h1>
    <p class="text-muted">La ressource demandée n'existe pas ou a été déplacée.</p>
    <a href="<?= h(base_url('dashboard')) ?>" class="btn btn-outline">Retour au tableau de bord</a>
</div>
