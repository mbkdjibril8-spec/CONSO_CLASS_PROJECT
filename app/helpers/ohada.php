<?php

/**
 * Présentation des états financiers au format normalisé OHADA/SYCEBNL
 * (codes REF, soldes intermédiaires de gestion). Couche de PRÉSENTATION
 * uniquement : le plan de comptes GROUPFIN (22 comptes, Phase 3) reste le
 * moteur de saisie/validation/consolidation. Chaque ligne OHADA sans
 * correspondance dans le plan de comptes simplifié affiche 0,00 — comme
 * sur un état réel d'une société dont l'activité ne mouvemente pas cette
 * ligne. Mapping documenté dans docs/CONSOLIDATION_LOGIC.md §États OHADA.
 */

/**
 * Calcule les valeurs des lignes OHADA du compte de résultat à partir des
 * montants du plan de comptes GROUPFIN (indexés par code : REV, COGS...).
 * @param array<string, float> $a
 * @return array<string, float>
 */
function ohada_income_statement_values(array $a): array
{
    $g = fn (string $k) => $a[$k] ?? 0.0;

    $xb = $g('REV') + $g('IC_REVENUE');                          // Chiffre d'affaires
    $rh = $g('OPEX_OTHER');                                      // Services extérieurs
    $rj = $g('IC_EXPENSE');                                      // Autres charges (dont intercos)
    $rc = $g('COGS');                                             // Achats matières premières / marchandises
    $xc = $xb - $rc - $rh - $rj;                                  // Valeur ajoutée
    $rk = $g('OPEX_PERS');                                        // Charges de personnel
    $xd = $xc - $rk;                                              // EBE
    $rl = $g('DA');                                               // Dotations amortissements
    $xe = $xd - $rl;                                              // Résultat d'exploitation
    $tk = $g('FIN_INCOME');
    $rm = $g('FIN_EXPENSE');
    $xf = $tk - $rm;                                              // Résultat financier
    $xg = $xe + $xf;                                              // Résultat activités ordinaires
    $xh = 0.0;                                                    // Résultat HAO (non tracké V1)
    $rs = $g('TAX');
    $xi = $xg + $xh - $rs;                                        // Résultat net

    return [
        'TA' => 0, 'RA' => 0, 'RB' => 0, 'XA' => 0,
        'TB' => $xb, 'TC' => 0, 'TD' => 0, 'XB' => $xb,
        'TE' => 0, 'TF' => 0, 'TG' => 0, 'TH' => 0, 'TI' => 0,
        'RC' => $rc, 'RD' => 0, 'RE' => 0, 'RF' => 0, 'RG' => 0, 'RH' => $rh, 'RI' => 0, 'RJ' => $rj,
        'XC' => $xc,
        'RK' => $rk, 'XD' => $xd,
        'TJ' => 0, 'RL' => $rl, 'XE' => $xe,
        'TK' => $tk, 'TL' => 0, 'TM' => 0, 'RM' => $rm, 'RN' => 0, 'XF' => $xf,
        'XG' => $xg,
        'TN' => 0, 'TO' => 0, 'RO' => 0, 'RP' => 0, 'XH' => $xh,
        'RQ' => 0, 'RS' => $rs, 'XI' => $xi,
    ];
}

/**
 * Calcule les valeurs des lignes OHADA du bilan (actif + passif) à partir
 * des montants du plan de comptes GROUPFIN et du résultat net (calculé,
 * jamais stocké — cf. Phase 3).
 * @param array<string, float> $a
 * @return array<string, float>
 */
