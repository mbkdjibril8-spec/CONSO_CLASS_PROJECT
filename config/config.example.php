<?php
/**
 * Modèle de configuration OHADA_CONSO+.
 * Copier ce fichier en "config.php" (à la racine de config/) et adapter
 * les valeurs à l'environnement local. config.php ne doit JAMAIS être versionné.
 */
return [
    'app' => [
        'name'      => 'OHADA_CONSO+',
        'env'       => 'local',
        // Chemin de base de l'application tel qu'exposé par Apache (XAMPP).
        // Exemple par défaut pour une installation dans C:\xampp\htdocs\groupfin
        'base_url'  => '/groupfin/public',
        'timezone'  => 'Africa/Dakar',
        'debug'     => true,
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'groupfin',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'groupfin_session',
        'lifetime' => 7200, // secondes
    ],
];
