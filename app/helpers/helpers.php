<?php

/**
 * Fonctions utilitaires globales chargées au bootstrap.
 * Regroupées ici (et non en classe statique) pour un usage direct dans les vues.
 */

use App\Core\Session;

/** Échappement systématique pour tout affichage de donnée en vue (anti-XSS). */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Envoie un tableau de lignes en téléchargement CSV (BOM UTF-8 pour un
 * import correct des accents dans Excel) et termine la requête. §2.14.
 * @param array<int, array<int, string|int|float|null>> $rows première ligne = en-têtes
 */
function stream_csv_download(string $filename, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

function config_value(string $dotted, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../../config/config.php';
    }
    $segments = explode('.', $dotted);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/** Préfixe une URL relative avec le chemin de base de l'application (config app.base_url). */
function base_url(string $path = ''): string
{
    $base = rtrim(config_value('app.base_url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * URL d'un asset statique avec cache-busting automatique (?v=mtime) : le
 * navigateur recharge le fichier dès qu'il change sur le disque, sans
 * jamais servir une version en cache après une modification (CSS/JS) —
 * évite d'avoir à faire un rechargement forcé (Ctrl+F5) à chaque ajustement.
 */
function asset(string $path): string
{
    $relative = 'assets/' . ltrim($path, '/');
    $absolute = __DIR__ . '/../../public/' . $relative;
    $version = is_file($absolute) ? filemtime($absolute) : null;
    return base_url($relative) . ($version ? '?v=' . $version : '');
}

/** Génère (ou réutilise) le jeton CSRF de la session courante. */
function csrf_token(): string
{
    $token = Session::get('_csrf_token');
    if (!$token) {
        $token = bin2hex(random_bytes(32));
        Session::set('_csrf_token', $token);
    }
    return $token;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    $expected = Session::get('_csrf_token');
    return is_string($token) && is_string($expected) && hash_equals($expected, $token);
}

/** Formate un montant selon les conventions financières du groupe (séparateur d'espace, 2 décimales). */
function format_amount(float $amount, string $currency = ''): string
{
    $formatted = number_format($amount, 2, ',', ' ');
    return $currency !== '' ? $formatted . ' ' . $currency : $formatted;
}

function format_date(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y H:i', $ts) : '';
}

/** Libellés français des statuts de période (utilisé dans plusieurs vues). */
function period_status_label(string $status): string
{
    $labels = [
        'open'          => 'Ouverte',
        'in_progress'   => 'En cours',
        'submitted'     => 'Soumise',
        'under_review'  => 'En revue',
        'validated'     => 'Validée',
        'consolidated'  => 'Consolidée',
        'closed'        => 'Clôturée',
    ];
    return $labels[$status] ?? $status;
}

/** Libellés français des méthodes de consolidation. */
function consolidation_method_label(string $method): string
{
    $labels = [
        'full'     => 'Intégration globale',
        'equity'   => 'Mise en équivalence',
        'excluded' => 'Exclue du périmètre',
    ];
    return $labels[$method] ?? $method;
}

function consolidation_method_badge_class(string $method): string
{
    $classes = [
        'full'     => 'badge-info',
        'equity'   => 'badge-warning',
        'excluded' => 'badge-neutral',
    ];
    return $classes[$method] ?? 'badge-neutral';
}

/** Libellés français des statuts de workflow d'un paquet filiale/période. */
/** Libellés/badges français des types de notification. */
function notification_type_label(string $type): string
{
    $labels = [
        'submission' => 'Soumission',
        'rejection' => 'Rejet',
        'mismatch' => 'Écart intercompany',
        'consolidation_ready' => 'Consolidation prête',
        'fiscal_year_opened' => 'Exercice ouvert',
    ];
    return $labels[$type] ?? $type;
}

function notification_type_badge_class(string $type): string
{
    $classes = [
        'submission' => 'badge-info',
        'rejection' => 'badge-negative',
        'mismatch' => 'badge-warning',
        'consolidation_ready' => 'badge-positive',
        'fiscal_year_opened' => 'badge-info',
    ];
    return $classes[$type] ?? 'badge-neutral';
}

function workflow_status_label(string $status): string
{
    $labels = [
        'draft'     => 'Brouillon',
        'submitted' => 'Soumis',
        'rejected'  => 'Rejeté',
        'validated' => 'Validé',
    ];
    return $labels[$status] ?? $status;
}

function workflow_status_badge_class(string $status): string
{
    $classes = [
        'draft'     => 'badge-neutral',
        'submitted' => 'badge-info',
        'rejected'  => 'badge-negative',
        'validated' => 'badge-positive',
    ];
    return $classes[$status] ?? 'badge-neutral';
}

/** Libellés français des types de déclaration intercompany. */
function intercompany_type_label(string $type): string
{
    $labels = [
        'receivable' => 'Créance',
        'payable' => 'Dette',
        'revenue' => 'Produit',
        'expense' => 'Charge',
        'dividend' => 'Dividende',
    ];
    return $labels[$type] ?? $type;
}

/** Libellés français des statuts de rapprochement intercompany. */
function match_status_label(string $status): string
{
    $labels = ['pending' => 'En attente', 'matched' => 'Rapproché', 'mismatch' => 'Écart'];
    return $labels[$status] ?? $status;
}

function match_status_badge_class(string $status): string
{
    $classes = ['pending' => 'badge-warning', 'matched' => 'badge-positive', 'mismatch' => 'badge-negative'];
    return $classes[$status] ?? 'badge-neutral';
}

/**
 * Icônes SVG inline pour la navigation latérale (une par onglet). Traits
 * simples (viewBox 24x24, stroke-based), aucune police d'icônes ni
 * dépendance externe — cohérent avec le reste de la plateforme (charts
 * SVG maison, voir app/helpers/charts.php). `currentColor` hérite la
 * couleur du lien parent : un seul style à animer au survol (voir
 * `.app-sidebar nav a svg` dans app.css) au lieu de dupliquer les couleurs
 * état par état dans chaque icône.
 */
function nav_icon(string $name): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.3"/><rect x="14" y="3" width="7" height="5" rx="1.3"/><rect x="14" y="12" width="7" height="9" rx="1.3"/><rect x="3" y="16" width="7" height="5" rx="1.3"/>',
        'subsidiaries' => '<rect x="5" y="3" width="14" height="18" rx="1"/><rect x="8" y="6.5" width="2.4" height="2.4"/><rect x="13.6" y="6.5" width="2.4" height="2.4"/><rect x="8" y="11.3" width="2.4" height="2.4"/><rect x="13.6" y="11.3" width="2.4" height="2.4"/><rect x="10" y="16.3" width="4" height="4.7"/>',
        'hierarchy' => '<circle cx="12" cy="4.6" r="2.1"/><circle cx="5" cy="19.4" r="2.1"/><circle cx="19" cy="19.4" r="2.1"/><path d="M12 6.7v4.3M12 11h-7v6.3M12 11h7v6.3"/>',
        'exchange' => '<path d="M4 7.5h13.5M17.5 7.5l-3-3M17.5 7.5l-3 3"/><path d="M20 16.5H6.5M6.5 16.5l3-3M6.5 16.5l3 3"/>',
        'consolidation' => '<path d="M12 3l8 4.5-8 4.5-8-4.5L12 3z"/><path d="M4 12.2l8 4.5 8-4.5"/><path d="M4 16.6l8 4.5 8-4.5"/>',
        'statement' => '<path d="M7 3h7l4 4v14H7V3z"/><path d="M14 3v4h4"/><line x1="9.5" y1="12.2" x2="15" y2="12.2"/><line x1="9.5" y1="15.8" x2="15" y2="15.8"/>',
        'audit' => '<rect x="5" y="4.3" width="14" height="16.7" rx="1.3"/><path d="M9 3h6v2.3H9z"/><path d="M8.6 12.2l2 2 4-4.2"/><line x1="8.6" y1="17" x2="15.4" y2="17"/>',
        'financial-data' => '<rect x="3" y="7" width="18" height="12" rx="1.3"/><path d="M3 10.2h18"/><circle cx="17" cy="14.7" r="1.3"/>',
        'intercompany' => '<rect x="2.5" y="9" width="6" height="6" rx="1"/><rect x="15.5" y="9" width="6" height="6" rx="1"/><path d="M8.5 10.6h7M15.5 10.6l-2-2M15.5 10.6l-2 2"/><path d="M15.5 13.4h-7M8.5 13.4l2-2M8.5 13.4l2 2"/>',
        'budgets' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4.4"/><circle cx="12" cy="12" r="1"/>',
        'periods' => '<rect x="4" y="5" width="16" height="15" rx="1.3"/><line x1="4" y1="9.5" x2="20" y2="9.5"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/>',
    ];
    $inner = $paths[$name] ?? '<circle cx="12" cy="12" r="8"/>';
    return '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/** Libellés français des rôles applicatifs. */
function role_label(string $code): string
{
    $labels = [
        'group_admin'            => 'Administrateur groupe',
        'preparer'                => 'Préparateur',
        'subsidiary_controller'   => 'Contrôleur de filiale',
        'consolidation_manager'   => 'Responsable consolidation',
        'cfo_readonly'            => 'Directeur financier (lecture)',
    ];
    return $labels[$code] ?? $code;
}

/**
 * Fil d'Ariane des écrans de détail : chaque entrée est [libellé, url] pour
 * un lien, ou [libellé, null] pour l'élément courant (dernier maillon).
 * Rend l'utilisateur capable de remonter d'un cran sans passer par le menu
 * latéral, et de savoir où il se trouve dans l'arborescence.
 * @param array<int, array{0:string, 1:string|null}> $items
 */
function render_breadcrumb(array $items): string
{
    if (empty($items)) {
        return '';
    }
    $arrow = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';

    $html = '<nav class="breadcrumb no-print" aria-label="Fil d\'Ariane">';
    $last = count($items) - 1;
    foreach ($items as $i => [$label, $url]) {
        if ($i > 0) {
            $html .= '<span class="sep" aria-hidden="true">/</span>';
        }
        if ($url !== null) {
            // Chevron uniquement sur le premier lien : c'est celui qui sert de "retour".
            $html .= '<a href="' . h(base_url($url)) . '">' . ($i === 0 ? $arrow : '') . h($label) . '</a>';
        } else {
            $html .= '<span class="current"' . ($i === $last ? ' aria-current="page"' : '') . '>' . h($label) . '</span>';
        }
    }
    return $html . '</nav>';
}
