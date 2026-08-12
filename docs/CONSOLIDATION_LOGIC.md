# CONSOLIDATION_LOGIC — OHADA_CONSO+

Ce document consigne les choix de traitement comptable retenus lorsque
plusieurs approches étaient défendables (règle §8.5 du cahier des charges).
Il est complété phase après phase (Phase 3 : plan de comptes et collecte ;
Phase 4 : workflow et intercompany ; Phase 5 : moteur de consolidation).

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

## Moteur de consolidation (Phase 5)

### Pipeline
1. **Périmètre** — filiales actives en méthode `full` ou `equity` ; le run
   échoue si l'une d'elles n'a pas de paquet `validated` pour la période
   (source : dernière transition `workflow_transitions`, cf. Phase 4).
   `excluded` (NOVA Holding) n'entre jamais dans le run.
2. **Conversion + agrégation** — chaque compte IS/BS des filiales en
   intégration globale est converti en XOF (moyen pour l'IS, clôture pour
   le bilan) puis sommé compte par compte. Le CF n'est pas consolidé
   (décision Phase 3).
3. **Éliminations intercompany** — seules les paires au statut `matched`
   (Phase 4) sont éliminées automatiquement, des deux côtés (le compte de
   la filiale déclarante ET celui de la contrepartie). Une paire `mismatch`
   n'est **jamais** éliminée automatiquement : elle reste telle quelle dans
   l'agrégat et est signalée dans le journal du run ("écart non résolu") —
   à corriger via le module Intercompany ou un ajustement de consolidation
   manuel avant une prochaine exécution.
4. **Élimination des dividendes** — un dividende n'est éliminé que si la
   filiale émettrice ET la filiale destinataire sont toutes deux dans le
   périmètre d'intégration globale. Dans le jeu de données NOVA AFRICA
   GROUP, tous les dividendes sont déclarés vers NOVA Holding (hors
   périmètre, cf. Phase 1) : cette étape s'exécute donc réellement mais
   ne trouve rien à éliminer — comportement honnête, pas une fonctionnalité
   fictive (règle §8.2).
5. **Mise en équivalence** — pour chaque filiale `equity` (NOVA Ghana),
   quote-part de résultat = `% détention × résultat net` (ajoutée à un
   compte dédié `EQ_METHOD_INCOME`) ; titres mis en équivalence = `%
   détention × capitaux propres` (compte dédié `EQ_METHOD_INVESTMENT`).
   Traitement en "photo" (pas de suivi multi-période des mouvements de
   réserves de mise en équivalence — hors périmètre V1, voir backlog).
6. **Ajustements de consolidation** — les écritures `posted` de la période
   sont appliquées à l'agrégat selon `normal_balance` du compte et le sens
   (débit/crédit) de l'écriture.
7. **Intérêts minoritaires** — calculés par filiale en intégration
   globale détenue à moins de 100 % (voir formule ci-dessous).

### Écart de conversion (traduction devises) et équilibre du bilan
Le cahier des charges impose un taux **moyen** pour l'IS et un taux de
**clôture** pour le bilan (§5). Appliqué à une filiale en devise étrangère,
cela signifie que le résultat net (traduit au taux moyen) et les capitaux
propres du bilan (traduits au taux de clôture) ne "s'emboîtent" plus
exactement — un écart de conversion apparaît nécessairement, comme dans
tout référentiel de consolidation multi-devises (IFRS : réserve OCI).

**Choix retenu (le plus simple et le plus robuste) :** plutôt que de
calculer et d'afficher un compte "écart de conversion" séparé, les
capitaux propres de chaque filiale sont **dérivés directement de
Actif − Passif traduits** (et non de Capital + Réserves + Résultat net).
Cette quantité absorbe automatiquement l'écart de conversion et garantit
que `Actif = Passif + Capitaux propres` reste vérifié **au centime près**,
y compris pour les filiales en EUR/MAD/GHS. Pour une filiale en XOF
(devise de consolidation, aucune conversion), cette formule redonne
exactement Capital + Réserves + Résultat net — aucune différence de
traitement, juste une formule plus générale. La quote-part groupe et la
quote-part minoritaire de ces capitaux propres appliquent ensuite
`% détention` / `% minoritaire` à cette même quantité.

