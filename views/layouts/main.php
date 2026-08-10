<?php
/**
 * Layout applicatif principal (topbar + sidebar + contenu).
 * Variables attendues : $title (string), $content (callable), $user (App\Models\User|null).
 */

use App\Core\Session;
use App\Repositories\NotificationRepository;

$user = Session::get('user');
$flashes = Session::pullFlashes();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$unreadNotifications = $user ? (new NotificationRepository())->unreadCount($user->id) : 0;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'OHADA_CONSO+') ?> — OHADA_CONSO+</title>
    <link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
</head>
<body>
<div class="app-shell">
    <header class="app-topbar">
        <div class="brand">OHADA_CONSO+ <span>· NOVA AFRICA GROUP</span></div>
        <div class="session-info">
            <?php if ($user): ?>
                <a href="<?= h(base_url('notifications')) ?>" class="topbar-notif" title="Notifications">
                    &#128276;
                    <?php if ($unreadNotifications > 0): ?><span class="topbar-notif-badge"><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span><?php endif; ?>
                </a>
                <span><?= h($user->name) ?> — <?= h(role_label($user->roleCode)) ?></span>
                <form method="post" action="<?= h(base_url('logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-logout">Déconnexion</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($user):
        $navLink = function (string $path, string $label, string $icon) use ($currentPath) {
            $active = $currentPath === base_url($path) || str_starts_with($currentPath, base_url($path) . '/');
            echo '<a href="' . h(base_url($path)) . '" class="' . ($active ? 'active' : '') . '">'
                . nav_icon($icon) . '<span>' . h($label) . '</span></a>';
        };
    ?>
    <aside class="app-sidebar">
        <nav>
            <?php $navLink('dashboard', 'Tableau de bord', 'dashboard'); ?>
            <?php if ($user->isGroupLevel()): ?>
                <?php $navLink('subsidiaries', 'Filiales', 'subsidiaries'); ?>
                <?php $navLink('subsidiaries/tree', 'Hiérarchie', 'hierarchy'); ?>
                <?php $navLink('exchange-rates', 'Taux de change', 'exchange'); ?>
                <?php $navLink('consolidation', 'Consolidation', 'consolidation'); ?>
                <?php $navLink('financial-statements', 'Liasse groupe', 'statement'); ?>
                <?php $navLink('audit', "Journal d'audit", 'audit'); ?>
            <?php elseif ($user->subsidiaryId): ?>
                <?php $navLink('financial-data/' . $user->subsidiaryId, 'Données financières', 'financial-data'); ?>
            <?php endif; ?>
            <?php $navLink('intercompany', 'Intercompany', 'intercompany'); ?>
            <?php $navLink('budgets', 'Budget vs Actual', 'budgets'); ?>
            <?php $navLink('periods', 'Périodes', 'periods'); ?>
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
<script>
/**
 * Couche "filtres dynamiques" : intercepte la soumission des formulaires
 * marqués data-ajax-filter, récupère le fragment mis à jour en fetch()
 * (le contrôleur détecte X-Requested-With et rend la vue sans layout),
 * remplace #ajax-content sans recharger toute la page, et garde l'URL
 * synchronisée (retour arrière / rechargement restent corrects).
 */
(function () {
    function swap(url, pushState) {
        var container = document.getElementById('ajax-content');
        if (!container) { window.location.href = url; return; }
        container.classList.add('is-loading');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                var fresh = tmp.querySelector('#ajax-content');
                container.replaceWith(fresh || tmp);
                if (pushState) { window.history.pushState({ ajax: true }, '', url); }
            })
            .catch(function () { window.location.href = url; });
    }

    document.addEventListener('submit', function (evt) {
        var form = evt.target.closest('[data-ajax-filter]');
        if (!form) { return; }
        evt.preventDefault();
        var params = new URLSearchParams(new FormData(form));
        swap(form.getAttribute('action') + '?' + params.toString(), true);
    });

    window.addEventListener('popstate', function () {
        swap(window.location.href, false);
    });
})();
</script>
</body>
</html>
