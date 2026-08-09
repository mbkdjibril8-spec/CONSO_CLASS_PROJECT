<?php

namespace App\Services;

use App\Models\Account;

/**
 * Règles de validation de la collecte financière (voir
 * docs/CONSOLIDATION_LOGIC.md pour le détail des formules). Ne connaît
 * rien du HTTP ni de la persistance : reçoit des montants déjà découpés
 * par compte, retourne des erreurs (bloquantes) et des avertissements
 * (non bloquants).
 */
class ValidationService
{
    private const REVENUE_ACCOUNTS = ['REV', 'IC_REVENUE'];
    private const BS_ASSET_ACCOUNTS = ['FIXED_ASSETS', 'RECEIVABLES', 'IC_RECEIVABLE', 'CASH'];
    private const BS_LIAB_EQUITY_ACCOUNTS = ['PAYABLES', 'IC_PAYABLE', 'FINANCIAL_DEBT', 'SHARE_CAPITAL', 'RETAINED_EARNINGS'];
    private const ANOMALY_THRESHOLD = 0.50;
    // Tolérance d'arrondi : les montants sont stockés en DECIMAL(18,2) ;
    // l'arrondi indépendant de chacun des 9 comptes du bilan peut produire
    // un écart résiduel de quelques centimes sans traduire une vraie
    // erreur de saisie. 1 unité de devise absorbe ce bruit sans masquer
    // un déséquilibre réel (toujours de plusieurs ordres de grandeur au-dessus).
    private const BALANCE_TOLERANCE = 1.00;

    /**
     * @param Account[] $accounts tous les comptes actifs (indexés par code)
     * @param array<string, string> $rawAmounts saisie brute indexée par code compte
     * @param array<string, float> $previousAmounts montants du mois précédent, indexés par code (vide si non disponible)
     * @return array{errors: array<string,string>, warnings: string[], parsed: array<string,float>, netIncome: float}
     */
    public function validate(array $accounts, array $rawAmounts, array $previousAmounts = []): array
    {
        $errors = [];
        $parsed = [];

        // 1. Champs obligatoires + type + signe.
        foreach ($accounts as $account) {
            $raw = $rawAmounts[$account->code] ?? '';
            $raw = is_string($raw) ? trim($raw) : $raw;

            if ($raw === '' || $raw === null) {
                $errors[$account->code] = 'Champ obligatoire.';
                continue;
            }
            if (!is_numeric($raw)) {
                $errors[$account->code] = 'Doit être un nombre.';
                continue;
            }

            $value = (float) $raw;
            if ($value < 0 && !$account->allowsNegativeAmount()) {
                $errors[$account->code] = 'Doit être positif ou nul.';
                continue;
            }

            $parsed[$account->code] = $value;
        }

        if (!empty($errors)) {
            return ['errors' => $errors, 'warnings' => [], 'parsed' => $parsed, 'netIncome' => 0.0];
        }

        // 2. Équation bilancielle (bloquant) — résultat net calculé depuis le compte de résultat saisi.
        $netIncome = $this->computeNetIncome($parsed);
        $assets = $this->sum($parsed, self::BS_ASSET_ACCOUNTS);
        $liabEquity = $this->sum($parsed, self::BS_LIAB_EQUITY_ACCOUNTS) + $netIncome;
        $diff = round($assets - $liabEquity, 2);

        if (abs($diff) >= self::BALANCE_TOLERANCE) {
            $sens = $diff > 0 ? 'l\'actif excède le passif + capitaux propres' : 'le passif + capitaux propres excède l\'actif';
            $errors['_balance'] = sprintf(
                'Le bilan n\'est pas équilibré : %s de %s. Actif = %s, Passif + Capitaux propres + Résultat net = %s.',
                $sens,
                format_amount(abs($diff)),
                format_amount($assets),
                format_amount($liabEquity)
            );
            return ['errors' => $errors, 'warnings' => [], 'parsed' => $parsed, 'netIncome' => $netIncome];
        }

        // 3. Anomalie non bloquante : variation de revenu > 50 % vs mois précédent.
        $warnings = [];
        if (!empty($previousAmounts)) {
            $currentRevenue = $this->sum($parsed, self::REVENUE_ACCOUNTS);
            $previousRevenue = $this->sum($previousAmounts, self::REVENUE_ACCOUNTS);

            if ($previousRevenue > 0) {
                $variation = ($currentRevenue - $previousRevenue) / $previousRevenue;
                if (abs($variation) > self::ANOMALY_THRESHOLD) {
                    $warnings[] = sprintf(
                        'Variation du chiffre d\'affaires de %s%% par rapport au mois précédent (%s → %s). Vérifiez la saisie avant soumission.',
                        number_format($variation * 100, 1, ',', ' '),
                        format_amount($previousRevenue),
                        format_amount($currentRevenue)
                    );
                }
            }
        }

        return ['errors' => [], 'warnings' => $warnings, 'parsed' => $parsed, 'netIncome' => $netIncome];
    }

    /** @param array<string,float> $amounts */
    public function computeNetIncome(array $amounts): float
    {
        $ebitda = $this->computeEbitda($amounts);
        $ebit = $ebitda - ($amounts['DA'] ?? 0);
        $preTax = $ebit + ($amounts['FIN_INCOME'] ?? 0) - ($amounts['FIN_EXPENSE'] ?? 0);
        return $preTax - ($amounts['TAX'] ?? 0);
    }

    /** @param array<string,float> $amounts */
    public function computeEbitda(array $amounts): float
    {
        $revenue = ($amounts['REV'] ?? 0) + ($amounts['IC_REVENUE'] ?? 0);
        return $revenue - ($amounts['COGS'] ?? 0) - ($amounts['OPEX_PERS'] ?? 0)
             - ($amounts['OPEX_OTHER'] ?? 0) - ($amounts['IC_EXPENSE'] ?? 0);
    }

    /** @param array<string,float> $amounts */
    public function computeRevenue(array $amounts): float
    {
        return ($amounts['REV'] ?? 0) + ($amounts['IC_REVENUE'] ?? 0);
    }

    /** @param array<string,float> $amounts @param string[] $codes */
    private function sum(array $amounts, array $codes): float
    {
        $total = 0.0;
        foreach ($codes as $code) {
            $total += $amounts[$code] ?? 0.0;
        }
        return $total;
    }
}