**Intérêt minoritaire (formule) :**
```
minority_pct = 100 − ownership_pct
Résultat net minoritaire = minority_pct × Résultat_net_filiale
Capitaux propres minoritaires = minority_pct × (Actif_filiale − Passif_filiale), traduits en XOF
```
Vérifié à la main sur le jeu de données décembre 2026 : NOVA Côte d'Ivoire
(75 % détenue, 25 % minoritaire), résultat net calculé 26 440 654,31 XOF
→ quote-part minoritaire = 25 % × 26 440 654,31 = 6 610 163,58 XOF,
exactement la valeur produite par le run.

### Ajustements de consolidation : écriture à un seul compte
Le schéma retient une ligne par ajustement (compte, sens, montant, motif) —
pas une écriture à deux lignes équilibrées comme un vrai journal comptable.
Puisque les capitaux propres groupe sont calculés en résiduel (voir
ci-dessus), un ajustement sur un compte d'actif ou de passif est
automatiquement absorbé par les capitaux propres consolidés, sans qu'il
soit nécessaire de saisir une seconde ligne de contrepartie. C'est le
choix le plus simple compatible avec le schéma existant et avec
l'exigence du cahier des charges ("écritures débit/crédit" — sans exiger
explicitly un journal à double entrée équilibré ligne à ligne).

### Hors périmètre V1 (voir aussi PROJECT_STATE.md)
Un run de consolidation est un instantané (aucun report des ajustements
ou de la réserve de conversion d'un mois sur l'autre) ; le CF n'est pas
consolidé ; pas d'élimination de marge interne sur stocks ; pas de
consolidation proportionnelle.

## Dashboards et Budget vs Actual (Phase 6)

### Vision "cumulée" vs résultat "consolidé"
Le dashboard CODIR et l'écran Budget vs Actual affichent une vision
**cumulée** : somme des filiales en intégration globale, **sans**
élimination intercompany ni mise en équivalence. C'est délibérément
différent du résultat **consolidé** officiel produit par un run (Phase 5),
pour deux raisons :
1. Un run n'existe pas forcément pour chaque mois de l'année — la tendance
   12 mois doit rester lisible même sans historique de runs.
2. Lancer une consolidation est un acte de gouvernance volontaire (voir
   §Workflow) ; le dashboard ne doit jamais recalculer silencieusement un
   résultat "consolidé" en le faisant passer pour officiel.

Le libellé à l'écran ("vision cumulée... hors éliminations") rend cette
distinction explicite. Sur les données de démonstration, l'écart entre les
deux visions est faible (le seul écart interco de décembre est un
`mismatch` non éliminé de toute façon, et aucun dividende n'est éliminable
dans ce périmètre) — vérifié : EBITDA cumulé décembre 198,8 M XOF vs EBITDA
du run de consolidation 198 750 149,75 XOF.

### Conversion multi-devises avant agrégation (bug corrigé en Phase 6)
Les montants filiale sont stockés en **devise locale**. Une première
version sommait directement `financial_data`/`budgets` sur plusieurs
filiales sans conversion — mélangeant XOF, EUR et MAD comme si c'était la
même unité (silencieusement faux : la France et le Maroc pesaient pour une
fraction dérisoire de leur poids réel). Corrigé : `ReportingService`
convertit chaque filiale en XOF (taux moyen, cohérent avec la convention
IS) **avant** toute somme multi-filiale. Vérifié à la main : Maroc décembre
2 687 098,65 MAD × 65,60 = 176 273 671,86 XOF, valeur exacte affichée.

### Palette catégorielle des filiales (dashboards)
Couleurs fixes par filiale (jamais recalculées selon le filtre actif),
validées CVD-safe (script `validate_palette.js` du système de dataviz
interne) : NOVA-CI bleu, NOVA-FR aqua, NOVA-GH jaune, NOVA-MA vert,
NOVA-ML violet, NOVA-SN rouge. Distinctes des 3 couleurs de tendance
(revenu/EBITDA/résultat net, bleu/aqua/violet) pour ne jamais laisser un
graphique de contribution et un graphique de tendance se contredire
visuellement sur le même écran.

