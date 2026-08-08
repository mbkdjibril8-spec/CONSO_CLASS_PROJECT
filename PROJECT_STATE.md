# PROJECT_STATE — GROUPFIN

## Phase courante
**Phase 2 — Structure de groupe + périodes : TERMINÉE ✅**
Prochaine étape : Phase 3 — Collecte des données + validation.

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

## Décisions clés
- **NOVA Holding exclue du périmètre bottom-up (`consolidation_method = 'excluded'`)** : la tête de groupe porte l'arbre de hiérarchie mais ne soumet pas de paquet financier propre en V1 — le scénario de démonstration du cahier des charges (§9) compte explicitement "6/6" filiales, pas 7. Documenté également dans `docs/CONSOLIDATION_LOGIC.md` (Phase 5).
- Répertoires `app/controllers`, `app/models`, etc. restent en minuscules (conformes à l'arborescence du cahier des charges) ; les classes utilisent des namespaces `App\Controllers`, `App\Models`... en PascalCase — l'autoloader fait la conversion de casse.
- UI entièrement en français (cohérent avec README/manuel utilisateur en français et le contexte du groupe ouest-africain).
- Table `accounts` (plan de comptes) créée par le schéma mais volontairement vide à ce stade : sera peuplée en Phase 3 avec la conception des formulaires de saisie IS/BS/CF.

## Cuts / V2 backlog
(Rien à ce stade — hors-scope V1 déjà exclu dès la conception du schéma : pas de consolidation proportionnelle, pas de dimensions analytiques, pas de taux de change historiques au-delà moyen/clôture.)

## Prochaines étapes (Phase 3)
- Conception du plan de comptes minimal (`accounts`) couvrant IS/BS/CF, suffisant pour l'équation bilancielle et la consolidation, sans complexité inutile (règle §8.1).
- Formulaires de saisie IS/BS/CF par filiale/période (saisie manuelle) + import CSV avec rapport d'erreurs ligne par ligne.
- `ValidationService` : équation bilancielle (bloquant), champs obligatoires, types, anti-doublon de soumission, alerte non bloquante si variation de revenu > 50 % vs mois précédent.