function ohada_balance_sheet_values(array $a, float $netIncome): array
{
    $g = fn (string $k) => $a[$k] ?? 0.0;

    $ai = $g('FIXED_ASSETS');
    $ar = $g('EQ_METHOD_INVESTMENT'); // Titres de participation (mise en équivalence — présent uniquement sur une vue consolidée)
    $aq = $ar;
    $bi = $g('RECEIVABLES');
    $bj = $g('IC_RECEIVABLE');
    $bk = $bi + $bj;
    $bs = $g('CASH');
    $bt = $bs;

    $ca = $g('SHARE_CAPITAL');
    $ch = $g('RETAINED_EARNINGS');
    $cj = $netIncome;
    $cp = $ca + $ch + $cj;
    $da = $g('FINANCIAL_DEBT');
    $dd = $da;
    $dj = $g('PAYABLES');
    $dm = $g('IC_PAYABLE');
    $dp = $dj + $dm;
    $dt = 0.0;

    // Écart de conversion : sur une vue multi-devises (consolidée), le résultat
    // net (traduit au taux moyen) et les capitaux propres du bilan (traduits au
    // taux de clôture) ne s'emboîtent pas exactement (cf. docs/CONSOLIDATION_LOGIC.md
    // §Écart de conversion). Le plug va dans la ligne dédiée du modèle OHADA
    // (BU côté actif ou DV côté passif) — jamais dans CJ, pour que le résultat net
    // reste identique entre le compte de résultat (XI) et le bilan (CJ), comme sur
    // un état réel. Les DEUX appelants (actif et passif) doivent recevoir le même
    // $netIncome pour que BU/DV/BZ/DZ restent cohérents entre les deux tableaux.
    $rawActif = $ai + $aq + $bk + $bt;
    $rawPassifBase = $cp + $dd + $dp + $dt;
    $cta = round($rawActif - $rawPassifBase, 2);
    $bu = $cta < 0 ? -$cta : 0.0;
    $dv = $cta > 0 ? $cta : 0.0;
    $az = $ai + $aq;
    $bz = $rawActif + $bu;
    $df = $cp + $dd;
    $dz = $rawPassifBase + $dv;

    return [
        // Actif
        'AD' => 0, 'AE' => 0, 'AF' => 0, 'AG' => 0, 'AH' => 0,
        'AI' => $ai, 'AJ' => 0, 'AK' => 0, 'AL' => 0, 'AM' => 0, 'AN' => 0, 'AP' => 0,
        'AQ' => $aq, 'AR' => $ar, 'AS' => 0,
        'AZ' => $az,
        'BA' => 0, 'BB' => 0,
        'BG' => 0, 'BH' => 0, 'BI' => $bi, 'BJ' => $bj, 'BK' => $bk,
        'BQ' => 0, 'BR' => 0, 'BS' => $bs, 'BT' => $bt,
        'BU' => $bu,
        'BZ' => $bz,
        // Passif
        'CA' => $ca, 'CB' => 0, 'CD' => 0, 'CE' => 0, 'CF' => 0, 'CG' => 0,
        'CH' => $ch, 'CJ' => $cj, 'CL' => 0, 'CM' => 0,
        'CP' => $cp,
        'DA' => $da, 'DB' => 0, 'DC' => 0, 'DD' => $dd,
        'DF' => $df,
        'DH' => 0, 'DI' => 0, 'DJ' => $dj, 'DK' => 0, 'DM' => $dm, 'DN' => 0,
        'DP' => $dp,
        'DQ' => 0, 'DR' => 0, 'DT' => $dt,
        'DV' => $dv,
        'DZ' => $dz,
    ];
}

