<?php

declare(strict_types=1);

// Même autoloader maison que public/index.php (voir ce fichier pour le
// détail) — dupliqué ici volontairement pour que les tests ne dépendent
// d'aucune requête HTTP ni du front controller.
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    $className = array_pop($parts);
    $dir = strtolower(implode('/', $parts));
    $path = __DIR__ . '/../app/' . $dir . '/' . $className . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/../app/helpers/helpers.php';
require __DIR__ . '/../app/helpers/charts.php';
require __DIR__ . '/../app/helpers/ohada.php';
require __DIR__ . '/framework.php';