### Seuil d'alerte "écart important"
Écart de chiffre d'affaires vs budget > 15 % (défavorable) par filiale —
choix simple et documenté plutôt qu'un seuil dérivé statistiquement,
cohérent avec le seuil d'anomalie de 50 % déjà utilisé en Phase 3 pour la
variation mensuelle (deux contextes différents : écart vs un budget fixé
à l'avance ici, variation d'un mois sur l'autre là).

## États financiers au format OHADA/SYCEBNL (ajustement post-Phase 6)

Sur demande utilisateur, les comptes de résultat et bilans (filiale et
consolidé) peuvent s'afficher au format normalisé OHADA (codes REF,
soldes intermédiaires de gestion — Marge commerciale, Valeur ajoutée,
EBE, Résultat d'exploitation, Résultat financier, RAO, Résultat HAO,
Résultat net). **Couche de présentation uniquement** (`app/helpers/ohada.php`) :
le plan de comptes interne (22 comptes, Phase 3) reste le moteur de
saisie/validation/consolidation — décision prise avec l'utilisateur pour
ne pas rouvrir les Phases 3 à 6 déjà testées. Chaque ligne OHADA sans
correspondance dans le plan simplifié affiche 0,00 (comme sur un état
réel d'une société qui ne mouvemente pas cette ligne).

**Mapping retenu** (résumé — voir le code pour le détail complet) :
- `REV + IC_REVENUE` → XB (Chiffre d'affaires) ; `COGS` → RC ; `OPEX_OTHER` → RH ;
  `IC_EXPENSE` → RJ ; `OPEX_PERS` → RK ; `DA` → RL ; `FIN_INCOME`/`FIN_EXPENSE` →
  TK/RM ; `TAX` → RS. Résultat HAO (XH) non tracké V1 → 0.
- Bilan : `FIXED_ASSETS` → AI ; `RECEIVABLES`+`IC_RECEIVABLE` → BI/BJ ; `CASH` → BS ;
  `SHARE_CAPITAL`/`RETAINED_EARNINGS` → CA/CH ; `FINANCIAL_DEBT` → DA ;
  `PAYABLES`/`IC_PAYABLE` → DJ/DM. Stocks (BB) non tracké V1 → 0.
- Le résultat net (XI sur le compte de résultat, CJ sur le bilan) est
  **toujours identique entre les deux états** — jamais utilisé comme
  variable d'ajustement (un CJ ≠ XI serait immédiatement repéré comme une
  erreur par n'importe quel comptable).

**Bug corrigé pendant l'implémentation — écart de conversion et titres
mis en équivalence :** sur la vue consolidée, l'actif (bilan traduit,
incluant les titres mis en équivalence de NOVA Ghana) et le passif
(capitaux propres = Capital + Réserves + Résultat net "naïf") ne
s'équilibraient plus (~40,8 M XOF d'écart, dont l'essentiel provenait des
titres mis en équivalence ajoutés à l'actif sans contrepartie côté
passif). Le modèle OHADA prévoit justement des lignes dédiées pour ce cas
(`BU` Écart de conversion-Actif / `DV` Écart de conversion-Passif),
laissées à 0 par oubli initial. Corrigé : ces lignes absorbent désormais
le résidu exact (`Actif − Passif avant écart`), garantissant l'équilibre
au centime près sans jamais toucher au résultat net affiché. Sur une
filiale mono-devise sans mise en équivalence, ce résidu ne capture que le
bruit d'arrondi `DECIMAL(18,2)` déjà documenté plus haut (jusqu'à
quelques centimes) — même mécanisme, contexte différent.

## Traçabilité, notifications et exports (Phase 7)

### `audit_logs` rattaché à filiale/période
Avant la Phase 7, une entrée d'audit n'était identifiable que par
`entity_type`/`entity_id`, dont la signification change selon l'action
(id d'un ajustement, d'une filiale, d'un run...) — impossible à filtrer
de façon fiable par filiale ou période sans réinterpréter chaque type
d'entité. Ajout de deux colonnes directes `subsidiary_id`/`period_id`
(nullables, `ON DELETE SET NULL` pour ne jamais perdre l'historique si
une filiale est désactivée), renseignées à la source par chaque service
métier qui journalise déjà l'action (Workflow, Intercompany, Subsidiary,
Period, ExchangeRate, Consolidation). Dénormalisation assumée : ces deux
colonnes dupliquent une information parfois déjà déductible de
`entity_type`/`entity_id`, mais le coût (2 colonnes + 2 index) est
minime face au bénéfice (un seul `WHERE` simple dans
`AuditLogRepository::filtered()` au lieu d'un cas par type d'entité).