/** @param array<int, array{0:string,1:string,2:string}> $rows [ref, label, 'normal'|'subtotal'|'total'] */
function ohada_render_table(array $rows, array $values): string
{
    $html = '<table class="ohada-table"><tbody>';
    foreach ($rows as [$ref, $label, $kind]) {
        $value = $values[$ref] ?? 0.0;
        $rowClass = $kind === 'total' ? 'ohada-total' : ($kind === 'subtotal' ? 'ohada-subtotal' : '');
        $html .= '<tr class="' . $rowClass . '">'
            . '<td class="ohada-ref">' . h($ref) . '</td>'
            . '<td class="ohada-label">' . h($label) . '</td>'
            . '<td class="num ohada-value">' . format_amount($value) . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

function render_ohada_income_statement(array $amounts): string
{
    $values = ohada_income_statement_values($amounts);
    $rows = [
        ['TA', 'Ventes de marchandises', 'normal'],
        ['RA', 'Achats de marchandises', 'normal'],
        ['RB', 'Variation de stocks de marchandises', 'normal'],
        ['XA', 'MARGE COMMERCIALE', 'subtotal'],
        ['TB', 'Ventes de produits fabriqués, travaux, services vendus', 'normal'],
        ['TC', 'Travaux, services vendus', 'normal'],
        ['TD', 'Produits accessoires', 'normal'],
        ['XB', "CHIFFRE D'AFFAIRES", 'subtotal'],
        ['TE', 'Production stockée (ou déstockage)', 'normal'],
        ['TF', 'Production immobilisée', 'normal'],
        ['TG', "Subventions d'exploitation", 'normal'],
        ['TH', 'Autres produits', 'normal'],
        ['TI', "Transferts de charges d'exploitation", 'normal'],
        ['RC', 'Achats de matières premières et fournitures liées', 'normal'],
        ['RD', 'Variation de stocks de matières premières', 'normal'],
        ['RE', 'Autres achats', 'normal'],
        ['RF', "Variation de stocks d'autres approvisionnements", 'normal'],
        ['RG', 'Transports', 'normal'],
        ['RH', 'Services extérieurs', 'normal'],
        ['RI', 'Impôts et taxes', 'normal'],
        ['RJ', 'Autres charges', 'normal'],
        ['XC', 'VALEUR AJOUTEE', 'subtotal'],
        ['RK', 'Charges de personnel', 'normal'],
        ['XD', "EXCEDENT BRUT D'EXPLOITATION", 'subtotal'],
        ['TJ', "Reprises d'amortissements, provisions et dépréciations", 'normal'],
        ['RL', 'Dotations aux amortissements, provisions et dépréciations', 'normal'],
        ['XE', "RESULTAT D'EXPLOITATION", 'subtotal'],
        ['TK', 'Revenus financiers et assimilés', 'normal'],
        ['TL', 'Reprises de provisions et dépréciations financières', 'normal'],
        ['TM', 'Transferts de charges financières', 'normal'],
        ['RM', 'Frais financiers et charges assimilées', 'normal'],
        ['RN', 'Dotations aux provisions et dépréciations financières', 'normal'],
        ['XF', 'RESULTAT FINANCIER', 'subtotal'],
        ['XG', "RESULTAT DES ACTIVITES ORDINAIRES", 'subtotal'],
        ['TN', "Produits des cessions d'immobilisations", 'normal'],
        ['TO', 'Autres produits HAO', 'normal'],
        ['RO', "Valeurs comptables des cessions d'immobilisations", 'normal'],
        ['RP', 'Autres charges HAO', 'normal'],
        ['XH', "RESULTAT HORS ACTIVITES ORDINAIRES", 'subtotal'],
        ['RQ', 'Participation des travailleurs', 'normal'],
        ['RS', 'Impôts sur le résultat', 'normal'],
        ['XI', 'RESULTAT NET', 'total'],
    ];
    return ohada_render_table($rows, $values);
}

/**
 * $netIncome DOIT être le même que celui passé à render_ohada_balance_sheet_passif()
 * pour ce même bilan : les deux tableaux dérivent BU/DV/BZ/DZ de la même base
 * (écart de conversion), sans quoi les deux moitiés du bilan divergeraient.
 */
function render_ohada_balance_sheet_actif(array $amounts, float $netIncome): string
{
    $values = ohada_balance_sheet_values($amounts, $netIncome);
    $rows = [
        ['AD', 'IMMOBILISATIONS INCORPORELLES', 'subtotal'],
        ['AE', 'Frais de développement et de prospection', 'normal'],
        ['AF', 'Brevets, licences, logiciels, droits similaires', 'normal'],
        ['AG', 'Fonds commercial et droit au bail', 'normal'],
        ['AH', 'Autres immobilisations incorporelles', 'normal'],
        ['AI', 'IMMOBILISATIONS CORPORELLES', 'subtotal'],
        ['AJ', 'Terrains', 'normal'],
        ['AK', 'Bâtiments', 'normal'],
        ['AL', 'Aménagements, agencements et installations', 'normal'],
        ['AM', 'Matériel, mobilier et actifs biologiques', 'normal'],
        ['AN', 'Matériel de transport', 'normal'],
        ['AP', 'Avances et acomptes versés sur immobilisations', 'normal'],
        ['AQ', 'IMMOBILISATIONS FINANCIERES', 'subtotal'],
        ['AR', 'Titres de participation', 'normal'],
        ['AS', 'Autres immobilisations financières', 'normal'],
        ['AZ', 'TOTAL ACTIF IMMOBILISE', 'total'],
        ['BA', 'Actif circulant HAO', 'normal'],
        ['BB', 'Stocks et encours', 'normal'],
        ['BG', 'Créances et emplois assimilés', 'subtotal'],
        ['BH', 'Fournisseurs avances versées', 'normal'],
        ['BI', 'Clients', 'normal'],
        ['BJ', 'Autres créances', 'normal'],
        ['BK', 'TOTAL ACTIF CIRCULANT', 'total'],
        ['BQ', 'Titres de placement', 'normal'],
        ['BR', 'Valeurs à encaisser', 'normal'],
        ['BS', 'Banques, chèques postaux, caisse et assimilés', 'normal'],
        ['BT', 'TOTAL TRESORERIE-ACTIF', 'total'],
        ['BU', 'Écart de conversion-Actif', 'normal'],
        ['BZ', 'TOTAL GENERAL', 'total'],
    ];
    return ohada_render_table($rows, $values);
}

function render_ohada_balance_sheet_passif(array $amounts, float $netIncome): string
{
    $values = ohada_balance_sheet_values($amounts, $netIncome);
    $rows = [
        ['CA', 'Capital', 'normal'],
        ['CB', 'Apporteurs capital non appelé (-)', 'normal'],
        ['CD', 'Primes liées au capital social', 'normal'],
        ['CE', 'Écarts de réévaluation', 'normal'],
        ['CF', 'Réserves indisponibles', 'normal'],
        ['CG', 'Réserves libres', 'normal'],
        ['CH', 'Report à nouveau (+ ou -)', 'normal'],
        ['CJ', "Résultat net de l'exercice (bénéfice + ou perte -)", 'normal'],
        ['CL', "Subventions d'investissement", 'normal'],
        ['CM', 'Provisions réglementées', 'normal'],
        ['CP', 'TOTAL CAPITAUX PROPRES ET RESSOURCES ASSIMILEES', 'total'],
        ['DA', 'Emprunts et dettes financières diverses', 'normal'],
        ['DB', 'Dettes de location acquisition', 'normal'],
        ['DC', 'Provisions pour risques et charges', 'normal'],
        ['DD', 'TOTAL DETTES FINANCIERES ET RESSOURCES ASSIMILEES', 'total'],
        ['DF', 'TOTAL RESSOURCES STABLES', 'total'],
        ['DH', 'Dettes circulantes HAO', 'normal'],
        ['DI', 'Clients, avances reçues', 'normal'],
        ['DJ', "Fournisseurs d'exploitation", 'normal'],
        ['DK', 'Dettes fiscales et sociales', 'normal'],
        ['DM', 'Autres dettes', 'normal'],
        ['DN', 'Provisions pour risques à court terme', 'normal'],
        ['DP', 'TOTAL PASSIF CIRCULANT', 'total'],
        ['DQ', "Banques, crédits d'escompte", 'normal'],
        ['DR', 'Banques, établissements financiers et crédits de trésorerie', 'normal'],
        ['DT', 'TOTAL TRESORERIE-PASSIF', 'total'],
        ['DV', 'Écart de conversion-Passif', 'normal'],
        ['DZ', 'TOTAL GENERAL', 'total'],
    ];
    return ohada_render_table($rows, $values);
}
