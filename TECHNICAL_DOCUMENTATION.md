# Documentation technique — OHADA_CONSO+

Public visé : développeur reprenant ou maintenant le projet. Pour la logique **métier/financière** (formules, conventions de signe, bugs corrigés), voir [`docs/CONSOLIDATION_LOGIC.md`](docs/CONSOLIDATION_LOGIC.md) — ce document-ci couvre l'architecture applicative.

## 1. Principes directeurs

- **Zéro dépendance Composer requise** pour exécuter l'application : autoload PSR-4 simplifié maison (`spl_autoload_register`, voir `public/index.php`), pas de vendor à installer. Ce choix se répercute sur plusieurs décisions (CSV plutôt que XLSX pour les exports, impression navigateur plutôt qu'une bibliothèque pour le PDF — voir `docs/CONSOLIDATION_LOGIC.md`).
- **Une installation par client** : l'application est single-tenant par conception (une base de données = un groupe). Voir `PROJECT_STATE.md` §"Cuts / V2 backlog" pour la discussion multi-tenant (délibérément écartée).
- **Aucune donnée factice à l'écran** : toute valeur affichée provient d'une requête réelle ; un panneau sans donnée disponible est simplement absent plutôt que de montrer un zéro trompeur.

## 2. Structure du dépôt

```
app/
  controllers/   Contrôleurs HTTP (un par domaine fonctionnel)
  services/      Logique métier (validation, workflow, consolidation, reporting...)
  repositories/  Accès aux données (une classe par agrégat, requêtes PDO préparées)
  models/        Objets de domaine simples (typés, sans logique de persistance)
  middleware/     AuthMiddleware, AuthorizationMiddleware, CsrfMiddleware
  core/          Router, Request, Controller (base), Database (PDO singleton), Session, View
  helpers/       Fonctions globales (helpers.php, charts.php, ohada.php)
config/
  config.example.php   Modèle versionné — copier en config.php (jamais versionné)
database/
  schema.sql             Schéma complet (19 tables), rejouable sur base vierge
  seed.sql                Référentiels + structure de groupe + utilisateurs de démonstration
  seed_financials.php     Génère les données financières + budgets 2026 (déterministe)
  seed_workflow.php       Positionne l'état de démonstration du workflow
  seed_intercompany.php   Positionne le scénario de démonstration intercompany
docs/
  CONSOLIDATION_LOGIC.md  Logique métier détaillée, formules, bugs corrigés
public/
  index.php       Front controller unique — toutes les requêtes y passent
  assets/         CSS, pas de build step (fichier unique app.css)
tests/
  run.php, *Test.php, framework.php, bootstrap.php   Suite de tests sans dépendance
views/
  Un dossier par domaine + layouts/main.php (layout applicatif) + partials/
```

## 3. Flux d'une requête

1. Apache route tout vers `public/index.php` (voir `public/.htaccess`).
2. `index.php` enregistre l'autoloader, charge les helpers globaux, charge `config/config.php`, instancie `Router` et `Request`.
3. Les routes sont déclarées explicitement (`$router->get(...)`, `$router->post(...)`) avec leur chaîne de middlewares.
4. `Router::dispatch()` résout la route (y compris les paramètres dynamiques `{id}`), exécute les middlewares dans l'ordre, puis appelle la méthode du contrôleur.
5. Le contrôleur orchestre un ou plusieurs services/repositories puis appelle `$this->view(...)`, qui rend le template PHP dans le layout `views/layouts/main.php` (sauf requête AJAX — voir §5).

## 4. Sécurité

- **Authentification** : `password_hash`/`password_verify`, session régénérée à la connexion (`Session::regenerate()`), cookies `httponly` + `SameSite=Lax`.
- **RBAC à deux niveaux** :
  - `AuthMiddleware` : bloque tout accès non authentifié (redirection `/login`).
  - `AuthorizationMiddleware::role([...])` : liste blanche de rôles autorisés sur une route.
  - `AuthorizationMiddleware::subsidiaryScope('paramName')` : un Préparateur/Contrôleur ne peut agir que sur sa propre filiale, même en forgeant l'URL — vérifié côté serveur sur le paramètre de route indiqué.
  - Tout refus (401/403) est journalisé dans `audit_logs` avant l'affichage de l'erreur.
