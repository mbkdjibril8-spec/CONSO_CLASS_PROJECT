# Guide de test complet — OHADA_CONSO+

Script de test pas à pas, de la connexion jusqu'aux états financiers consolidés et au reporting annuel.
Chaque étape indique : le compte à utiliser, l'action à faire, et ce qu'il faut vérifier (☐). Pour la
description exhaustive de chaque écran, voir [`USER_MANUAL.md`](USER_MANUAL.md) — ce document-ci est un
parcours guidé, pas une référence.

Mot de passe de tous les comptes de démonstration : `Groupfin@2026`.

## 0. État de départ attendu

Avant de commencer, la base doit être dans son état de démonstration initial :

- 5 filiales sur 6 déjà **soumises** pour décembre 2026 (Sénégal, Côte d'Ivoire, Mali, France, Ghana) ; le
  **Maroc reste en brouillon** — c'est la filiale sur laquelle ce guide fait tester le cycle complet.
- Un écart intercompany **non résolu** entre Sénégal (créance) et France (dette) sur décembre 2026.
- **Aucun run de consolidation** encore lancé.
- Les 12 mois de 2026 sont ouverts (`in_progress`) — n'importe quel mois est utilisable pour la saisie.

Si ce n'est pas le cas (base modifiée par un test précédent), réinitialise-la (§10 en fin de document) avant
de commencer.

---

## 1. Connexion

1. Ouvrir `http://localhost/groupfin/public/`.
2. Se connecter avec `admin@novaafrica.com`.
   - ☐ Redirection vers `/dashboard`, KPIs affichés (tuiles héro colorées CA/EBITDA/résultat net/marges).
3. Se déconnecter, retenter avec un mauvais mot de passe.
   - ☐ Message d'erreur affiché, pas de session créée.

## 2. Structure de groupe (Administrateur)

1. Menu `Filiales` → liste des 7 entités (NOVA Holding + 6 filiales opérationnelles).
   - ☐ Chaque ligne affiche pays, devise, % détention, méthode de consolidation.
2. Menu `Hiérarchie` → arbre visuel (org-chart).
   - ☐ NOVA Holding en racine, 6 filiales reliées par des traits.
   - ☐ Cliquer le bouton rond sous NOVA Holding : les 6 filiales se replient (icône passe à `+6`), re-cliquer
     pour les redéplier.
3. Cliquer sur une filiale (ex. NOVA-SN) → fiche détaillée en lecture seule.

## 3. Périodes de reporting

1. Menu `Périodes` → 12 lignes pour 2026, statut `En cours` pour chacune.
   - ☐ Aucune période à `Clôturée` (état de départ).
2. Ne pas faire avancer de période ici — on y reviendra en fin de guide (§9, reporting annuel).

## 4. Taux de change

1. Menu `Taux de change`, sélectionner décembre 2026.
   - ☐ Taux moyen et de clôture affichés pour EUR, MAD, GHS (distincts pour MAD/GHS — normal, deux
     conventions de conversion différentes selon le type d'état, voir `docs/CONSOLIDATION_LOGIC.md`).
2. Modifier une valeur, enregistrer.
   - ☐ Nouvelle valeur persistée, visible après rechargement.
3. Remettre la valeur d'origine (pour ne pas fausser les vérifications suivantes) ou passer directement au
   reset de fin de guide plus tard.

## 5. Saisie et workflow — cycle complet sur le Maroc

C'est la filiale volontairement laissée en brouillon dans le scénario de démonstration, pour tester le cycle
complet de bout en bout.

1. Se connecter en `preparer.ma@novaafrica.com`.
2. Menu `Données financières` → décembre 2026 → formulaire de saisie (compte de résultat / bilan / flux de
   trésorerie).
   - ☐ Les 22 comptes sont déjà pré-remplis (données de démonstration) ; le contrôle d'équilibre bilanciel
     affiche "Bilan équilibré".
3. Modifier volontairement un montant du bilan (ex. Trésorerie) pour casser l'équilibre, cliquer
   `Enregistrer`.
   - ☐ Sauvegarde **bloquée**, message d'erreur donnant l'écart exact en XOF.
4. Remettre la valeur d'origine, enregistrer à nouveau.
   - ☐ "Bilan équilibré" réapparaît.
5. Cliquer `Soumettre pour validation`.
   - ☐ Bannière "Paquet soumis, en attente de validation" ; le formulaire devient en lecture seule.
6. Se connecter en `controller.ma@novaafrica.com`.
7. Sur le même écran (décembre 2026, Maroc), panneau "Revue du contrôleur" → `Rejeter`, motif obligatoire
   (ex. "Écart de marge vs budget à justifier").
   - ☐ Paquet repasse en brouillon, motif affiché en bannière côté préparateur, notification créée.
8. Se reconnecter en `preparer.ma@novaafrica.com`, re-soumettre sans rien changer.
9. Se reconnecter en `controller.ma@novaafrica.com`, cliquer `Valider le paquet`.
   - ☐ Bannière "Paquet validé".
   - ☐ Bas de page : historique complet des 4 transitions (soumis → rejeté → soumis → validé), avec
     utilisateur/date/commentaire pour chacune.

## 6. Valider les 5 filiales déjà soumises

Pour chacune (Sénégal, Côte d'Ivoire, Mali, France, Ghana) :

1. Se connecter en `controller.<code>@novaafrica.com` (ex. `controller.sn@novaafrica.com`).
2. `Données financières` → décembre 2026 → `Valider le paquet`.
   - ☐ Bannière "Paquet validé".

Une fois les 6, menu `Tableau de bord` (compte admin) doit afficher l'alerte "Toutes les filiales du périmètre
sont validées : la consolidation peut être lancée."

## 7. Intercompany

1. Se connecter en `consolidation@novaafrica.com` (rôle groupe, voit toutes les déclarations).
2. Menu `Intercompany` → repérer la paire Sénégal (créance, 100 000 000 XOF) / France (dette).
   - ☐ Statut `Écart` — les montants convertis en XOF diffèrent de 5 000 000 XOF.
3. Deux options pour la suite (au choix, aucune n'est obligatoire pour continuer) :
   - **Laisser tel quel** : le run de consolidation (§8) n'éliminera pas cette paire et le signalera dans son
     journal — comportement attendu, illustre la traçabilité d'un écart non résolu.
   - **Corriger via un ajustement** : menu `Consolidation` → `Ajustements` → poster un ajustement de
     -5 000 000 XOF sur le compte `IC_RECEIVABLE`, filiale Sénégal, motif "Régularisation écart interco
     décembre". Sera appliqué au prochain run.

## 8. Lancer la consolidation

1. Se connecter en `admin@novaafrica.com` ou `consolidation@novaafrica.com`.
2. Menu `Consolidation` → sélectionner décembre 2026 → `Lancer la consolidation`.
   - ☐ Redirection vers le détail du run, statut `Terminé`.
   - ☐ Journal d'exécution : 7 étapes toutes à `Terminé` (périmètre, conversion, éliminations intercompany,
     éliminations dividendes, mise en équivalence, ajustements, intérêts minoritaires).
   - ☐ Si l'écart interco n'a pas été corrigé à l'étape 7 : l'étape "Éliminations intercompany" mentionne
     explicitement l'écart non résolu dans ses détails.
3. Vérifier le compte de résultat et le bilan consolidés affichés sur cet écran (résultat net, total actif,
   répartition part du groupe / minoritaires).
   - ☐ Total actif = Total passif + capitaux propres (au centime près).
4. Dépliant "Afficher au format normalisé OHADA/SYCEBNL" en bas de l'écran → aperçu rapide de la liasse (le
   détail complet est à l'écran dédié, §9 ci-dessous).

## 9. Liasse groupe — états financiers consolidés (OHADA)

1. Menu `Liasse groupe`.
   - ☐ Sélecteur de période affiche "2026-12 (run du ...)".
   - ☐ Compte de résultat consolidé (codes REF : XB chiffre d'affaires, XC valeur ajoutée, XD EBE, XE résultat
     d'exploitation, XF résultat financier, XI résultat net).
   - ☐ Bilan consolidé actif + passif (BZ = DZ, total général identique des deux côtés).
2. Cliquer `Exporter la liasse (CSV)`.
   - ☐ Fichier téléchargé (`liasse_groupe_2026-12.csv`), 3 sections (compte de résultat, bilan actif, bilan
     passif), ouvrable dans Excel (séparateur `;`, accents corrects).
   - ☐ Valeurs identiques à celles affichées à l'écran.
3. Cliquer `Exporter (PDF)` → boîte de dialogue d'impression du navigateur s'ouvre, choisir "Enregistrer en
   PDF".
   - ☐ Aperçu sans menu ni bouton, en-tête "NOVA AFRICA GROUP — Liasse consolidée — 2026-12".

## 10. Dashboard CODIR (reporting groupe)

1. Menu `Tableau de bord`, période décembre 2026, filtre filiale = "Toutes (groupe)".
   - ☐ 5 tuiles héro (CA, EBITDA, résultat net, marge EBITDA, marge nette).
   - ☐ Panneau "Situation bilancielle groupe" (apparaît maintenant qu'un run existe) — ratio d'endettement,
     lien "Voir la liasse complète".
   - ☐ Courbe de tendance 12 mois.
   - ☐ Donut de répartition : basculer entre "Chiffre d'affaires" et "EBITDA" (bouton) → le graphique se
     redessine sans rechargement de page, labels reliés aux parts par un trait.
   - ☐ Graphique de contribution EBITDA par filiale (barres), à côté du donut.
   - ☐ Tableau "Performance par filiale" : CA/EBITDA/écarts/marge/résultat net, une ligne par filiale.
2. Changer le filtre `Filiale` sur une seule (ex. NOVA-CI).
   - ☐ KPIs recalculés sans rechargement de page (AJAX), panneau bilancielle et donut disparaissent (non
     pertinents en vue filiale unique).
3. Bouton `Exporter en PDF` en haut de l'écran → même mécanisme que §9.3.
4. Bouton `Exporter cette vue (CSV)` (dans le panneau de filtres) → export des KPIs bruts.

## 11. Budget vs Actual

1. Menu `Budget vs Actual`, décembre 2026.
   - ☐ Tableau par compte IS, colonnes Mois et Cumul YTD (depuis janvier), Actual/Budget/Écart/Écart %.
   - ☐ Lignes de synthèse en bas ("Chiffre d'affaires total", "EBITDA", "Résultat net"), écart coloré (vert
     favorable / rouge défavorable).

## 12. Notifications

1. Se connecter en `controller.ma@novaafrica.com` (a reçu plusieurs évènements pendant ce guide).
   - ☐ Badge cloche dans la topbar avec un nombre > 0.
2. Menu `Notifications` (ou clic sur la cloche).
   - ☐ Liste des évènements (rejet du §5.7 notamment).
3. `Tout marquer comme lu`.
   - ☐ Badge repasse à 0.

## 13. Journal d'audit

1. Se connecter en `admin@novaafrica.com`, menu `Journal d'audit`.
2. Filtrer par filiale = NOVA-MA.
   - ☐ Toutes les transitions du §5 apparaissent (soumission, rejet, resoumission, validation), avec
     ancienne/nouvelle valeur.
3. Filtrer par utilisateur = le compte préparateur/contrôleur Maroc.
   - ☐ Résultats cohérents avec le filtre.
4. Se connecter en `preparer.sn@novaafrica.com`, tenter d'accéder à `/audit` directement par l'URL.
   - ☐ 403 Accès refusé (rôle filiale, pas groupe).

## 14. Reporting annuel — bascule automatique d'exercice (approfondi, optionnel)

Cette section vérifie que l'exercice 2027 s'ouvre automatiquement une fois 2026 entièrement clôturé.
**Attention** : clôturer un mois est **irréversible** dans l'application (pas de retour en arrière dans le
cycle de vie) — à ne faire que sur une base de test, et à réinitialiser ensuite (§15).

1. Menu `Périodes` (compte admin) : chaque mois doit passer par la séquence complète avant de pouvoir être
   clôturé : `En cours → Soumise → En revue → Validée → Consolidée → Clôturée` (bouton "Faire avancer" à
   chaque étape). C'est volontairement long à la main pour les 12 mois — reflète une vraie discipline de
   clôture mensuelle, pas un raccourci de démonstration.
2. Faire avancer **décembre 2026** jusqu'à `Clôturée`.
   - ☐ Tant que les 11 autres mois ne sont pas eux aussi à `Clôturée`, rien de spécial ne se passe.
3. Une fois que **les 12 mois de 2026** sont à `Clôturée` (le dernier fait basculer l'exercice, quel que soit
   l'ordre dans lequel les mois ont été clôturés) :
   - ☐ 12 nouvelles périodes apparaissent pour **2027** (statut `Ouverte`), visibles sur `/periods`.
   - ☐ Menu `Taux de change`, période janvier 2027 : les taux EUR/MAD/GHS de décembre 2026 sont déjà
     renseignés (moyen et clôture repris à l'identique).
   - ☐ Notification "Exercice ouvert" reçue par les rôles groupe (Admin, Responsable consolidation, DAF).
   - ☐ Journal d'audit : entrée `fiscal_year_opened`.
4. Ce mécanisme a déjà été vérifié en profondeur pendant le développement (voir `PROJECT_STATE.md` §"Bascule
   automatique d'exercice") — cette étape du guide sert à le voir fonctionner soi-même, pas à le re-certifier.

## 15. Réinitialiser la base de démonstration

Après ce guide (notamment après la §14, qui clôture des périodes de façon irréversible), remettre la base à
son état de départ :

```
mysql -u root -e "DROP DATABASE IF EXISTS groupfin; CREATE DATABASE groupfin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root groupfin < database/schema.sql
mysql -u root groupfin < database/seed.sql
php database/seed_financials.php
php database/seed_workflow.php
php database/seed_intercompany.php
```

Revérifier ensuite l'état de départ décrit en §0.
