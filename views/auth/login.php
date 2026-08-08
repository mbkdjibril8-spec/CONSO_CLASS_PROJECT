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
<div class="auth-page">

    <aside class="auth-brand-side">
        <div class="auth-wordmark">GROUPFIN<span class="dot">.</span> <span class="org">NOVA AFRICA GROUP</span></div>

        <div class="auth-brand-mid">
            <div class="eyebrow">Consolidation &amp; reporting de groupe</div>
            <h1>Une vision consolidée, filiale par filiale.</h1>
            <p>Collecte, validation et consolidation financière multi-devises pour un groupe multi-filiales — du préparateur au comité de direction.</p>
            <div class="auth-footprint">
                <span>Sénégal</span>
                <span>Côte d'Ivoire</span>
                <span>Mali</span>
                <span>France</span>
                <span>Maroc</span>
                <span>Ghana</span>
            </div>
        </div>

        <div class="auth-brand-foot">Devise de consolidation : XOF (Franc CFA — BCEAO)</div>
    </aside>

    <div class="auth-form-side">
        <div class="auth-form-card">
            <h2>Connexion</h2>
            <div class="subtitle">Accédez à votre espace de reporting.</div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= h(base_url('login')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus autocomplete="username" placeholder="prenom.nom@novaafrica.com">
                </div>
                <div class="field">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••••">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
            </form>
        </div>
    </div>

</div>
</body>
</html>