- **CSRF** : jeton de session (`csrf_token()`), champ caché `_csrf` sur tout formulaire POST (`csrf_field()`), vérifié par `CsrfMiddleware` avant l'exécution du contrôleur.
- **XSS** : échappement systématique en sortie de vue via l'helper `h()` (`htmlspecialchars` avec `ENT_QUOTES`) — jamais d'affichage brut d'une donnée utilisateur.
- **SQL** : PDO exclusivement, requêtes préparées, `PDO::ATTR_EMULATE_PREPARES => false` (préparation native — **un même paramètre nommé ne peut pas être réutilisé deux fois dans une requête**, piège rencontré et documenté en Phase 3).
- **Traçabilité** : `audit_logs` journalise chaque mutation (création/modification/validation/rejet/refus d'accès) avec ancienne/nouvelle valeur, utilisateur, filiale et période concernées.

## 5. Filtres dynamiques (AJAX)

Les écrans avec filtres (dashboard, Budget vs Actual, taux de change, intercompany, ajustements, audit, liasse groupe) ne rechargent pas la page entière :

1. Le contrôleur détecte l'en-tête `X-Requested-With: XMLHttpRequest` (`Request::isAjax()`) et, si présent, rend la vue **sans layout** (juste le fragment).
2. La vue encapsule son contenu dans `<div id="ajax-content">`.
3. Une couche JS générique (`views/layouts/main.php`) intercepte la soumission de tout formulaire `[data-ajax-filter]`, fait un `fetch()` vers l'action du formulaire, remplace `#ajax-content` par le fragment reçu, et synchronise l'URL via `history.pushState` (le bouton retour du navigateur fonctionne, `popstate` refait le `fetch`).
4. Repli automatique sur une navigation complète si `#ajax-content` est absent ou si le `fetch()` échoue.

## 6. Modèle de données (aperçu)

19 tables, regroupées par domaine :

| Domaine | Tables |
|---|---|
| Référentiels | `roles`, `currencies`, `accounts` |
| Structure de groupe | `subsidiaries`, `users` |
| Périodes & taux | `reporting_periods`, `exchange_rates` |
| Collecte | `financial_data`, `budgets` |
| Workflow | `workflow_transitions` (statut dérivé de la dernière ligne, jamais stocké en colonne dédiée) |
| Intercompany | `intercompany_transactions` |
| Consolidation | `consolidation_runs`, `consolidation_run_steps`, `consolidation_adjustments`, `eliminations`, `minority_interests`, `consolidation_line_items` |
| Transverse | `notifications`, `audit_logs` |

Motif récurrent : **statut dérivé, jamais dupliqué**. Le statut d'un paquet filiale (`draft`/`submitted`/`validated`/`rejected`) et celui d'une période sont tous deux déduits de la dernière ligne de leur table de transitions respective, plutôt que stockés dans une colonne à synchroniser — élimine toute une classe de bugs de désynchronisation.

## 7. Points d'extension (si vous reprenez ce projet)

- **Nouveau compte du plan de comptes** : ajouter la ligne dans `accounts` (via `schema.sql`/une migration), puis étendre le mapping OHADA correspondant dans `app/helpers/ohada.php` si le compte doit apparaître dans la présentation normalisée (sinon il reste inclus dans l'équation bilancielle mais absent de l'affichage OHADA).
- **Nouvelle devise** : l'ajouter dans `currencies`, fournir ses taux dans `exchange_rates` pour chaque période — `CurrencyConversionService` n'a rien de codé en dur par devise.
- **Nouveau rôle** : l'ajouter dans `roles`, dans la classe `App\Models\Role` (constantes), puis dans les listes `AuthorizationMiddleware::role([...])` des routes concernées (`public/index.php`).
- **Sortir le nom du groupe en configuration** : actuellement en dur dans `views/layouts/main.php` et `views/auth/login.php` — voir `PROJECT_STATE.md` pour le contexte (préparation à la revente à un autre client).

## 8. Tests

Suite de tests unitaires sans dépendance (`tests/`, voir `README.md` §Tests) : couvre la logique de calcul pure (`ValidationService`, `BudgetVarianceService`, `CurrencyConversionService`, `app/helpers/ohada.php`). N'utilise ni base de données ni serveur HTTP — chaque test instancie directement les services avec des données synthétiques.

Le reste (workflow, RBAC, pipeline de consolidation complet, exports, notifications) est couvert par un protocole de vérification manuelle systématique sur base réelle (requêtes HTTP via curl, jamais de simple lecture de code), documenté phase par phase dans `PROJECT_STATE.md` sous "Vérifications exécutées (DoD Phase N)" — c'est la référence à consulter pour savoir ce qui a été concrètement testé et comment.

## 9. Déploiement

Application conçue pour un hébergement mutualisé/VPS classique LAMP (Apache + PHP-FPM ou mod_php + MySQL) :

1. Copier l'arborescence (hors `config/config.php`, `storage/logs/*`, `storage/exports/*` — voir `.gitignore`).
2. Copier `config/config.example.php` en `config/config.php`, adapter `db.*` et `app.base_url`, passer `app.debug` à `false` en production.
3. Rejouer `schema.sql` sur la base cible ; `seed.sql`/`seed_*.php` sont réservés à la démonstration, ne pas les exécuter en production.
4. Le `DocumentRoot` (ou l'alias Apache) doit pointer vers `public/` — jamais la racine du projet (les fichiers `app/`, `config/`, `database/` ne doivent pas être accessibles directement via HTTP).
