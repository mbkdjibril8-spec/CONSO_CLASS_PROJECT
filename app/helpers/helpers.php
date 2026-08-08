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

function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
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
