# PROJECT_STATE — GROUPFIN

## Phase courante
**Phase 6 — Budget vs Actual + dashboards : TERMINÉE ✅**
**Ajustement UX/OHADA post-Phase 6 : TERMINÉ ✅** (voir section dédiée ci-dessous)
Prochaine étape : Phase 7 — Notifications, audit, exports.

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

## Décisions clés
- **NOVA Holding exclue du périmètre bottom-up (`consolidation_method = 'excluded'`)** : la tête de groupe porte l'arbre de hiérarchie mais ne soumet pas de paquet financier propre en V1 — le scénario de démonstration du cahier des charges (§9) compte explicitement "6/6" filiales, pas 7. Documenté également dans `docs/CONSOLIDATION_LOGIC.md` (Phase 5).
- Répertoires `app/controllers`, `app/models`, etc. restent en minuscules (conformes à l'arborescence du cahier des charges) ; les classes utilisent des namespaces `App\Controllers`, `App\Models`... en PascalCase — l'autoloader fait la conversion de casse.
- UI entièrement en français (cohérent avec README/manuel utilisateur en français et le contexte du groupe ouest-africain).
- Table `accounts` (plan de comptes) créée par le schéma mais volontairement vide à ce stade : sera peuplée en Phase 3 avec la conception des formulaires de saisie IS/BS/CF.

## Cuts / V2 backlog
- Hors-scope V1 déjà exclu dès la conception du schéma : pas de consolidation proportionnelle, pas de dimensions analytiques, pas de taux de change historiques au-delà moyen/clôture.
- **Productisation / revente à d'autres entreprises (discuté 2026-08-08, décision utilisateur : traiter après la fin du V1)** : l'architecture est déjà single-tenant/réutilisable (une base = un groupe), mais deux choses restent codées en dur pour NOVA AFRICA GROUP : (1) le nom du groupe dans `views/layouts/main.php` et `views/auth/login.php` (à sortir vers `config.php`, ~30 min) ; (2) le plan de comptes est référencé par code (`REV`, `COGS`...) dans `ValidationService` — un nouveau client peut renommer les libellés mais pas changer la structure sans toucher au code. Modèle retenu pour la revente : une installation (base + config) par client, pas de multi-tenant (rejeté : chantier de plusieurs semaines, risque sécurité de fuite de données entre clients pour un bénéfice non demandé).

## Prochaines étapes (Phase 7)
- Centre de notifications (badge non-lues, liste, marquer comme lu) — les évènements sont déjà créés en base depuis la Phase 4 (soumission, rejet, mismatch) ; ajouter l'évènement `consolidation_ready`.
- Visualiseur du journal d'audit (`audit_logs`) avec filtres par utilisateur/filiale/période.
- Exports Excel/CSV : états consolidés, paquets filiale, vue dashboard courante.
