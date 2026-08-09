<?php

namespace App\Services;

/**
 * Calcul des écarts Budget vs Actual. Convention de signe : un écart
 * "favorable" est une hausse pour un agrégat de type produit/résultat
 * (revenu, EBITDA, résultat net) et une baisse pour une charge — déterminé
 * ici via un paramètre explicite plutôt que déduit de normal_balance, pour
 * rester correct sur les indicateurs composites (EBITDA, résultat net) qui
 * ne correspondent à aucun compte unique.
 */
class BudgetVarianceService
{
    /**
     * @return array{actual: float, budget: float, variance: float, variancePct: float|null, favorable: bool}
     */
    public function compute(float $actual, float $budget, bool $higherIsFavorable = true): array
    {
        $variance = $actual - $budget;
        $variancePct = $budget != 0.0 ? round($variance / abs($budget) * 100, 1) : null;
        $favorable = $higherIsFavorable ? $variance >= 0 : $variance <= 0;

        return [
            'actual' => $actual,
            'budget' => $budget,
            'variance' => $variance,
            'variancePct' => $variancePct,
            'favorable' => $favorable,
        ];
    }
}