### Notifications : évènement `consolidation_ready`
Les évènements `submission`/`rejection`/`mismatch` existaient déjà en
base depuis la Phase 4 (créés par `WorkflowService`/`IntercompanyService`)
mais n'avaient pas encore d'écran de consultation. Phase 7 ajoute cet
écran (`/notifications`, badge topbar) et un 4ᵉ évènement,
`consolidation_ready`, déclenché par `ConsolidationService` à la toute
fin d'un run **réussi** (après la dernière étape du pipeline, jamais en
cas d'échec — un run `failed` ne notifie personne, il reste visible sur
l'écran de détail du run pour l'utilisateur qui l'a lancé). Destinataires :
tous les utilisateurs des rôles `group_admin`, `consolidation_manager`,
`cfo_readonly` — les seuls rôles qui consultent les résultats consolidés
(un préparateur/contrôleur filiale n'a pas d'usage direct de cette
notification).

### Exports CSV plutôt que XLSX
Le cahier des charges évoque des exports "Excel/CSV". CSV a été retenu
seul (pas de génération `.xlsx`) pour rester cohérent avec la contrainte
explicite "zéro dépendance Composer" du projet : générer un vrai `.xlsx`
nécessite une bibliothèque (PhpSpreadsheet ou équivalent) ; un CSV
`;`-délimité avec BOM UTF-8 s'ouvre nativement dans Excel FR (virgule
déjà utilisée comme séparateur décimal, d'où le choix de `;`) sans
réglage d'import manuel, pour un coût d'implémentation nul. Le format
est centralisé dans `stream_csv_download()` (`app/helpers/helpers.php`)
pour garantir que les 3 exports (run de consolidation, paquet filiale,
vue dashboard) produisent un fichier identique en convention, même si
leurs colonnes diffèrent.

## Liasse groupe et dashboard CODIR enrichi (post-Phase 7, intégré à la Phase 8)

### Liasse groupe (`/financial-statements`)
Écran dédié présentant la liasse complète (compte de résultat + bilan
actif/passif, format OHADA) du dernier run **terminé** d'une période
choisie, sans avoir à connaître l'id d'un run précis. Réutilise
strictement les mêmes helpers de rendu (`app/helpers/ohada.php`) que la
section dépliable déjà présente sur `/consolidation/{id}` — même
formules, mêmes chiffres, un seul endroit où ils sont calculés (voir
point suivant). Ce n'est pas un nouveau moteur de calcul : c'est une
vue de consultation directe, pensée pour un usage "je veux voir les
états financiers du groupe", distincte de `/consolidation/{id}` qui
reste l'écran d'audit technique d'un run (étapes, éliminations,
intérêts minoritaires).

### Centralisation du calcul de synthèse (`ConsolidationService::computeSummary()`)
Avant cet ajout, le calcul du résultat net groupe/minoritaires et du
bilan groupe/minoritaires (dérivation Actif − Passif, cf. §Écart de
conversion) n'existait que dans `ConsolidationController::show()`. Pour
alimenter la liasse groupe et le nouveau panneau bilan du dashboard avec
exactement les mêmes chiffres — sans dupliquer une formule financière
sensible à trois endroits — cette logique a été extraite en une méthode
unique du service (`computeSummary(array $lineItems, int $runId): array`),
appelée par les trois consommateurs (détail d'un run, liasse groupe,
panneau bilan du dashboard). Un seul endroit à corriger si la formule
évolue ; les trois écrans ne peuvent plus diverger entre eux.

### Dashboard CODIR : marges, bilan groupe, classement des filiales
- **Marges (EBITDA %, nette %)** : calculées en vue (ratio de deux
  chiffres déjà validés par `ReportingService::kpis()`), pas de nouvelle
  donnée ni de nouveau calcul métier — juste une division.
