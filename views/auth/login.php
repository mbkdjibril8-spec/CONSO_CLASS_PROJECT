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
        <svg class="auth-network" viewBox="0 0 560 760" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <g stroke="#ff9142" stroke-width="1" fill="none">
                <line x1="60" y1="620" x2="180" y2="540" opacity=".55"/>
                <line x1="180" y1="540" x2="150" y2="420" opacity=".35"/>
                <line x1="180" y1="540" x2="300" y2="560" opacity=".55"/>
                <line x1="300" y1="560" x2="260" y2="440" opacity=".4"/>
                <line x1="300" y1="560" x2="420" y2="500" opacity=".6"/>
                <line x1="420" y1="500" x2="470" y2="380" opacity=".4"/>
                <line x1="420" y1="500" x2="500" y2="600" opacity=".45"/>
                <line x1="260" y1="440" x2="150" y2="420" opacity=".3"/>
                <line x1="260" y1="440" x2="380" y2="330" opacity=".3"/>
                <line x1="470" y1="380" x2="380" y2="330" opacity=".35"/>
                <line x1="150" y1="420" x2="90" y2="300" opacity=".22"/>
                <line x1="380" y1="330" x2="440" y2="210" opacity=".22"/>
            </g>
            <g stroke="#2a7f8f" stroke-width="1" fill="none" opacity=".5">
                <line x1="60" y1="620" x2="150" y2="420"/>
                <line x1="300" y1="560" x2="470" y2="380"/>
                <line x1="90" y1="300" x2="150" y2="420"/>
            </g>
            <g fill="#ff9142">
                <circle cx="60" cy="620" r="4" opacity=".9"/>
                <circle cx="180" cy="540" r="5.5" opacity=".95"/>
                <circle cx="300" cy="560" r="6.5" opacity="1"/>
                <circle cx="420" cy="500" r="5" opacity=".9"/>
                <circle cx="260" cy="440" r="3.5" opacity=".7"/>
                <circle cx="470" cy="380" r="4" opacity=".8"/>
                <circle cx="150" cy="420" r="3" opacity=".6"/>
                <circle cx="380" cy="330" r="3" opacity=".55"/>
                <circle cx="500" cy="600" r="3" opacity=".6"/>
            </g>
            <g fill="#4fb3c7">
                <circle cx="90" cy="300" r="2.5" opacity=".55"/>
                <circle cx="440" cy="210" r="2.5" opacity=".45"/>
            </g>
        </svg>
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
