# PROJECT_STATE — OHADA_CONSO+

## Phase courante
**Phase 8 — Tests, documentation, packaging : TERMINÉE ✅** (inclut la liasse groupe et le dashboard CODIR
enrichi, ajoutés au périmètre à la demande de l'utilisateur le 2026-08-09).
**OHADA_CONSO+ V1 est complet.** Prochaines étapes : voir "Cuts / V2 backlog" ci-dessous (productisation multi-client,
dynamisme approfondi) — explicitement reportées après V1 par décision utilisateur.
**Ajustement post-Phase 8 (2026-08-09) : TERMINÉ ✅** — renommage GROUPFIN → OHADA_CONSO+, correctifs UX dashboard,
arbre de hiérarchie dynamique, icônes de navigation animées (voir section dédiée ci-dessous).
**Ajustement UX dashboard (2026-08-12) : TERMINÉ ✅** — tuiles KPI héro symétriques, donut à labels reliés
(callout), panneaux graphiques à hauteur égale (voir section dédiée ci-dessous).
**Export CSV de la liasse groupe (2026-08-12) : TERMINÉ ✅** — nouveau bouton exportant le compte de résultat
et le bilan au format OHADA tel qu'affiché à l'écran (voir section dédiée ci-dessous).
**Bascule automatique d'exercice (2026-08-12) : TERMINÉ ✅** — l'année N+1 s'ouvre automatiquement (12 périodes
+ taux de change repris) dès que les 12 mois de l'année N sont clôturés ; fonctionnalité absente jusqu'ici,
signalée par une question directe de l'utilisateur (voir section dédiée ci-dessous).
**Guide de test complet (2026-08-13) : TERMINÉ ✅** — `GUIDE_DE_TEST.md`, parcours pas à pas de la connexion
jusqu'aux états financiers consolidés et au reporting annuel, chaque étape rejouée et vérifiée en conditions
réelles avant livraison (voir section dédiée ci-dessous).

