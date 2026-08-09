<?php

declare(strict_types=1);

/**
 * Micro-framework de test (aucune dépendance Composer/PHPUnit, cohérent
 * avec la contrainte "zéro dépendance" du projet). Couvre la logique de
 * calcul pure (Validation, écarts budgétaires, conversion de devises,
 * mapping OHADA) — sans base de données. Le workflow, le RBAC et le
 * pipeline de consolidation restent couverts par le protocole de
 * vérification manuelle (HTTP réel via curl) documenté phase par phase
 * dans PROJECT_STATE.md : ce sont des comportements qui dépendent de
 * l'état en base et de l'authentification, hors du périmètre naturel
 * d'un test unitaire rapide.
 */
final class TestRunner
{
    private static int $passed = 0;
    private static int $failed = 0;

    public static function test(string $name, callable $fn): void
    {
        try {
            $fn();
            self::$passed++;
            echo "  OK    {$name}\n";
        } catch (\Throwable $e) {
            self::$failed++;
            echo "  ECHEC {$name}\n        " . $e->getMessage() . "\n";
        }
    }

    public static function summary(): int
    {
        $total = self::$passed + self::$failed;
        echo "\n{$total} test(s) : " . self::$passed . " réussi(s), " . self::$failed . " échoué(s).\n";
        return self::$failed > 0 ? 1 : 0;
    }
}

function assert_equal($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $prefix = $message !== '' ? "{$message} — " : '';
        throw new \RuntimeException($prefix . 'attendu ' . var_export($expected, true) . ', obtenu ' . var_export($actual, true));
    }
}

function assert_float_equal(float $expected, float $actual, float $delta = 0.01, string $message = ''): void
{
    if (abs($expected - $actual) > $delta) {
        $prefix = $message !== '' ? "{$message} — " : '';
        throw new \RuntimeException($prefix . "attendu {$expected}, obtenu {$actual} (écart > {$delta})");
    }
}

function assert_true($condition, string $message = ''): void
{
    if (!$condition) {
        throw new \RuntimeException($message !== '' ? $message : 'condition attendue vraie, obtenue fausse');
    }
}

function assert_null($value, string $message = ''): void
{
    if ($value !== null) {
        $prefix = $message !== '' ? "{$message} — " : '';
        throw new \RuntimeException($prefix . 'attendu null, obtenu ' . var_export($value, true));
    }
}
