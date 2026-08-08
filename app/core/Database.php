<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Connexion PDO unique (singleton) partagée par toute l'application.
 * Toutes les requêtes du projet doivent passer par des requêtes préparées.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/config.php';
            $db = $config['db'];

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['name'],
                $db['charset']
            );

            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // On ne fuite jamais les détails de connexion au client.
                error_log('[GROUPFIN][DB] ' . $e->getMessage());
                http_response_code(500);
                die('Erreur de connexion à la base de données. Vérifiez config/config.php.');
            }
        }

        return self::$instance;
    }
}