## Installation
- Racine du projet : `C:\xampp\htdocs\groupfin`
- Accès navigateur : `http://localhost/groupfin/public/`
- Base de données : `groupfin` (MySQL/MariaDB via XAMPP)
- PHP servi par Apache (XAMPP) : 8.0.30 — testé et compatible (aucune syntaxe 8.1+ utilisée : pas d'enums PHP, pas de `readonly`, pas de `match`).

## Phase 1 — Réalisé
- Architecture MVC maison sans dépendance (autoload PSR-4 simplifié via `spl_autoload_register`, pas de Composer requis).
- `app/core/` : `Database` (PDO singleton), `Request`, `Router` (routes + middlewares + paramètres dynamiques `{id}`), `Session` (durcie : httponly, SameSite=Lax, régénération à la connexion), `View`, `Controller`.
- `database/schema.sql` : schéma complet (18 tables) couvrant l'intégralité du périmètre V1 (référentiels, structure de groupe, périodes, collecte, workflow, intercompany, consolidation, notifications, audit). Rejouable sur base vierge.
- `database/seed.sql` : référentiels (rôles, devises), structure NOVA AFRICA GROUP (7 filiales), 15 utilisateurs de démonstration (1 admin, 1 responsable consolidation, 1 DAF, 1 binôme préparateur/contrôleur par filiale opérationnelle), 12 périodes 2026, taux de change EUR/MAD/GHS sur 12 mois. **Sera complété phase après phase** (données financières en Phase 3, intercompany en Phase 4, runs de consolidation en Phase 5).
- Authentification par mot de passe (`password_hash`/`password_verify`), session régénérée à la connexion, déconnexion.
- RBAC à deux niveaux : `AuthMiddleware` (session active) + `AuthorizationMiddleware` (rôle autorisé + portée filiale). Tout refus est journalisé dans `audit_logs` avant l'affichage du 403.
- CSRF sur tous les formulaires POST (`CsrfMiddleware` + `csrf_field()`/`csrf_verify()`).
- Échappement systématique des sorties via l'helper `h()`.
- Système de design "salle de contrôle financière" (voir cahier des charges §7) : `public/assets/css/app.css` — palette neutre + accents sémantiques uniquement, tables denses, chiffres tabulaires, pas de dégradés/glassmorphism.
- Écrans Phase 1 : connexion, tableau de bord adapté au rôle (vue groupe pour rôles transverses, vue filiale pour préparateur/contrôleur), fiche filiale en lecture seule (`/subsidiaries/{id}`), pages 403/404.

## Vérifications exécutées (DoD Phase 1)
Toutes vérifiées en conditions réelles (Apache + PHP 8.0.30 + MariaDB 10.4, requêtes HTTP réelles via curl) :
- `schema.sql` puis `seed.sql` s'exécutent sans erreur sur base vierge (5 rôles, 4 devises, 7 filiales, 15 utilisateurs, 12 périodes, 72 taux de change).
- Connexion/déconnexion fonctionnelles pour les 5 rôles (mot de passe démo : `Groupfin@2026`), testé sur 3 cycles complets consécutifs.
- Chaque rôle voit un accueil adapté : rôles groupe → liste des 7 filiales + KPIs ; préparateur/contrôleur → fiche de leur propre filiale uniquement.
- Accès non authentifié à `/dashboard` → redirection 302 vers `/login` + entrée `audit_logs` (action `unauthorized_access`).
- Préparateur Sénégal (filiale id=2) : accès à `/subsidiaries/2` → 200 ; accès à `/subsidiaries/3` (Côte d'Ivoire) → 403 + entrée `audit_logs` avec le motif exact.
- Tentative de connexion avec mauvais mot de passe → entrée `audit_logs` (action `login_failed`), aucune session créée.

## Phase 2 — Réalisé
- `SubsidiaryService` : validation complète (code unique, devise existante, absence de cycle dans la hiérarchie via `ancestorIds()`, pourcentages 0-100, méthode de consolidation valide), création/édition, activation/désactivation (pas de suppression physique — traçabilité), construction de l'arbre de hiérarchie.
- Écrans filiales : liste (`/subsidiaries`), arbre de hiérarchie récursif (`/subsidiaries/tree`), création/édition avec ré-affichage des erreurs par champ, fiche filiale enrichie (société mère, actions Modifier/Désactiver réservées à l'administrateur groupe).
- `PeriodService` : cycle de vie strictement séquentiel (`Open → In Progress → Submitted → Under Review → Validated → Consolidated → Closed`), aucun saut ni retour en arrière — vérifié y compris par contournement direct du formulaire (POST forgé), rejeté côté serveur avec message explicite.
- `ExchangeRateService` : upsert des taux moyen/clôture par devise et par période ; période clôturée = verrouillée (vérifié par tentative d'écriture forcée sur janvier 2026, rejetée, valeur inchangée en base).
- Toutes les mutations (création/édition/activation filiale, transition période, mise à jour des taux) journalisées dans `audit_logs` avec ancienne/nouvelle valeur.
- Navigation latérale enrichie selon le rôle : rôles groupe voient Filiales/Hiérarchie/Taux de change en plus de Tableau de bord/Périodes ; rôles filiale ne voient que Tableau de bord/Périodes (lecture seule, utile pour connaître le statut de la période en cours).
- `Controller::view()` injecte désormais automatiquement `$user` (depuis la session) dans toutes les vues, évitant d'avoir à le repasser manuellement à chaque `view()` (corrige un oubli potentiel identifié pendant les tests).

## Vérifications exécutées (DoD Phase 2)
Toutes vérifiées en conditions réelles (HTTP via curl, avec remise à zéro de la base entre les scénarios destructifs) :
- Création filiale : code dupliqué → erreur de validation affichée, aucune insertion ; création valide → filiale visible immédiatement (donnée réelle, pas de cache).
- Édition + activation/désactivation d'une filiale : changements persistés et journalisés (`audit_logs` : `update`, `deactivate`, `activate`).
- Arbre de hiérarchie : NOVA Holding en racine, 6 filiales en enfants, badges méthode/pourcentage corrects.
- Transition de période : Décembre `in_progress → submitted` par l'administrateur ✅ ; tentative de saut direct vers `closed` (requête forgée) → rejetée avec message explicite, statut inchangé.
- Responsable consolidation : peut aussi transitionner une période (`submitted → under_review`) ✅.
- Taux de change : lecture/écriture pour une période ouverte ✅ ; période clôturée (janvier 2026) → champs en lecture seule côté UI **et** écriture forcée rejetée côté serveur, valeur inchangée.
- RBAC sur les nouvelles routes : préparateur → 403 sur `/subsidiaries` et `/exchange-rates` (200 sur `/periods`, lecture seule accordée à tous) ; DAF (lecture seule) → 200 sur `/subsidiaries` mais 403 sur `/subsidiaries/create`.

## Phase 3 — Réalisé
- Plan de comptes minimal (22 comptes IS/BS/CF) — voir `docs/CONSOLIDATION_LOGIC.md` pour le détail des formules, la convention de signe (montants toujours positifs sauf flux CF) et la justification de chaque simplification.
- `database/seed_financials.php` : générateur déterministe (idempotent, rejouable) des données réelles + budget 2026 pour les 6 filiales opérationnelles, avec une trajectoire cohérente par filiale (Sénégal moteur de profit, Côte d'Ivoire croissance rapide, Mali stable, France faible marge, Maroc en sous-performance budgétaire délibérée, Ghana mise en équivalence). CASH calculé comme valeur d'ajustement garantissant l'équation bilancielle par construction. Solde interco Sénégal/France de décembre pré-positionné pour le scénario de démonstration Phase 4.
- `ValidationService` : équation bilancielle (bloquant), champs obligatoires + type + signe, anomalie de variation de revenu > 50 % (non bloquant).
- `CsvImportService` : import `account_code,amount`, lignes valides appliquées immédiatement, lignes invalides rapportées avec numéro de ligne sans bloquer les autres.
- Écrans : liste des périodes par filiale avec indicateur de complétude, formulaire de saisie IS/BS/CF (lecture seule pour tout rôle sauf Préparateur), import CSV avec rapport détaillé.
- Règle métier : seul le Préparateur saisit/modifie les données (le Contrôleur valide/rejette en Phase 4 sans ressaisir).

## Bug trouvé et corrigé pendant les tests Phase 3
`FinancialDataRepository::upsert()` utilisait le même paramètre nommé PDO (`:uid`) à deux endroits de la requête (`created_by`, `updated_by`). Avec `PDO::ATTR_EMULATE_PREPARES => false` (préparation native, configuré dès la Phase 1), MySQL ne supporte pas la réutilisation d'un paramètre nommé — erreur `SQLSTATE[HY093]: Invalid parameter number`, jamais déclenchée avant Phase 3 car aucune requête antérieure ne réutilisait de paramètre. Corrigé en utilisant `:uid_created`/`:uid_updated` distincts. Un audit du reste du code (agent dédié) n'a trouvé aucune autre occurrence. Leçon : tester les flux d'écriture avec des données réelles (pas seulement `php -l`) reste indispensable.

## Vérifications exécutées (DoD Phase 3)
Toutes vérifiées en conditions réelles (HTTP via curl, y compris upload multipart) :
- Saisie complète d'un paquet filiale (22 comptes) : équation bilancielle affichée comme équilibrée, résultat net recalculé exact (vérifié à la main pour NOVA-SN janvier : 55 115 000 XOF).
- Bilan volontairement déséquilibré (CASH modifié) → sauvegarde bloquée avec message explicite (écart chiffré, actif vs passif+capitaux propres+résultat net), aucune écriture en base.
- Variation de revenu > 50 % vs mois précédent → avertissement nonbloquant affiché, sauvegarde acceptée (202 % de variation testé, message correct).
- Import CSV : 3 lignes valides importées immédiatement, 1 compte inconnu et 1 montant négatif non autorisé rejetés avec numéro de ligne exact.
- RBAC : Contrôleur voit la saisie en lecture seule (aucun champ éditable) et reçoit 403 sur toute tentative d'écriture ; Préparateur d'une filiale reçoit 403 sur les données d'une autre filiale.
- Période clôturée (janvier) : toute tentative d'écriture redirige avec message "période clôturée", sans jamais atteindre la validation.

## Phase 4 — Réalisé
- `WorkflowService` : statut d'un paquet filiale/période déduit de la dernière ligne de `workflow_transitions` (pas de colonne dupliquée). Séquence stricte `draft → submitted → validated` ou `submitted → rejected → submitted → ...`. La soumission ré-exécute `ValidationService` (impossible de soumettre incomplet/déséquilibré). Anti-doublon : un paquet déjà `submitted`/`validated` ne peut pas être resoumis.
- Saisie (formulaire + CSV) verrouillée dès que le statut quitte `draft`/`rejected` — vérifié côté serveur, pas seulement masqué côté UI.
- Écran de saisie enrichi : bannière de statut, motif de rejet visible, actions Valider/Rejeter (Contrôleur, sur paquet `submitted`), action Soumettre (Préparateur, sur paquet complet et équilibré), historique complet des transitions (utilisateur, date, commentaire).
- `IntercompanyService` : déclaration à sens unique par filiale, recherche automatique de la contrepartie (receivable↔payable, revenue↔expense), conversion FX (taux de clôture pour les soldes de bilan, taux moyen pour les flux), calcul de l'écart exact, notification des deux contrôleurs + responsable consolidation en cas d'écart. Dividendes traités comme déclaration unilatérale (voir décision ci-dessous).
- Notifications créées dès l'évènement (soumission, rejet, mismatch) — le centre de notifications (badge, liste) arrive en Phase 7, mais les lignes existent déjà en base.
- `database/seed_workflow.php` et `database/seed_intercompany.php` : positionnent l'état de départ du scénario de démonstration (5/6 filiales soumises, Maroc en attente ; mismatch Sénégal/France déclaré) en réutilisant les services applicatifs réels (pas de statut écrit à la main).

## Décision : adaptation du motif de rejet du scénario de démonstration
Le cahier des charges illustre le rejet du Maroc par un "déséquilibre bilanciel". Or la Phase 3 bloque déjà toute sauvegarde déséquilibrée à la source (`ValidationService`), et la Phase 4 revalide à la soumission : un paquet déséquilibré ne peut structurellement jamais atteindre le statut `submitted`. Plutôt que d'affaiblir ce contrôle (régression sur une exigence explicite), le motif de rejet démontré est **l'écart de marge vs budget** — cohérent avec la vraie trame narrative du Maroc (sous-performance budgétaire, cf. Phase 3) et tout aussi représentatif d'un rejet par jugement métier du contrôleur. Le cycle complet (Soumettre → Rejeter avec motif → Corriger → Resoumettre → Valider) a été vérifié en conditions réelles de bout en bout.

## Vérifications exécutées (DoD Phase 4)
Toutes vérifiées en conditions réelles (HTTP via curl), avec remise à zéro de la base après tests :
- Cycle complet Maroc : `draft → submitted → rejected → submitted → validated`, chaque transition journalisée dans `workflow_transitions` **et** `audit_logs` avec utilisateur/date/commentaire exacts.
- Package rejeté : préparateur voit le motif exact + formulaire de nouveau éditable ; notification créée pour le préparateur.
- Anti-doublon : tentative de resoumission d'un paquet déjà validé → bloquée avec message explicite, aucune nouvelle transition créée.
- Mismatch intercompany Sénégal (100M XOF) / France (~95M XOF) : écart de 5 000 000 XOF calculé automatiquement et identique des deux côtés ; notifications envoyées aux deux contrôleurs + responsable consolidation.
- Isolation : préparateur Mali ne voit aucune déclaration intercompany impliquant Sénégal/France ; responsable consolidation (rôle groupe) voit toutes les déclarations de la période.
- 6/6 filiales validées pour décembre 2026 — prêt pour le run de consolidation (Phase 5).
- Bug de tolérance d'arrondi corrigé pendant les tests : `ValidationService` comparait à 0,01 XOF près, trop strict face à l'arrondi indépendant de 9 comptes stockés en `DECIMAL(18,2)` (écarts résiduels observés jusqu'à 0,01 XOF sur des montants de centaines de millions). Tolérance portée à 1 XOF, documentée dans `ValidationService`.

## Phase 5 — Réalisé
- Nouvelle table `consolidation_line_items` (résultat consolidé par compte et par run) + 2 comptes calculés (`EQ_METHOD_INCOME`, `EQ_METHOD_INVESTMENT`) exclus de la saisie filiale (`AccountRepository::enterable()`).
- `CurrencyConversionService` : taux moyen pour l'IS, taux de clôture pour le bilan.
- `ConsolidationService` : pipeline en 7 étapes tracées (périmètre → conversion/agrégation → éliminations intercos → élimination dividendes → mise en équivalence → ajustements → intérêts minoritaires), chaque étape journalisée dans `consolidation_run_steps`.
- Seules les paires intercompany `matched` sont éliminées automatiquement ; les `mismatch` restent en l'état et sont signalées (résolution via ajustement manuel — relie directement Phase 4 et Phase 5).
- Écran de détail d'un run : compte de résultat consolidé et bilan consolidé hand-vérifiables, répartition part du groupe / part des minoritaires, taux utilisés, détail des éliminations et intérêts minoritaires.
- Ajustements de consolidation manuels (`consolidation_adjustments`), appliqués selon `normal_balance` du compte, pleinement audités.

## Bug trouvé et corrigé pendant les tests Phase 5 : écart de conversion devises
Premier run réel : le bilan consolidé ne s'équilibrait pas (écart de ~8 200 XOF). Cause : le cahier des charges impose taux moyen pour l'IS et taux de clôture pour le bilan (§5) — pour une filiale en devise étrangère, cela crée mécaniquement un écart de conversion entre le résultat net (traduit au taux moyen) et les capitaux propres du bilan (traduits au taux de clôture), comme dans tout référentiel multi-devises. **Correctif :** les capitaux propres de chaque filiale (pour le calcul des intérêts minoritaires et de la part groupe) sont désormais dérivés directement de `Actif − Passif traduits` plutôt que de `Capital + Réserves + Résultat net` — absorbe l'écart automatiquement, garantit l'équilibre au centime près. Documenté dans `docs/CONSOLIDATION_LOGIC.md`. Trouvé uniquement parce que le run a été vérifié à la main (Actif vs Passif+CP) plutôt que simplement "ça s'affiche sans erreur".

## Vérifications exécutées (DoD Phase 5)
Toutes vérifiées en conditions réelles sur les données décembre 2026 (6/6 validées) :
- Run complet : bilan consolidé équilibré exactement (3 084 176 188,74 XOF des deux côtés) après correction de l'écart de conversion.
- Intérêt minoritaire NOVA Côte d'Ivoire (75 % détenue → 25 % minoritaire) : résultat net recalculé à la main (26 440 654,31 XOF) × 25 % = 6 610 163,58 XOF, valeur exacte produite par le run.
- Taux de change affichés (EUR/MAD/GHS, moyen + clôture) sur l'écran de détail du run.
- Ajustement manuel (retraitement de l'écart interco Sénégal/France, -5 000 000 XOF sur `IC_RECEIVABLE`) : relance du run → nouveau total actif exactement réduit de 5 000 000 XOF, bilan toujours équilibré, écriture dans `audit_logs`.
- Run bloqué avec message explicite tant qu'une filiale du périmètre n'est pas `validated` (testé avant validation du Maroc).
- RBAC : Préparateur → 403 sur `/consolidation` ; DAF (lecture seule) → 200 en lecture, 403 sur le lancement d'un run.

## Phase 6 — Réalisé
- `ReportingService` : KPIs (CA/EBITDA/résultat net) Actual vs Budget, tendance 12 mois, contribution EBITDA par filiale, alertes — vision "cumulée" (hors éliminations/mise en équivalence), distincte du résultat consolidé officiel (voir `docs/CONSOLIDATION_LOGIC.md` §Dashboards).
- `BudgetVarianceService` : écart et écart % avec sens favorable/défavorable correct selon la nature du compte (produit vs charge).
- Dashboard CODIR (`/dashboard`, rôles groupe) : filtres période/pays/filiale, tuiles KPI avec écart vs budget, graphique de tendance (courbes SVG, survol avec réticule + infobulle), graphique de contribution par filiale (barres horizontales), panneau d'alertes (soumissions manquantes, écarts importants, mismatch interco, "prêt à consolider" avec raccourci direct).
- Dashboard filiale : même écran, automatiquement restreint à la filiale du Préparateur/Contrôleur connecté.
- Écran dédié **Budget vs Actual** (`/budgets`) : tableau par compte, mois + cumul YTD, Actual/Budget/Écart/Écart %.
- Composants graphiques SVG maison (`app/helpers/charts.php`) suivant la méthodologie de dataviz interne : palette catégorielle validée CVD-safe (script `validate_palette.js`), traits 2px, marqueurs avec anneau de surface, légende systématique, libellés directs en bout de série, grille discrète, survol avec réticule + infobulle.

## Bug trouvé et corrigé pendant les tests Phase 6 : mélange de devises
Les premiers KPIs groupe sommaient `financial_data`/`budgets` de plusieurs filiales sans convertir les devises locales en XOF — un mélange silencieux XOF+EUR+MAD qui sous-pondérait fortement la France et le Maroc (ex. filtrer sur le Maroc seul affichait "2,7 M XOF" alors que le vrai montant était 2,7 M **MAD**, soit 176,3 M XOF une fois converti). Corrigé : `ReportingService` convertit chaque filiale en XOF (taux moyen) avant toute somme multi-filiale. Trouvé en comparant les totaux du dashboard à ceux du run de consolidation Phase 5 (qui, eux, convertissaient déjà correctement) — les deux devraient être proches et ne l'étaient pas.

## Vérifications exécutées (DoD Phase 6)
Toutes vérifiées en conditions réelles (HTTP via curl) :
- KPIs traçables en base : EBITDA cumulé décembre (198,8 M XOF) cohérent avec l'EBITDA du run de consolidation Phase 5 (198 750 149,75 XOF) — même périmètre, écart résiduel attendu (mismatch interco non éliminé dans la vision cumulée).
- Conversion devises vérifiée à la main : Maroc décembre 2 687 098,65 MAD × 65,60 (taux moyen) = 176 273 671,86 XOF, valeur exacte affichée après correction.
- Changement de filtre (période/filiale/pays) recalcule tous les widgets (KPIs, tendance, contribution) — testé en isolant la France (conversion EUR vérifiée à la main également) et le Maroc.
- Alertes reflètent l'état réel : "6 paquets non validés" avant toute validation ; disparition progressive au fil des validations ; alerte "prêt à consolider" apparue une fois les 6 filiales validées puis disparue après le run — cycle complet vérifié en direct.
- RBAC : rôles filiale ne voient ni le sélecteur filiale/pays, ni le panneau d'alertes, ni le graphique de contribution (vue restreinte à leur propre filiale, sans possibilité de la contourner via paramètre d'URL — contrôlé côté serveur, pas seulement masqué côté UI).

## Ajustements UI (hors phase, sur retour utilisateur)
- Écran de connexion refondu : layout deux colonnes (identité de groupe sur fond graphite avec trame subtile + empreinte géographique du groupe / formulaire épuré sans carte superflue), cohérent avec la direction "salle de contrôle financière" (§7). Voir `views/auth/login.php` et la section "Écran de connexion" de `public/assets/css/app.css`.

## Ajustement UX/OHADA post-Phase 6 (retour utilisateur détaillé)
- **Palette orange + vert** : nouvelle identité de marque (`--color-primary` orange brûlé, `--color-secondary` vert) appliquée aux boutons, liens, navigation active, focus, écran de connexion — structure graphite réchauffée (tons chauds au lieu de gris froids) pour rester cohérente. Couleurs sémantiques (positif/négatif/alerte/info) conservées distinctes de la marque.
- **Filtres dynamiques (AJAX)** : les formulaires de filtre (dashboard, Budget vs Actual, taux de change, intercompany, ajustements) ne rechargent plus toute la page — `Request::isAjax()` détecte l'en-tête `X-Requested-With`, le contrôleur rend la vue sans layout, et une couche JS générique (`views/layouts/main.php`) échange le fragment `#ajax-content` en `fetch()` avec repli complet si JS est désactivé et gestion correcte du bouton retour (`history.pushState`/`popstate`).
- **États financiers au format OHADA/SYCEBNL** : nouvel écran filiale (`/financial-data/{id}/{periodId}/statement`) et section dépliable sur le run de consolidation, présentant compte de résultat et bilan avec codes REF et soldes intermédiaires de gestion — voir `docs/CONSOLIDATION_LOGIC.md` pour le mapping complet et un bug d'équilibrage corrigé pendant l'implémentation (écart de conversion / titres mis en équivalence).
- Transitions/micro-interactions ajoutées (boutons, liens, lignes de tableau, champs) pour une sensation plus réactive, cohérent avec la demande explicite "plateforme dynamique, pas statique".
- Reporté (hors scope de cet ajustement, à traiter plus tard si besoin) : plus de visuels sur les écrans hors dashboard (fiche filiale, workflow), relooking approfondi au-delà de la palette.

## Phase 7 — Réalisé
- Table `audit_logs` étendue avec `subsidiary_id`/`period_id` (FK `ON DELETE SET NULL` + index) : les actions journalisées (workflow, intercompany, filiales, périodes, taux, ajustements, runs) n'étaient rattachables qu'à `entity_type`/`entity_id`, hétérogènes selon l'action — insuffisant pour un filtre fiable par filiale/période dans le visualiseur d'audit. `AuditService::logChange()`/`AuditLogRepository::log()` acceptent désormais ces deux colonnes en paramètres optionnels ; tous les points d'appel (Workflow, Intercompany, Subsidiary, Period, ExchangeRate, Consolidation) les renseignent.
- Centre de notifications : `NotificationRepository` (compteur non-lues, liste, marquer lu/tout lire), écran `/notifications`, badge cloche dans la topbar (mis à jour à chaque chargement de page, aucune valeur en dur). Nouvel évènement `consolidation_ready` créé à la fin d'un run réussi, adressé aux rôles Administrateur groupe / Responsable consolidation / DAF (lecture seule).
- Visualiseur du journal d'audit (`/audit`, rôles groupe uniquement) : filtres utilisateur/filiale/période combinables, rendu AJAX (`data-ajax-filter`, cohérent avec le reste de la plateforme), affichage ancienne/nouvelle valeur par entrée.
- Exports CSV (`ExportController`) : run de consolidation (`/exports/consolidation/{id}`), paquet filiale (`/exports/financial-data/{subsidiaryId}/{periodId}`, protégé par `subsidiaryScope`), vue dashboard courante (`/exports/dashboard`, respecte les filtres période/filiale/pays actifs). Format Excel FR : séparateur `;`, BOM UTF-8 (`stream_csv_download()` dans `app/helpers/helpers.php`) — CSV retenu plutôt que XLSX (pas de dépendance Composer/PhpSpreadsheet, cohérent avec la contrainte "zéro dépendance" du cahier des charges ; un tableur FR ouvre un CSV `;`+BOM sans réglage manuel).
- Boutons d'export ajoutés aux écrans concernés (détail run de consolidation, saisie filiale, dashboard) sans dupliquer la logique de filtrage déjà présente dans chaque contrôleur.

## Vérifications exécutées (DoD Phase 7)
Toutes vérifiées en conditions réelles (HTTP via curl) :
- Notifications : soumission Sénégal + mismatch interco → 2 notifications non lues pour le contrôleur concerné, badge topbar à jour ; "Tout marquer comme lu" → badge à 0, entrées grisées.
- Run de consolidation complet → notification `consolidation_ready` reçue par les 3 rôles groupe (Administrateur, Responsable consolidation, DAF lecture seule), aucune pour les rôles filiale.
- Audit : filtre par filiale (Sénégal, id=2) → uniquement les entrées liées à cette filiale (2 résultats sur le jeu de test) ; accès `/audit` par un Préparateur/Contrôleur → 403.
- Export dashboard (`/exports/dashboard?period_id=12`) : CSV `;`+BOM, valeurs identiques au dashboard affiché (EBITDA 198 750 149,75 XOF).
- Export paquet filiale (`/exports/financial-data/2/12`) : 22 comptes, codes/libellés/montants exacts.
- Export run de consolidation (`/exports/consolidation/1`) : lignes conformes à `consolidation_line_items`, libellés de comptes corrects.
- Base de démonstration remise à l'état de départ après les tests (5/6 filiales soumises, Maroc en brouillon, mismatch Sénégal/France déclaré, aucun run de consolidation) via rejeu complet `schema.sql` + `seed.sql` + les 3 scripts `seed_*.php`.
- `php -l` sur l'intégralité de `app/ public/ views/ database/` : aucune erreur de syntaxe.

## Phase 8 — Réalisé (en cours)

### Ajout de périmètre demandé par l'utilisateur (2026-08-09) : liasse groupe + dashboard CODIR enrichi
- **Liasse groupe** (`/financial-statements`, nav "Liasse groupe", rôles groupe) : compte de résultat + bilan
  consolidés au format OHADA pour le dernier run terminé d'une période choisie (sélecteur AJAX), export CSV
  (réutilise `/exports/consolidation/{id}`) et export PDF (impression navigateur).
- **`ConsolidationService::computeSummary()`** : calcul du résumé consolidé (résultat net groupe/minoritaires,
  bilan groupe/minoritaires) extrait de `ConsolidationController::show()` vers le service, pour être partagé
  sans duplication entre le détail d'un run, la liasse groupe et le nouveau panneau bilan du dashboard.
- **Dashboard CODIR enrichi** : marges (EBITDA %, nette %), panneau "Situation bilancielle groupe" (dernier run
  terminé de la période, ratio d'endettement — absent si aucun run n'existe, jamais de valeur factice),
  tableau de classement complet des filiales (`ReportingService::subsidiaryScorecard()` : CA/EBITDA/écarts
  budget/marge/résultat net par filiale, au-delà du seul graphique de contribution déjà existant).
- **Export PDF** (dashboard + liasse groupe) : bouton "Exporter en PDF" déclenchant `window.print()` + feuille
  `@media print` dédiée (masque nav/filtres, en-tête document, pas de coupure de page au milieu d'un panneau) —
  zéro dépendance serveur, cohérent avec le choix CSV vs XLSX de la Phase 7.
- Voir `docs/CONSOLIDATION_LOGIC.md` §"Liasse groupe et dashboard CODIR enrichi" pour le détail et un bug
  corrigé pendant l'implémentation (`period_label` absent de `latestCompletedForPeriod()`).

### Suite de tests (`tests/`)
Micro-framework maison sans dépendance (`tests/framework.php` : `TestRunner::test()`, `assert_equal()`,
`assert_float_equal()`, `assert_true()`, `assert_null()`), lancé via `php tests/run.php`. 24 tests couvrant la
logique de calcul pure : `ValidationService` (équation bilancielle, tolérance d'arrondi, anomalies, signes),
`BudgetVarianceService` (sens favorable/défavorable produit vs charge), `CurrencyConversionService` (taux
moyen vs clôture, taux manquant), `app/helpers/ohada.php` (soldes intermédiaires de gestion, plug de l'écart
de conversion côté BU/DV — regression test direct du bug XI≠CJ corrigé plus tôt). Workflow/RBAC/pipeline de
consolidation restent couverts par le protocole curl manuel (voir DoD de chaque phase ci-dessus) — ce sont des
comportements dépendant de l'état en base et de l'authentification, hors périmètre naturel d'un test unitaire
rapide sans dépendance de test (pas de PHPUnit).

### Documentation livrée
`README.md` (installation, stack, réutilisation), `USER_MANUAL.md` (guide par rôle, comptes de démonstration,
tous les écrans), `TECHNICAL_DOCUMENTATION.md` (architecture, sécurité, modèle de données, points d'extension,
déploiement) — les trois en français. `docs/CONSOLIDATION_LOGIC.md` relu et complété au fil de chaque phase,
à jour.

## Vérifications exécutées (DoD Phase 8)
- `php tests/run.php` : 24/24 tests réussis, code de sortie 0.
- `php -l` sur l'intégralité de `app/ public/ views/ database/ tests/` : aucune erreur de syntaxe.
- Liasse groupe et panneau bilan du dashboard vérifiés avec un run de consolidation réel (période décembre
  2026) : résultat net consolidé et total bilan affichés identiques aux valeurs vérifiées à la main en Phase 5
  (3 084 176 188,74 XOF de bilan). RBAC vérifié : 403 pour un Préparateur, 200 pour le DAF (lecture seule).
- Marge EBITDA affichée (14,6 %) vérifiée à la main : 198 750 149,75 / 1 363 651 060,27 XOF.
- Export PDF (dashboard + liasse groupe) vérifié visuellement : navigation masquée, en-tête document présent,
  panneaux non coupés au milieu par un saut de page.
- Base de démonstration remise à l'état de départ après tous les tests (5/6 filiales soumises, Maroc en
  brouillon, mismatch Sénégal/France déclaré, aucun run de consolidation).
- Dépôt Git initialisé et poussé sur `https://github.com/mbkdjibril8-spec/CONSO_CLASS_PROJECT` (branche `main`).

## Ajustement post-Phase 8 (retour utilisateur détaillé, 2026-08-09)

### Renommage GROUPFIN → OHADA_CONSO+
Décision utilisateur : renommer uniquement le **nom affiché** (UI, `<title>`, README, docs, `config.app.name`)
— le dossier local (`C:\xampp\htdocs\groupfin`), le nom de la base de données (`groupfin`), le cookie de
session (`groupfin_session`) et le dépôt GitHub (`CONSO_CLASS_PROJECT`) restent inchangés pour ne pas casser
l'installation XAMPP ni le lien GitHub existant. Deux commentaires de code ("plan de comptes GROUPFIN") ont été
reformulés en "plan de comptes interne" plutôt que remplacés littéralement par le nouveau nom, pour éviter la
confusion visuelle "plan de comptes OHADA_CONSO+" juxtaposé à "format OHADA/SYCEBNL" dans la même phrase.

### Correctif UX : asymétrie des cartes KPI
Cause : `.kpi { flex: 1 1 160px }` sans `max-width` faisait grandir les cartes pour remplir toute la largeur de
leur rangée — une rangée à 2 cartes (ex. marges EBITDA/nette) produisait des cartes ~2x plus larges qu'une
rangée à 4 cartes (ex. situation bilancielle), rendant le tableau de bord visuellement incohérent d'une rangée
à l'autre. Corrigé : `max-width: 260px` fixe une taille de carte constante quel que soit le nombre de cartes
par rangée. Deuxième cause, plus subtile : certaines cartes ont une 3ᵉ ligne (`.kpi-sub`, écart vs budget) et
d'autres non — `flex-direction: column` + `margin-top: auto` sur `.kpi-sub` ancre systématiquement le libellé et
la valeur en haut de la carte, pour qu'une carte sans écart ne paraisse pas juste "tronquée" à côté d'une carte
qui en affiche un.

### Donut de répartition dynamique (dashboard)
Demande initiale : remplacer le graphique de tendance (courbes, 12 mois) par un "disk chart". Clarifié avec
l'utilisateur : un donut ne peut pas représenter une évolution dans le temps (il montre une répartition à un
instant donné) — décision retenue : **garder** la courbe de tendance et **ajouter** un donut de répartition
CA/EBITDA par filiale pour la période sélectionnée, avec bascule dynamique entre les deux indicateurs (boutons,
aucun rechargement de page). Réutilise directement `ReportingService::subsidiaryScorecard()` (même source que
le tableau "Performance par filiale" — un seul calcul, jamais deux résultats potentiellement divergents pour la
même donnée). Les valeurs négatives (filiale en perte sur l'indicateur affiché) ne sont pas représentables dans
un donut (pas de part négative d'un tout) : exclues du tracé, listées séparément dans la légende avec la
mention "non représenté" plutôt que silencieusement ignorées.

### Arbre de hiérarchie dynamique
L'ancienne vue (`/subsidiaries/tree`) était une liste à puces imbriquée avec des bordures gauche simulant un
arbre. Remplacée par un org-chart visuel (boîtes reliées par des traits, motif CSS "family tree" classique :
connecteurs en pseudo-éléments sur des `<li>` en flexbox, se redispose automatiquement à n'importe quelle
profondeur/largeur sans recalcul JS) avec repli/dépli par filiale (clic sur le bouton rond sous chaque nœud,
état purement client — pas persisté, pas nécessaire pour un usage de consultation). Même donnée qu'avant
(`SubsidiaryService::tree()`), seule la présentation change.

### Icônes de navigation animées
Chaque lien de la barre latérale a désormais une icône SVG dédiée (`nav_icon()`, `app/helpers/helpers.php`) —
traits simples, `currentColor`, aucune police d'icônes/dépendance externe. Au survol et à l'état actif : icône
qui change de couleur/s'agrandit légèrement, retrait du lien qui glisse, petit point d'accent animé sur l'item
actif. `prefers-reduced-motion` respecté (animations désactivées).

### Correctif : cache navigateur sur les assets statiques
Symptôme rapporté : "les icônes n'ont pas changé". Cause réelle : `app.css` était bien à jour côté serveur
(vérifié), mais rien n'invalidait le cache du navigateur sur ce fichier statique. Corrigé une fois pour toutes :
`asset()` (`app/helpers/helpers.php`) ajoute désormais `?v=<filemtime>` à l'URL de chaque asset — un simple F5
suffit maintenant à voir tout changement CSS/JS, plus besoin de vider le cache manuellement.

## Ajustement UX dashboard (retour utilisateur détaillé, 2026-08-12)

Référence visuelle fournie par l'utilisateur (capture d'un dashboard tiers) : tuiles KPI pleines couleur toutes
identiques en taille, donuts avec libellés reliés par un trait (callout) plutôt qu'une légende séparée,
graphiques alignés en rangée. Objectif explicite : "tout doit être symétrique".

### Tuiles KPI "héro"
Les 5 indicateurs principaux (CA, EBITDA, résultat net, marge EBITDA, marge nette) — auparavant répartis sur
deux `<div class="kpi-row">` distinctes avec des cartes bordées neutres — sont regroupés en une seule rangée
`.kpi-hero-row` : fond plein (nuances de la palette orange/vert/graphite, pas 5 couleurs sémantiques
différentes), `display:grid` avec colonnes égales (`repeat(auto-fit, minmax(150px,1fr))`) plutôt que flex, pour
que les 5 tuiles se partagent exactement la largeur du panneau quel que soit le nombre affiché (ex. seulement 3
si la marge n'est pas calculable faute de CA). Le style `.kpi` (bordé, neutre) existant est conservé tel quel
pour les indicateurs secondaires (situation bilancielle, filiales/utilisateurs) — hiérarchie visuelle
volontaire entre KPIs "héros" et informations complémentaires, pas une recoloration de tout l'écran.

### Donut à labels reliés (callout), sans légende séparée
Réécriture de `render_composition_donut()` : chaque part du donut est désormais reliée par un trait coudé
(polyline SVG) à un libellé placé à l'extérieur du cercle (code filiale + pourcentage), aligné en deux colonnes
gauche/droite selon la position angulaire de la part — plus de légende séparée sous le graphique. Un algorithme
simple d'anti-chevauchement (`spreadLabels()`, tri par position verticale + écart minimal forcé) évite que deux
libellés proches en angle se superposent, traité indépendamment pour chaque côté. Toujours dynamique (bascule
CA/EBITDA sans rechargement, survol avec infobulle) — seule la présentation des libellés change.

### Symétrie des panneaux graphiques
Le donut de répartition et le graphique de contribution EBITDA (auparavant deux panneaux pleine largeur
empilés) sont désormais côte à côte dans une rangée `.panel-row` (`display:grid`, colonnes égales, hauteur
étirée à l'identique) — repasse à un empilement vertical sur écran étroit (`auto-fit`, pas de media query
dédiée nécessaire). La courbe de tendance (12 mois) reste pleine largeur au-dessus : compressée à la moitié de
la largeur, une série temporelle devient illisible, contrairement à un donut ou un graphique en barres qui
restent lisibles à taille réduite.

## Export CSV de la liasse groupe (2026-08-12)

Constat utilisateur : le bouton d'export de la page Liasse groupe existait déjà mais exportait les montants
bruts par compte interne, pas la présentation OHADA (REF, soldes intermédiaires) affichée à l'écran — un
utilisateur ouvrant l'export ne retrouvait pas ce qu'il voyait sur la page. Ajouté
`ExportController::financialStatements()` (`/exports/financial-statements/{runId}`, rôles groupe) : exporte le
compte de résultat + bilan actif + bilan passif **exactement comme affichés**, en 3 sections dans un seul CSV.
Les définitions de lignes OHADA ont été extraites de `render_ohada_*()` vers des fonctions dédiées
(`ohada_income_statement_rows()`, `ohada_balance_sheet_actif_rows()`, `ohada_balance_sheet_passif_rows()` dans
`app/helpers/ohada.php`), réutilisées à la fois par l'affichage écran et par l'export — un seul endroit définit
la structure, impossible que l'export diverge de l'écran. Le bouton de la page Liasse groupe pointe désormais
vers ce nouvel export ; l'export brut par compte (`consolidationRun()`) reste inchangé sur l'écran technique
"Détail du run" où il a plus de sens (audit ligne par ligne). Voir `docs/CONSOLIDATION_LOGIC.md` pour le détail.

## Bascule automatique d'exercice (2026-08-12)

Question directe de l'utilisateur : "as-tu géré les années ?" — vérification faite, la réponse était non :
aucun code nulle part ne créait l'année suivante, seul 2026 était seedé. Construit avec deux décisions
utilisateur : (1) déclenchement **automatique** dès que les 12 mois d'une année sont tous `closed` (pas un
bouton manuel) ; (2) taux de change de la nouvelle année **pré-remplis** avec les derniers taux connus
(décembre de l'année qui se termine), pour ne pas bloquer la première saisie filiale en devise étrangère.

Implémenté dans `PeriodService::advance()` → `maybeOpenNextFiscalYear()` (déclenché après toute transition
vers `closed`, vérifie par comptage que les 12 mois de l'année le sont — pas seulement "décembre vient de se
clôturer", puisque rien n'impose de clôturer les mois dans l'ordre calendaire dans ce modèle). Garde
d'idempotence pour ne jamais recréer l'année suivante deux fois. Notification `fiscal_year_opened` aux rôles
groupe, entrée `audit_logs` dédiée. Budgets et données financières volontairement non recopiés (nouvel
exercice = nouvelles données ; les budgets relèvent d'un processus de préparation distinct). Voir
`docs/CONSOLIDATION_LOGIC.md` §"Bascule automatique d'exercice" pour le détail complet et les vérifications.

Nouveaux tests unitaires (`tests/ReportingPeriodTest.php`, 4 tests, logique pure) : format du libellé
`ReportingPeriod::labelFor()` (zéro-padding du mois, y compris à la bascule d'année), enchaînement complet de
`nextStatus()`. La création effective (DB, taux, notification) a été vérifiée manuellement en conditions
réelles (12 clôtures via l'endpoint HTTP réel, pas un bypass) — voir DoD ci-dessous.

## Vérifications exécutées (DoD — bascule d'exercice)
- 24 → 28 tests unitaires, tous réussis (`php tests/run.php`).
- Clôture réelle des 12 mois de 2026 via `/periods/{id}/transition` (endpoint HTTP réel, authentifié
  Administrateur groupe) : les 12 périodes 2027 apparaissent automatiquement (statut `open`), label correct
  (`2027-01` … `2027-12`).
- Taux de change 2027 vérifiés pour EUR/MAD/GHS, moyen ET clôture : identiques à décembre 2026 sur les 12 mois
  (y compris la distinction moyen ≠ clôture pour MAD et GHS, préservée).
- Entrée `audit_logs` (`fiscal_year_opened`) et notification reçue par les 3 comptes de rôle groupe (Admin,
  Responsable consolidation, DAF lecture seule), vérifiés en base et via l'écran `/notifications`.
- Dashboard testé sur une période 2027 (`period_id` de janvier 2027) : aucune erreur, écran vide comme attendu
  (aucune donnée financière encore saisie pour ce nouvel exercice).
- Base de démonstration remise à l'état de départ après test (2026 seul, statuts d'origine, aucun run).

## Guide de test complet (2026-08-13)

`GUIDE_DE_TEST.md` (nouveau, racine du dépôt) : parcours de test séquentiel, en français, de la connexion
jusqu'aux états financiers consolidés et au reporting annuel — connexion → structure de groupe → périodes →
taux de change → saisie + workflow (cycle complet soumission/rejet/resoumission/validation sur le Maroc,
volontairement laissé en brouillon dans le scénario de démo) → validation des 5 autres filiales → intercompany
→ lancement de la consolidation → liasse groupe (états financiers OHADA + exports CSV/PDF) → dashboard CODIR →
Budget vs Actual → notifications → journal d'audit → bascule d'exercice (optionnel, approfondi). Chaque étape
a été rejouée manuellement via HTTP réel avant livraison (pas rédigée puis supposée correcte) — un bug de
méthodologie de test a été trouvé et corrigé au passage : un jeton CSRF périmé provoquait un faux échec sur la
soumission d'un paquet dans mon script de vérification (rien à voir avec l'application elle-même, qui
fonctionne normalement dans un navigateur où chaque page chargée a son propre jeton frais).

Distinct de `USER_MANUAL.md` (référence exhaustive écran par écran) : ce guide est un script à dérouler dans
l'ordre, avec des cases à cocher, pas une documentation de référence.

## Décisions clés
- **NOVA Holding exclue du périmètre bottom-up (`consolidation_method = 'excluded'`)** : la tête de groupe porte l'arbre de hiérarchie mais ne soumet pas de paquet financier propre en V1 — le scénario de démonstration du cahier des charges (§9) compte explicitement "6/6" filiales, pas 7. Documenté également dans `docs/CONSOLIDATION_LOGIC.md` (Phase 5).
- Répertoires `app/controllers`, `app/models`, etc. restent en minuscules (conformes à l'arborescence du cahier des charges) ; les classes utilisent des namespaces `App\Controllers`, `App\Models`... en PascalCase — l'autoloader fait la conversion de casse.
- UI entièrement en français (cohérent avec README/manuel utilisateur en français et le contexte du groupe ouest-africain).
- Table `accounts` (plan de comptes) créée par le schéma mais volontairement vide à ce stade : sera peuplée en Phase 3 avec la conception des formulaires de saisie IS/BS/CF.
- **Les 12 périodes 2026 démarrent `in_progress` plutôt que janvier-novembre `closed`** (retour utilisateur du 2026-08-09) : permet de tester le cycle de workflow complet (saisie → soumission → validation) sur n'importe quel mois de démonstration, pas seulement décembre. Le cycle de vie des périodes lui-même (Phase 2, `PeriodService`, transitions séquentielles strictes) n'est pas modifié — reste testable via l'écran Périodes.

## Cuts / V2 backlog
- Hors-scope V1 déjà exclu dès la conception du schéma : pas de consolidation proportionnelle, pas de dimensions analytiques, pas de taux de change historiques au-delà moyen/clôture.
- **Productisation / revente à d'autres entreprises (discuté 2026-08-08, décision utilisateur : traiter après la fin du V1)** : l'architecture est déjà single-tenant/réutilisable (une base = un groupe), mais deux choses restent codées en dur pour NOVA AFRICA GROUP : (1) le nom du groupe dans `views/layouts/main.php` et `views/auth/login.php` (à sortir vers `config.php`, ~30 min) ; (2) le plan de comptes est référencé par code (`REV`, `COGS`...) dans `ValidationService` — un nouveau client peut renommer les libellés mais pas changer la structure sans toucher au code. Modèle retenu pour la revente : une installation (base + config) par client, pas de multi-tenant (rejeté : chantier de plusieurs semaines, risque sécurité de fuite de données entre clients pour un bénéfice non demandé).
- **UX "dynamisme" approfondi (demandé 2026-08-09, débloqué maintenant que la Phase 8 est terminée)** : boutons "retour à l'onglet/page précédente" et autres micro-interactions de navigation au-delà des filtres AJAX déjà livrés (dashboard, Budget vs Actual, taux de change, intercompany, ajustements, liasse groupe). L'utilisateur avait explicitement choisi de terminer le cahier des charges (Phases 7-8) avant d'y revenir — plus aucun blocage, mais reste à traiter sur demande explicite plutôt qu'unilatéralement.

## Prochaines étapes
Les 8 phases du cahier des charges sont terminées et vérifiées. Il n'y a plus de prochaine étape imposée par
le cahier des charges initial — la suite dépend des priorités de l'utilisateur parmi le backlog V2 ci-dessus
(productisation multi-client, dynamisme approfondi) ou de nouvelles demandes fonctionnelles.
