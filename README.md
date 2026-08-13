# OHADA_CONSO+

Plateforme de consolidation financière et de reporting de groupe pour **NOVA AFRICA GROUP**, groupe ouest-africain fictif présent au Sénégal, en Côte d'Ivoire, au Mali, au Maroc, au Ghana et en France.

Application PHP/MySQL classique (architecture MVC maison, sans framework ni dépendance Composer), pensée pour être installée en une seule instance par client (voir §Réutilisation).

## Fonctionnalités

- **Structure de groupe** : hiérarchie de filiales, méthodes de consolidation (intégration globale / mise en équivalence / exclue), taux de détention.
- **Périodes de reporting** : cycle de vie mensuel (`Ouverte → En cours → Soumise → En revue → Validée → Consolidée → Clôturée`).
- **Collecte des données** : saisie manuelle ou import CSV du compte de résultat, bilan et flux de trésorerie par filiale, avec contrôle d'équilibre bilanciel en temps réel.
- **Workflow** : soumission → validation/rejet par le contrôleur de filiale, historique complet et traçable.
- **Intercompany** : déclaration des créances/dettes/produits/charges intra-groupe, rapprochement automatique, détection des écarts.
- **Moteur de consolidation** : conversion multi-devises, agrégation, éliminations intercompany et dividendes, mise en équivalence, ajustements manuels, intérêts minoritaires — pipeline en 7 étapes tracées.
- **États financiers OHADA/SYCEBNL** : présentation normalisée (codes REF) du compte de résultat et du bilan, au niveau filiale et groupe (liasse complète).
- **Budget vs Actual & dashboards** : KPIs, tendances, contribution par filiale, classement, situation bilancielle groupe — export PDF (impression navigateur) et CSV.
- **Notifications, journal d'audit, exports CSV** : traçabilité complète des actions et des décisions.

## Stack technique

- PHP 8 (testé sur 8.0.30), aucune dépendance Composer requise pour faire fonctionner l'application.
- MySQL/MariaDB (testé sur MariaDB 10.4 via XAMPP).
- JavaScript vanilla (aucun framework front) — filtres dynamiques en AJAX (`fetch`, `history.pushState`).
- Design system maison (`public/assets/css/app.css`), palette orange + vert.

## Installation locale (XAMPP / Windows)

1. **Copier le projet** dans `C:\xampp\htdocs\groupfin` (ou tout dossier servi par Apache).
2. **Créer la base de données** :
   ```
   mysql -u root -e "CREATE DATABASE groupfin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root groupfin < database/schema.sql
   mysql -u root groupfin < database/seed.sql
   ```
3. **Générer les données de démonstration** (financières, workflow, intercompany) :
   ```
   php database/seed_financials.php
   php database/seed_workflow.php
   php database/seed_intercompany.php
   ```
4. **Configurer l'application** : copier `config/config.example.php` en `config/config.php` et adapter les identifiants de connexion à la base si besoin (`config.php` n'est jamais versionné — voir `.gitignore`).
5. **Démarrer Apache et MySQL** (XAMPP Control Panel), puis ouvrir :
   ```
   http://localhost/groupfin/public/
   ```

Voir [`USER_MANUAL.md`](USER_MANUAL.md) pour les comptes de démonstration et un guide d'utilisation par rôle, et [`TECHNICAL_DOCUMENTATION.md`](TECHNICAL_DOCUMENTATION.md) pour l'architecture détaillée.

## Tests

Suite de tests unitaires (sans dépendance, aucun framework de test requis) couvrant la logique de calcul financier pure (équation bilancielle, écarts budgétaires, conversion de devises, mapping OHADA) :
```
php tests/run.php
```
Le workflow, le RBAC et le pipeline de consolidation sont couverts par le protocole de vérification manuelle (HTTP réel) documenté phase par phase dans `PROJECT_STATE.md`.

## Documentation

- [`docs/CONSOLIDATION_LOGIC.md`](docs/CONSOLIDATION_LOGIC.md) — logique métier détaillée : formules, conventions de signe, choix de conception et bugs corrigés (utile pour comprendre *pourquoi*, pas seulement *quoi*).
- [`USER_MANUAL.md`](USER_MANUAL.md) — manuel utilisateur par rôle.
- [`GUIDE_DE_TEST.md`](GUIDE_DE_TEST.md) — parcours de test guidé, de la connexion jusqu'aux états financiers consolidés et au reporting annuel.
- [`TECHNICAL_DOCUMENTATION.md`](TECHNICAL_DOCUMENTATION.md) — architecture, structure du code, sécurité.
- [`PROJECT_STATE.md`](PROJECT_STATE.md) — état d'avancement, décisions produit, vérifications exécutées phase par phase.

## Réutilisation pour un autre groupe

L'architecture est conçue pour une **installation dédiée par client** (une base de données = un groupe), pas en mode multi-tenant. Voir `PROJECT_STATE.md` §"Cuts / V2 backlog" pour le détail de ce qui resterait à adapter (nom du groupe en configuration, plan de comptes).

## Licence

Projet de démonstration — NOVA AFRICA GROUP est un groupe fictif.
