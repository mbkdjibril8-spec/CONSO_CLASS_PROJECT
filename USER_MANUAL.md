# Manuel utilisateur — OHADA_CONSO+

Ce manuel explique comment utiliser la plateforme OHADA_CONSO+, écran par écran, selon votre rôle. Pour la logique financière détaillée (formules de consolidation, conventions OHADA), voir [`docs/CONSOLIDATION_LOGIC.md`](docs/CONSOLIDATION_LOGIC.md).

## Sommaire

1. [Connexion et comptes de démonstration](#1-connexion-et-comptes-de-démonstration)
2. [Les rôles de la plateforme](#2-les-rôles-de-la-plateforme)
3. [Tableau de bord](#3-tableau-de-bord)
4. [Saisie des données financières (Préparateur)](#4-saisie-des-données-financières-préparateur)
5. [Validation du paquet filiale (Contrôleur)](#5-validation-du-paquet-filiale-contrôleur)
6. [Intercompany](#6-intercompany)
7. [Périodes de reporting](#7-périodes-de-reporting)
8. [Taux de change](#8-taux-de-change)
9. [Consolidation](#9-consolidation)
10. [Liasse groupe (états financiers OHADA)](#10-liasse-groupe-états-financiers-ohada)
11. [Budget vs Actual](#11-budget-vs-actual)
12. [Notifications](#12-notifications)
13. [Journal d'audit](#13-journal-daudit)
14. [Exports (CSV / PDF)](#14-exports-csv--pdf)

---

## 1. Connexion et comptes de démonstration

Rendez-vous sur `http://localhost/groupfin/public/`. Tous les comptes de démonstration utilisent le mot de passe :

```
Groupfin@2026
```

| Compte | Rôle | Filiale |
|---|---|---|
| admin@novaafrica.com | Administrateur groupe | — |
| consolidation@novaafrica.com | Responsable consolidation | — |
| cfo@novaafrica.com | Directeur financier (lecture seule) | — |
| preparer.sn@novaafrica.com / controller.sn@novaafrica.com | Préparateur / Contrôleur | Sénégal |
| preparer.ci@novaafrica.com / controller.ci@novaafrica.com | Préparateur / Contrôleur | Côte d'Ivoire |
| preparer.ml@novaafrica.com / controller.ml@novaafrica.com | Préparateur / Contrôleur | Mali |
| preparer.ma@novaafrica.com / controller.ma@novaafrica.com | Préparateur / Contrôleur | Maroc |
| preparer.gh@novaafrica.com / controller.gh@novaafrica.com | Préparateur / Contrôleur | Ghana (mise en équivalence) |
| preparer.fr@novaafrica.com / controller.fr@novaafrica.com | Préparateur / Contrôleur | France |

**Scénario de démonstration (état de départ)** : pour la période en cours, 5 des 6 filiales opérationnelles ont déjà soumis leur paquet financier ; le Maroc est encore en brouillon. Un écart intercompany Sénégal/France est déclaré et non résolu. Aucune consolidation n'a encore été lancée. Les 12 mois de l'année sont ouverts à la saisie (pas seulement le mois courant), pour permettre de tester le cycle complet sur n'importe quelle période.

## 2. Les rôles de la plateforme

| Rôle | Peut faire |
|---|---|
| **Préparateur** | Saisir/importer les données financières de sa filiale, soumettre le paquet pour validation. |
| **Contrôleur de filiale** | Valider ou rejeter (avec motif) le paquet soumis par le préparateur de sa filiale. Lecture seule sur la saisie. |
| **Responsable consolidation** | Tout ce que voit un rôle groupe (filiales, périodes, taux, consolidation, audit) + lancer une consolidation, poster des ajustements, faire avancer une période. |
| **Administrateur groupe** | Tout ce que peut faire le Responsable consolidation, + gestion des filiales et de la structure de groupe. |
| **Directeur financier (lecture seule)** | Accès en lecture à tous les écrans groupe (dashboard, liasse, audit, exports) — aucune action de saisie ou de validation. |

Un préparateur/contrôleur ne voit et ne peut agir que sur **sa propre filiale** — contrôlé côté serveur, pas seulement masqué à l'écran.

## 3. Tableau de bord

Écran d'accueil après connexion (`/dashboard`).

- **Vue groupe** (rôles transverses) : sélecteurs Période / Pays / Filiale (mise à jour instantanée sans rechargement de page), KPIs Chiffre d'affaires / EBITDA / Résultat net (Actual vs Budget), marges, tendance 12 mois, contribution EBITDA par filiale, **classement complet des filiales** (CA, EBITDA, écarts, marge, résultat net), panneau **Situation bilancielle groupe** (dès qu'une consolidation existe pour la période — total bilan, capitaux propres, ratio d'endettement), alertes (paquets non validés, écarts budgétaires importants, mismatch intercompany, "prêt à consolider").
- **Vue filiale** (Préparateur/Contrôleur) : mêmes KPIs et tendance, restreints à sa propre filiale.
- **Exporter en PDF** : bouton en haut de l'écran — ouvre la boîte de dialogue d'impression du navigateur ; choisir "Enregistrer au format PDF" comme imprimante. Génère un document propre (sans menus ni boutons, en-tête avec groupe/période/date).
- **Exporter cette vue (CSV)** : données brutes des KPIs affichés.

## 4. Saisie des données financières (Préparateur)

`Données financières` (menu latéral) → choisir la période.

- Formulaire regroupé en 3 blocs : Compte de résultat (IS), Bilan (BS), Flux de trésorerie (CF).
- Le contrôle d'équilibre bilanciel (`Actif = Passif + Capitaux propres + Résultat net`) s'affiche automatiquement dès que les comptes concernés sont renseignés ; un déséquilibre bloque l'enregistrement avec le détail chiffré de l'écart.
- Une variation de chiffre d'affaires de plus de 50 % par rapport au mois précédent déclenche un avertissement (non bloquant).
- **Import CSV** : fichier 2 colonnes `account_code,amount` avec en-tête, peut couvrir tout ou partie des comptes. Les lignes invalides sont rapportées avec leur numéro de ligne sans bloquer l'import des lignes valides.
- **Soumettre pour validation** : disponible une fois tous les comptes renseignés et le bilan équilibré. Le paquet devient alors non modifiable jusqu'à ce que le contrôleur le valide ou le rejette.
- **Voir l'état financier** : présentation du compte de résultat et du bilan de la filiale au format normalisé OHADA/SYCEBNL.

## 5. Validation du paquet filiale (Contrôleur)

Sur l'écran de saisie d'un paquet `soumis`, le contrôleur voit un panneau "Revue du contrôleur" :

- **Valider le paquet** : le paquet passe au statut `validé` et devient éligible à la prochaine consolidation.
- **Rejeter** (motif obligatoire) : le paquet repasse en brouillon chez le préparateur, avec le motif affiché en bannière ; celui-ci peut corriger et resoumettre. Le cycle `soumis → rejeté → soumis → validé` est illimité.

L'historique complet des transitions (qui, quand, quel commentaire) est visible en bas de l'écran de saisie.

## 6. Intercompany

`Intercompany` (menu latéral) — déclaration des créances/dettes/produits/charges/dividendes entre filiales du groupe.

1. Déclarer sa transaction (type, filiale contrepartie, montant en devise locale).
2. Le système recherche automatiquement la déclaration miroir côté contrepartie (créance ↔ dette, produit ↔ charge) et calcule l'écart après conversion en XOF (taux de clôture pour les soldes de bilan, taux moyen pour les flux).
3. Trois statuts possibles : `en attente` (contrepartie pas encore déclarée), `rapproché` (montants identiques après conversion), `écart` (montants différents — notifie les deux contrôleurs et le responsable consolidation).
4. Seules les paires **rapprochées** sont éliminées automatiquement lors d'une consolidation ; un écart non résolu reste visible et peut être traité via un ajustement manuel de consolidation.

## 7. Périodes de reporting

`Périodes` (menu latéral) — cycle de vie strictement séquentiel, sans saut ni retour en arrière :

```
Ouverte → En cours → Soumise → En revue → Validée → Consolidée → Clôturée
```

Rôles groupe uniquement pour faire avancer une période. Une fois `Clôturée`, plus aucune saisie n'est possible sur cette période (y compris les taux de change).

## 8. Taux de change

`Taux de change` (menu latéral, rôles groupe) — taux moyen (résultat) et taux de clôture (bilan) par devise et par période. Verrouillés dès que la période est clôturée.

## 9. Consolidation

`Consolidation` (menu latéral, rôles groupe) :

- **Lancer une consolidation** pour une période : nécessite que toutes les filiales du périmètre (intégration globale + mise en équivalence) soient `validées`. Le pipeline (7 étapes tracées) s'exécute : vérification du périmètre, conversion/agrégation, éliminations intercompany, éliminations de dividendes, mise en équivalence, ajustements manuels, intérêts minoritaires.
- **Détail d'un run** : journal d'exécution étape par étape, compte de résultat et bilan consolidés, répartition part du groupe / minoritaires, taux utilisés, détail des éliminations.
- **Ajustements de consolidation** (`/consolidation/adjustments`) : écritures manuelles (ex. régularisation d'un écart interco non résolu), appliquées au prochain run.

## 10. Liasse groupe (états financiers OHADA)

`Liasse groupe` (menu latéral, rôles groupe) — compte de résultat et bilan consolidés au format normalisé OHADA/SYCEBNL, pour le dernier run terminé de la période choisie. C'est le document de synthèse à présenter tel quel (sélecteur de période, exports CSV et PDF), distinct de l'écran technique "détail d'un run" (étapes, éliminations).

## 11. Budget vs Actual

`Budget vs Actual` (menu latéral) — tableau détaillé par compte, mois sélectionné et cumul depuis janvier (YTD), avec écart et écart % (sens favorable/défavorable adapté selon qu'il s'agit d'un produit ou d'une charge).

## 12. Notifications

Icône cloche dans la barre supérieure (badge = nombre de notifications non lues). Événements notifiés : soumission d'un paquet, rejet, écart intercompany détecté, consolidation terminée (rôles groupe uniquement pour ce dernier).

## 13. Journal d'audit

`Journal d'audit` (menu latéral, rôles groupe) — historique de toutes les actions (créations, modifications, validations, rejets, refus d'accès), filtrable par utilisateur, filiale et période, avec ancienne/nouvelle valeur pour chaque modification.

## 14. Exports (CSV / PDF)

- **CSV** (séparateur `;`, encodage UTF-8 avec BOM — s'ouvre correctement dans Excel FR) : disponible sur le tableau de bord, un paquet de saisie filiale, un run de consolidation, la liasse groupe.
- **PDF** : bouton "Exporter en PDF" sur le tableau de bord et la liasse groupe — utilise la fonction d'impression du navigateur ("Enregistrer au format PDF"), avec une mise en page dédiée à l'impression (sans menus, en-tête document).
