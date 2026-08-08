<?php
/** Écran de connexion (page autonome, sans layout applicatif). */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — GROUPFIN</title>
    <link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth-screen">
    <div class="auth-card">
        <div class="brand">GROUPFIN<span>.</span></div>
        <div class="tagline">Consolidation financière — NOVA AFRICA GROUP</div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= h(base_url('login')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
        </form>
    </div>
</div>
</body>
</html>
