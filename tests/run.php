<?php

declare(strict_types=1);

/**
 * Lance toute la suite de tests. Usage : php tests/run.php
 * Code de sortie 0 si tout passe, 1 sinon (utilisable en CI).
 */
require __DIR__ . '/bootstrap.php';

$testFiles = glob(__DIR__ . '/*Test.php');
sort($testFiles);

foreach ($testFiles as $file) {
    echo basename($file) . "\n";
    require $file;
    echo "\n";
}

exit(TestRunner::summary());
