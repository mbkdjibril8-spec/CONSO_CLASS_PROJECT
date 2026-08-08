# PROJECT_STATE — GROUPFIN

## Phase courante
**Phase 1 — Fondation : TERMINÉE ✅**
Prochaine étape : Phase 2 — Structure de groupe + périodes.

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

## Décisions clés
- **NOVA Holding exclue du périmètre bottom-up (`consolidation_method = 'excluded'`)** : la tête de groupe porte l'arbre de hiérarchie mais ne soumet pas de paquet financier propre en V1 — le scénario de démonstration du cahier des charges (§9) compte explicitement "6/6" filiales, pas 7. Documenté également dans `docs/CONSOLIDATION_LOGIC.md` (Phase 5).
- Répertoires `app/controllers`, `app/models`, etc. restent en minuscules (conformes à l'arborescence du cahier des charges) ; les classes utilisent des namespaces `App\Controllers`, `App\Models`... en PascalCase — l'autoloader fait la conversion de casse.
- UI entièrement en français (cohérent avec README/manuel utilisateur en français et le contexte du groupe ouest-africain).
- Table `accounts` (plan de comptes) créée par le schéma mais volontairement vide à ce stade : sera peuplée en Phase 3 avec la conception des formulaires de saisie IS/BS/CF.

## Cuts / V2 backlog
(Rien à ce stade — hors-scope V1 déjà exclu dès la conception du schéma : pas de consolidation proportionnelle, pas de dimensions analytiques, pas de taux de change historiques au-delà moyen/clôture.)

## Prochaines étapes (Phase 2)
- CRUD filiales (créer/éditer, avec le même formulaire couvrant les champs du schéma).
- Vue arbre de hiérarchie (parent → filiales).
- Gestion des devises et taux de change (interface, actuellement seedés en SQL brut uniquement).
- Cycle de vie des périodes de reporting (transitions de statut contrôlées, période clôturée = lecture seule).
