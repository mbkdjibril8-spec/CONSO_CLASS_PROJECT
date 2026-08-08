<?php

namespace App\Services;

use App\Core\Request;
use App\Models\Account;
use App\Models\User;
use App\Repositories\FinancialDataRepository;

/**
 * Import CSV d'un paquet filiale/période (voir docs/CONSOLIDATION_LOGIC.md
 * pour le format attendu : 2 colonnes account_code,amount). Les lignes
 * valides sont enregistrées immédiatement ; les lignes invalides sont
 * rapportées avec leur numéro de ligne sans bloquer les autres.
 */
class CsvImportService
{
    private const MAX_SIZE_BYTES = 1_048_576; // 1 Mo

    private FinancialDataRepository $financialData;
    private AuditService $audit;

    public function __construct()
    {
        $this->financialData = new FinancialDataRepository();
        $this->audit = new AuditService();
    }

    /**
     * @param array $file entrée $_FILES['csv']
     * @param array<string, Account> $accountsByCode
     * @return array{imported: int, errors: array<int, array{line:int, message:string}>}
     */
    public function import(array $file, int $subsidiaryId, int $periodId, array $accountsByCode, User $actor, Request $request): array
    {
        $errors = [];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return ['imported' => 0, 'errors' => [['line' => 0, 'message' => 'Échec du téléversement du fichier.']]];
        }
        if ($file['size'] > self::MAX_SIZE_BYTES) {
            return ['imported' => 0, 'errors' => [['line' => 0, 'message' => 'Fichier trop volumineux (1 Mo maximum).']]];
        }
        if (strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            return ['imported' => 0, 'errors' => [['line' => 0, 'message' => 'Seuls les fichiers .csv sont acceptés.']]];
        }

        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            return ['imported' => 0, 'errors' => [['line' => 0, 'message' => 'Impossible de lire le fichier.']]];
        }

        $imported = 0;
        $lineNumber = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $lineNumber++;

            // Ligne d'en-tête attendue : account_code,amount — ignorée silencieusement.
            if ($lineNumber === 1 && isset($row[0]) && strtolower(trim($row[0])) === 'account_code') {
                continue;
            }
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // ligne vide
            }

            if (count($row) !== 2) {
                $errors[] = ['line' => $lineNumber, 'message' => 'Format invalide : 2 colonnes attendues (account_code,amount).'];
                continue;
            }

            [$code, $rawAmount] = $row;
            $code = strtoupper(trim($code));
            $rawAmount = trim($rawAmount);

            if (!isset($accountsByCode[$code])) {
                $errors[] = ['line' => $lineNumber, 'message' => "Compte inconnu : « {$code} »."];
                continue;
            }
            if ($rawAmount === '' || !is_numeric($rawAmount)) {
                $errors[] = ['line' => $lineNumber, 'message' => "Montant non numérique pour « {$code} »."];
                continue;
            }

            $account = $accountsByCode[$code];
            $amount = (float) $rawAmount;
            if ($amount < 0 && !$account->allowsNegativeAmount()) {
                $errors[] = ['line' => $lineNumber, 'message' => "Montant négatif non autorisé pour « {$code} »."];
                continue;
            }

            $this->financialData->upsert($subsidiaryId, $periodId, $account->id, $amount, $actor->id);
            $imported++;
        }

        fclose($handle);

        $this->audit->logChange(
            $actor,
            'csv_import',
            'financial_data',
            $subsidiaryId,
            null,
            ['period_id' => $periodId, 'imported' => $imported, 'errors' => count($errors)],
            $request
        );

        return ['imported' => $imported, 'errors' => $errors];
    }
}
