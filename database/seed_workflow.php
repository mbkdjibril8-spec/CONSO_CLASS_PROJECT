<?php

/**
 * Positionne l'état de départ du scénario de démonstration (cahier des
 * charges §9) : au 1er décembre 2026, 5 des 6 filiales opérationnelles ont
 * déjà soumis leur paquet, le Maroc est encore en attente. La suite du
 * scénario (rejet Maroc, correction, revalidation, consolidation) se joue
 * ensuite EN DIRECT dans l'application — elle n'est pas pré-enregistrée ici,
 * pour que le workflow reste une fonctionnalité réellement démontrée et non
 * un état figé.
 *
 * Réutilise WorkflowService::submit() (pas de SQL brut) : garantit que les
 * données générées par seed_financials.php sont bien complètes et
 * équilibrées avant de les marquer "soumises".
 *
 * Usage : php database/seed_workflow.php
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
use App\Services\WorkflowService;

$subsidiaries = new SubsidiaryRepository();
$periods = new ReportingPeriodRepository();
$users = new UserRepository();
$workflow = new WorkflowService();
$request = new Request();

$december = null;
foreach ($periods->all() as $p) {
    if ($p->year === 2026 && $p->month === 12) {
        $december = $p;
        break;
    }
}
if (!$december) {
    fwrite(STDERR, "Période décembre 2026 introuvable — lancez d'abord database/seed.sql.\n");
    exit(1);
}

// NOVA Morocco (NOVA-MA) volontairement exclue : "5/6 soumis, Maroc en attente".
$submittingCodes = ['NOVA-SN', 'NOVA-CI', 'NOVA-ML', 'NOVA-FR', 'NOVA-GH'];
$preparerEmails = [
    'NOVA-SN' => 'preparer.sn@novaafrica.com',
    'NOVA-CI' => 'preparer.ci@novaafrica.com',
    'NOVA-ML' => 'preparer.ml@novaafrica.com',
    'NOVA-FR' => 'preparer.fr@novaafrica.com',
    'NOVA-GH' => 'preparer.gh@novaafrica.com',
];

foreach ($submittingCodes as $code) {
    $subsidiary = $subsidiaries->findByCode($code);
    $preparer = $users->findByEmail($preparerEmails[$code]);

    [$ok, $error] = $workflow->submit($subsidiary, $december, $preparer, $request);
    echo ($ok ? 'OK  ' : 'ERR ') . "{$code} : " . ($ok ? 'soumis' : $error) . "\n";
}

echo "Terminé. NOVA-MA (Maroc) reste en brouillon, comme prévu par le scénario.\n";
