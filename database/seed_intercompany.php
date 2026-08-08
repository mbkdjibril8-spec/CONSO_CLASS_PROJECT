<?php

/**
 * Déclare le mismatch intercompany du scénario de démonstration (cahier
 * des charges §9) : créance NOVA Senegal (100M XOF) vs dette NOVA France
 * (95M XOF équivalent) sur décembre 2026 — écart volontaire de 5M XOF à
 * "investiguer puis résoudre" en direct dans l'application.
 *
 * Réutilise IntercompanyService::declare() (conversion de change + logique
 * de rapprochement réelles, pas de statut de match écrit à la main).
 * Les montants locaux correspondent exactement aux comptes IC_RECEIVABLE /
 * IC_PAYABLE déjà positionnés par seed_financials.php pour la cohérence
 * entre le bilan soumis et la déclaration intercompany.
 *
 * Usage : php database/seed_intercompany.php
 */

declare(strict_types=1);

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

use App\Core\Request;
use App\Repositories\ReportingPeriodRepository;
use App\Repositories\SubsidiaryRepository;
use App\Repositories\UserRepository;
use App\Services\IntercompanyService;

$subsidiaries = new SubsidiaryRepository();
$periods = new ReportingPeriodRepository();
$users = new UserRepository();
$service = new IntercompanyService();
$request = new Request();

$december = null;
foreach ($periods->all() as $p) {
    if ($p->year === 2026 && $p->month === 12) {
        $december = $p;
        break;
    }
}
if (!$december) {
    fwrite(STDERR, "Période décembre 2026 introuvable.\n");
    exit(1);
}

$senegal = $subsidiaries->findByCode('NOVA-SN');
$france = $subsidiaries->findByCode('NOVA-FR');
$preparerSN = $users->findByEmail('preparer.sn@novaafrica.com');
$preparerFR = $users->findByEmail('preparer.fr@novaafrica.com');

[$ok1, $err1] = $service->declare($senegal->id, $december->id, $france->id, 'receivable', 100_000_000.0, $preparerSN, $request);
echo ($ok1 ? 'OK  ' : 'ERR ') . 'NOVA-SN créance interco 100 000 000 XOF : ' . ($ok1 ? 'déclarée' : $err1) . "\n";

[$ok2, $err2] = $service->declare($france->id, $december->id, $senegal->id, 'payable', 95_000_000 / 655.957, $preparerFR, $request);
echo ($ok2 ? 'OK  ' : 'ERR ') . 'NOVA-FR dette interco (~95M XOF équiv.) : ' . ($ok2 ? 'déclarée' : $err2) . "\n";

echo "Terminé.\n";