- **Situation bilancielle groupe** : affichée uniquement (a) en vue
  groupe non filtrée (un bilan consolidé n'a pas de sens "par filiale"
  ou "par pays" — un run de consolidation porte toujours sur tout le
  périmètre) et (b) seulement si un run **terminé** existe déjà pour la
  période affichée. Si aucun run n'existe, le panneau est simplement
  absent (pas de placeholder "0 XOF" trompeur) — cohérent avec la règle
  "aucune valeur en dur / aucune fausse donnée" du projet.
- **Classement des filiales (`ReportingService::subsidiaryScorecard()`)** :
  tableau complet (CA, EBITDA, écarts vs budget, marge EBITDA, résultat
  net) par filiale en intégration globale, en complément du graphique de
  contribution déjà existant — un CODIR veut le chiffre exact, pas
  seulement la barre.
- **Bug corrigé pendant l'implémentation** : le panneau bilan groupe
  utilisait `$balanceSheetRun['period_label']`, une colonne absente de
  la requête `ConsolidationRunRepository::latestCompletedForPeriod()`
  (pas de jointure sur `reporting_periods`, à la différence de `all()`
  et `findById()`) — provoquait un avertissement PHP silencieux (chaîne
  vide affichée) sans faire planter la page. Corrigé en réutilisant la
  variable `$period->label` déjà disponible dans la vue plutôt que
  d'ajouter une jointure superflue au repository pour une seule colonne
  déjà connue de l'appelant.

### Export PDF : impression navigateur plutôt qu'une bibliothèque
Comme pour CSV vs XLSX, le export PDF n'ajoute aucune dépendance : un
bouton "Exporter en PDF" déclenche `window.print()`, et une feuille de
style dédiée (`@media print` dans `app.css`) masque la navigation,
ajoute un en-tête document (groupe, période, date de génération) et
évite les coupures de page au milieu d'un panneau (`break-inside:
avoid`). Tout navigateur moderne propose "Enregistrer en PDF" comme
destination d'impression — c'est donc un export PDF complet sans code
serveur de génération PDF. Appliqué au dashboard CODIR et à la liasse
groupe, les deux écrans où l'utilisateur a demandé cette fonctionnalité.

## Export CSV de la liasse groupe au format OHADA (2026-08-12)

Constat utilisateur : le bouton "Exporter (CSV)" de la liasse groupe pointait vers `consolidationRun()`
(`ExportController`), qui exporte les **montants bruts par compte interne** (22 comptes) — pas la présentation
OHADA (codes REF, soldes intermédiaires de gestion) affichée à l'écran. Un utilisateur ouvrant l'export
s'attendait logiquement à retrouver ce qu'il voyait sur la page, pas une structure différente.

Corrigé en ajoutant `ExportController::financialStatements()` (route `/exports/financial-statements/{runId}`),
qui exporte le compte de résultat + bilan actif + bilan passif **exactement comme affichés**, en 3 sections
dans un seul CSV. Pour garantir que cet export ne puisse jamais diverger de l'écran, les définitions de lignes
OHADA (référence, libellé, nature normal/sous-total/total) ont été extraites de `render_ohada_*()`
(`app/helpers/ohada.php`) vers des fonctions dédiées (`ohada_income_statement_rows()`,
`ohada_balance_sheet_actif_rows()`, `ohada_balance_sheet_passif_rows()`) — un seul endroit définit "quelles
lignes, dans quel ordre", partagé par l'affichage HTML et l'export CSV. Le bouton de la page Liasse groupe
(`views/consolidation/statements.php`) a été repointé vers ce nouvel export ; l'export brut par compte
(`consolidationRun()`) reste inchangé et disponible sur l'écran technique "Détail du run"
(`/consolidation/{id}`), où il a plus de sens (audit ligne par ligne, pas présentation normalisée).

Vérifié : export du run décembre 2026 — BZ (total actif) = DZ (total passif) = 3 084 176 188,74 XOF et
XI (résultat net compte de résultat) = CJ (résultat net bilan) = 114 175 717,20 XOF, valeurs identiques à celles
vérifiées à la main en Phase 5 et affichées à l'écran.
