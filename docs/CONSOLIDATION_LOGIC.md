# CONSOLIDATION_LOGIC — GROUPFIN

Ce document consigne les choix de traitement comptable retenus lorsque
plusieurs approches étaient défendables (règle §8.5 du cahier des charges).
Il est complété phase après phase (Phase 3 : plan de comptes et collecte ;
Phase 5 : moteur de consolidation).

## Plan de comptes (Phase 3)

Conformément à la règle de simplification §8.1 ("réduire le nombre de
comptes, jamais la logique de calcul"), le plan de comptes est volontairement
réduit à 22 comptes couvrant les trois états financiers.

### Convention de signe
**Tous les montants sont saisis en valeur positive**, telle qu'ils
apparaîtraient sur un état financier imprimé (une charge se saisit en
positif, pas en négatif). Les formules de calcul (marge, EBITDA, résultat
net...) additionnent ou soustraient explicitement chaque compte selon sa
nature économique — la colonne `normal_balance` du schéma est informative
(affichage), elle n'inverse aucun signe dans les calculs.
Exception : les 3 comptes de flux de trésorerie (`CF_*`) représentent un
flux **net** et peuvent être négatifs (sortie nette de trésorerie).

### Compte de résultat (IS)
| Code | Libellé | Nature |
|---|---|---|
| REV | Chiffre d'affaires | Produit |
| IC_REVENUE | Produits intercos | Produit (interco) |
| COGS | Coût des ventes | Charge |
| OPEX_PERS | Charges de personnel | Charge |
| OPEX_OTHER | Autres charges d'exploitation | Charge |
| IC_EXPENSE | Charges intercos | Charge (interco) |
| DA | Dotations aux amortissements | Charge |
| FIN_INCOME | Produits financiers | Produit |
| FIN_EXPENSE | Charges financières | Charge |
| TAX | Impôt sur les sociétés | Charge |

Formules dérivées (non stockées, calculées à l'affichage) :
- **Marge brute** = REV + IC_REVENUE − COGS
- **EBITDA** = Marge brute − OPEX_PERS − OPEX_OTHER − IC_EXPENSE
- **Résultat d'exploitation (EBIT)** = EBITDA − DA
- **Résultat net** = EBIT + FIN_INCOME − FIN_EXPENSE − TAX

### Bilan (BS)
| Code | Libellé | Nature |
|---|---|---|
| FIXED_ASSETS | Immobilisations nettes | Actif |
| RECEIVABLES | Créances clients | Actif |
| IC_RECEIVABLE | Créances intercos | Actif (interco) |
| CASH | Trésorerie et équivalents | Actif |
| PAYABLES | Dettes fournisseurs | Passif |
| IC_PAYABLE | Dettes intercos | Passif (interco) |
| FINANCIAL_DEBT | Dettes financières | Passif |
| SHARE_CAPITAL | Capital social | Capitaux propres |
| RETAINED_EARNINGS | Réserves et report à nouveau | Capitaux propres |

**Le résultat net de l'exercice n'est PAS un compte saisi séparément** :
il est calculé à partir du compte de résultat de la même période et
intégré automatiquement aux capitaux propres pour le contrôle de
l'équation bilancielle. Cela évite une double saisie et un risque
d'incohérence entre le compte de résultat et le bilan soumis.

**Équation bilancielle (contrôle bloquant, ValidationService) :**
```
FIXED_ASSETS + RECEIVABLES + IC_RECEIVABLE + CASH
    =
PAYABLES + IC_PAYABLE + FINANCIAL_DEBT + SHARE_CAPITAL + RETAINED_EARNINGS + Résultat_net(période)
```

Les intérêts minoritaires n'apparaissent qu'au niveau **consolidé** (table
`minority_interests`, calculés lors du run de consolidation) : ils ne font
pas partie du plan de comptes filiale, une filiale ne "connaît" pas sa
propre quote-part minoritaire dans ses comptes sociaux.

### Flux de trésorerie (CF)
| Code | Libellé |
|---|---|
| CF_OPERATING | Flux de trésorerie d'exploitation |
| CF_INVESTING | Flux de trésorerie d'investissement |
| CF_FINANCING | Flux de trésorerie de financement |

Le CF est collecté à titre informatif (item V1 §2.4) mais **n'est pas
retraité par le moteur de consolidation** (§2.8 ne mentionne que le compte
de résultat et le bilan consolidés en sortie). Un CF consolidé complet
(méthode indirecte avec réconciliation) est hors périmètre V1 — voir V2
backlog dans PROJECT_STATE.md. En conséquence, aucun contrôle de
cohérence CF ↔ variation de trésorerie du bilan n'est implémenté en V1
(éviterait d'ajouter une règle de validation non demandée par le cahier
des charges).

### Comptes intercos (`is_intercompany = 1`)
`IC_REVENUE`, `IC_EXPENSE`, `IC_RECEIVABLE`, `IC_PAYABLE` sont séparés des
comptes externes homologues afin que le moteur de consolidation (Phase 5)
puisse éliminer précisément les soldes/flux intra-groupe sans toucher à
l'activité avec les tiers.

## Règles de validation (Phase 3, `ValidationService`)
1. **Équation bilancielle** (bloquant) — voir formule ci-dessus.
2. **Champs obligatoires** — chaque compte actif du plan de comptes doit
   avoir une valeur numérique saisie (0 accepté, vide refusé).
3. **Type** — montant numérique ; négatif refusé pour tous les comptes
   sauf les 3 comptes `CF_*` (flux net, signe libre).
4. **Anomalie non bloquante** — variation de REV + IC_REVENUE > 50 % par
   rapport au mois précédent (même filiale) : avertissement affiché,
   la saisie/soumission reste autorisée.
5. **Anti-doublon de soumission** — traité au niveau du workflow
   (Phase 4), pas au niveau de la saisie (upsert idempotent par
   filiale/période/compte).

## Workflow (Phase 4)
Le statut d'un paquet filiale/période (`draft`, `submitted`, `rejected`,
`validated`) n'est **pas stocké** dans une colonne dédiée : il est déduit
de la dernière ligne de `workflow_transitions` pour ce couple
filiale/période (`draft` si aucune ligne n'existe encore). Source unique
de vérité, cohérent avec l'exigence d'audit "chaque transition
enregistrée". Séquence autorisée :
```
draft --(Soumettre, Préparateur)--> submitted
submitted --(Valider, Contrôleur)--> validated
submitted --(Rejeter + motif, Contrôleur)--> rejected
rejected --(Soumettre à nouveau, Préparateur)--> submitted
```
La saisie (formulaire + import CSV) n'est modifiable que si le statut est
`draft` ou `rejected` **et** que la période n'est pas clôturée — vérifié
côté serveur (`WorkflowService::isEditable`), pas seulement masqué côté UI.
La soumission ré-exécute `ValidationService` sur les données déjà
enregistrées : impossible de soumettre un paquet incomplet ou déséquilibré.

## Intercompany (Phase 4)
Chaque filiale déclare **son propre côté** d'une opération intercompany
(`intercompany_transactions`, un enregistrement par déclarant). Le
rapprochement automatique cherche la déclaration miroir de la
contrepartie pour la même période :

| Type déclaré | Type miroir attendu |
|---|---|
| receivable (créance) | payable (dette) |
| payable (dette) | receivable (créance) |
| revenue (produit) | expense (charge) |
| expense (charge) | revenue (produit) |
| dividend | — (voir ci-dessous) |

Conversion en XOF : **taux de clôture** pour receivable/payable (soldes de
bilan), **taux moyen** pour revenue/expense/dividend (flux de période).
Si les deux montants convertis diffèrent de moins de 0,01 XOF → `matched`,
sinon → `mismatch` avec l'écart exact stocké sur les deux lignes
(`difference_amount`) et notification aux contrôleurs des deux filiales
+ au responsable consolidation.

**Simplification dividendes** : NOVA Holding ne soumettant pas de paquet
financier propre (décision Phase 1), il n'existe pas de "contrepartie"
qui déclarerait la réception d'un dividende intra-groupe. Une déclaration
`dividend` est donc **à sens unique** (déclarée par la filiale payante) et
marquée `matched` dès la saisie, sans recherche de contrepartie. Elle sert
uniquement de donnée source pour l'élimination des dividendes en Phase 5.

## Import CSV
Format attendu : 2 colonnes `account_code,amount`, un fichier par couple
filiale/période couvrant tous les comptes (IS+BS+CF mélangés, le compte
détermine son état financier). Extension `.csv` obligatoire, taille
maximale 1 Mo. Les lignes valides sont enregistrées immédiatement ; les
lignes en erreur (code compte inconnu, montant non numérique, négatif non
autorisé) sont rapportées avec leur numéro de ligne sans bloquer
l'enregistrement des lignes valides — cohérent avec un usage tableur où
l'utilisateur corrige et réimporte uniquement les lignes en erreur.
