<?php
/**
 * Layout applicatif principal (topbar + sidebar + contenu).
 * Variables attendues : $title (string), $content (callable), $user (App\Models\User|null).
 */

use App\Core\Session;

$user = Session::get('user');
$flashes = Session::pullFlashes();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'GROUPFIN') ?> — GROUPFIN</title>
    <link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
</head>
<body>
<div class="app-shell">
    <header class="app-topbar">
        <div class="brand">GROUPFIN <span>· NOVA AFRICA GROUP</span></div>
        <div class="session-info">
            <?php if ($user): ?>
                <span><?= h($user->name) ?> — <?= h(role_label($user->roleCode)) ?></span>
                <form method="post" action="<?= h(base_url('logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-logout">Déconnexion</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($user): ?>
    <aside class="app-sidebar">
        <nav>
            <a href="<?= h(base_url('dashboard')) ?>" class="<?= str_contains($currentPath, '/dashboard') ? 'active' : '' ?>">Tableau de bord</a>
        </nav>
    </aside>
    <?php endif; ?>

    <main class="app-main" style="<?= $user ? '' : 'margin-left:0;width:100%;' ?>">
        <?php foreach ($flashes as $type => $messages): foreach ($messages as $message): ?>
            <div class="alert alert-<?= h($type) ?>"><?= h($message) ?></div>
        <?php endforeach; endforeach; ?>

        <?php $content(); ?>
    </main>
</div>
</body>
</html>
