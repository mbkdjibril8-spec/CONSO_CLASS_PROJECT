-- =====================================================================
-- GROUPFIN — Données de démonstration : NOVA AFRICA GROUP
-- Ce fichier est complété phase après phase (voir PROJECT_STATE.md).
-- Phase 1 : référentiels + structure de groupe + utilisateurs + périodes
--           + taux de change. Les données financières (Phase 3+),
--           l'intercompany (Phase 4) et les runs de consolidation
--           (Phase 5) seront ajoutés par les phases suivantes.
-- Mot de passe de démonstration pour TOUS les comptes : Groupfin@2026
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Rôles RBAC
-- ---------------------------------------------------------------------
INSERT INTO roles (code, label) VALUES
    ('group_admin', 'Administrateur groupe'),
    ('preparer', 'Préparateur'),
    ('subsidiary_controller', 'Contrôleur de filiale'),
    ('consolidation_manager', 'Responsable consolidation'),
    ('cfo_readonly', 'Directeur financier (lecture)');

-- ---------------------------------------------------------------------
-- Devises (consolidation en XOF)
-- ---------------------------------------------------------------------
INSERT INTO currencies (code, name, symbol, is_group_currency) VALUES
    ('XOF', 'Franc CFA (BCEAO)', 'FCFA', 1),
    ('EUR', 'Euro', '€', 0),
    ('MAD', 'Dirham marocain', 'DH', 0),
    ('GHS', 'Cedi ghanéen', 'GH₵', 0);

-- ---------------------------------------------------------------------
-- Structure de groupe — NOVA AFRICA GROUP
-- NOVA Holding est la tête de groupe : elle porte l'arbre de hiérarchie
-- mais ne soumet pas de paquet de reporting propre en V1 (méthode
-- "excluded" = exclue du périmètre de calcul bottom-up). Voir
-- docs/CONSOLIDATION_LOGIC.md et PROJECT_STATE.md (V2 backlog).
-- ---------------------------------------------------------------------
INSERT INTO subsidiaries (code, name, country, zone, activity, currency_code, parent_id, ownership_pct, control_pct, consolidation_method) VALUES
    ('NOVA-HLD', 'NOVA Holding', 'Sénégal', 'Afrique de l''Ouest', 'Holding / Siège de groupe', 'XOF', NULL, 100.00, 100.00, 'excluded');

SET @hld_id = (SELECT id FROM subsidiaries WHERE code = 'NOVA-HLD');

INSERT INTO subsidiaries (code, name, country, zone, activity, currency_code, parent_id, ownership_pct, control_pct, consolidation_method) VALUES
    ('NOVA-SN', 'NOVA Senegal Retail', 'Sénégal', 'Afrique de l''Ouest', 'Distribution retail', 'XOF', @hld_id, 100.00, 100.00, 'full'),
    ('NOVA-CI', 'NOVA Côte d''Ivoire', 'Côte d''Ivoire', 'Afrique de l''Ouest', 'Distribution retail', 'XOF', @hld_id, 75.00, 75.00, 'full'),
    ('NOVA-ML', 'NOVA Mali', 'Mali', 'Afrique de l''Ouest', 'Distribution retail', 'XOF', @hld_id, 60.00, 60.00, 'full'),
    ('NOVA-FR', 'NOVA France', 'France', 'Europe', 'Négoce / Import-export', 'EUR', @hld_id, 51.00, 51.00, 'full'),
    ('NOVA-MA', 'NOVA Morocco', 'Maroc', 'Afrique du Nord', 'Distribution retail', 'MAD', @hld_id, 80.00, 80.00, 'full'),
    ('NOVA-GH', 'NOVA Ghana', 'Ghana', 'Afrique de l''Ouest', 'Distribution retail', 'GHS', @hld_id, 30.00, 30.00, 'equity');

-- ---------------------------------------------------------------------
-- Utilisateurs de démonstration (un par rôle transverse + un binôme
-- préparateur/contrôleur par filiale opérationnelle).
-- password_hash = bcrypt("Groupfin@2026")
-- ---------------------------------------------------------------------
SET @pwd = '$2y$12$KrLjMimE7KA4YglMf11FSuu/rytOFfTpI4LfSk3r9zNCs0/4Itubi';
SET @r_admin = (SELECT id FROM roles WHERE code = 'group_admin');
SET @r_preparer = (SELECT id FROM roles WHERE code = 'preparer');
SET @r_controller = (SELECT id FROM roles WHERE code = 'subsidiary_controller');
SET @r_consol = (SELECT id FROM roles WHERE code = 'consolidation_manager');
SET @r_cfo = (SELECT id FROM roles WHERE code = 'cfo_readonly');

INSERT INTO users (name, email, password_hash, role_id, subsidiary_id) VALUES
    ('Amadou Ndiaye', 'admin@novaafrica.com', @pwd, @r_admin, NULL),
    ('Fatou Diop', 'consolidation@novaafrica.com', @pwd, @r_consol, NULL),
    ('Jean-Baptiste Traoré', 'cfo@novaafrica.com', @pwd, @r_cfo, NULL),

    ('Cheikh Fall', 'preparer.sn@novaafrica.com', @pwd, @r_preparer, (SELECT id FROM subsidiaries WHERE code = 'NOVA-SN')),
    ('Aïda Sarr', 'controller.sn@novaafrica.com', @pwd, @r_controller, (SELECT id FROM subsidiaries WHERE code = 'NOVA-SN')),

    ('Koffi Yao', 'preparer.ci@novaafrica.com', @pwd, @r_preparer, (SELECT id FROM subsidiaries WHERE code = 'NOVA-CI')),
    ('Aya Kouassi', 'controller.ci@novaafrica.com', @pwd, @r_controller, (SELECT id FROM subsidiaries WHERE code = 'NOVA-CI')),

    ('Oumar Diarra', 'preparer.ml@novaafrica.com', @pwd, @r_preparer, (SELECT id FROM subsidiaries WHERE code = 'NOVA-ML')),
    ('Fanta Traoré', 'controller.ml@novaafrica.com', @pwd, @r_controller, (SELECT id FROM subsidiaries WHERE code = 'NOVA-ML')),

    ('Claire Martin', 'preparer.fr@novaafrica.com', @pwd, @r_preparer, (SELECT id FROM subsidiaries WHERE code = 'NOVA-FR')),
    ('Nicolas Bernard', 'controller.fr@novaafrica.com', @pwd, @r_controller, (SELECT id FROM subsidiaries WHERE code = 'NOVA-FR')),

    ('Yasmine El Amrani', 'preparer.ma@novaafrica.com', @pwd, @r_preparer, (SELECT id FROM subsidiaries WHERE code = 'NOVA-MA')),
    ('Karim Benjelloun', 'controller.ma@novaafrica.com', @pwd, @r_controller, (SELECT id FROM subsidiaries WHERE code = 'NOVA-MA')),

    ('Kwame Mensah', 'preparer.gh@novaafrica.com', @pwd, @r_preparer, (SELECT id FROM subsidiaries WHERE code = 'NOVA-GH')),
    ('Abena Owusu', 'controller.gh@novaafrica.com', @pwd, @r_controller, (SELECT id FROM subsidiaries WHERE code = 'NOVA-GH'));

-- ---------------------------------------------------------------------
-- Périodes de reporting — exercice 2026
-- Janvier à novembre : clôturées (historique). Décembre : en cours
-- (période active du scénario de démonstration, voir cahier des charges §9).
-- ---------------------------------------------------------------------
INSERT INTO reporting_periods (year, month, label, status) VALUES
    (2026, 1, '2026-01', 'closed'),
    (2026, 2, '2026-02', 'closed'),
    (2026, 3, '2026-03', 'closed'),
    (2026, 4, '2026-04', 'closed'),
    (2026, 5, '2026-05', 'closed'),
    (2026, 6, '2026-06', 'closed'),
    (2026, 7, '2026-07', 'closed'),
    (2026, 8, '2026-08', 'closed'),
    (2026, 9, '2026-09', 'closed'),
    (2026, 10, '2026-10', 'closed'),
    (2026, 11, '2026-11', 'closed'),
    (2026, 12, '2026-12', 'in_progress');

-- ---------------------------------------------------------------------
-- Taux de change (moyen = résultat, clôture = bilan). EUR/XOF fixe
-- (parité CFA), MAD/XOF et GHS/XOF avec légère variation mensuelle.
-- ---------------------------------------------------------------------
INSERT INTO exchange_rates (currency_code, period_id, rate_type, rate)
SELECT 'EUR', id, 'average', 655.957 FROM reporting_periods WHERE year = 2026
UNION ALL
SELECT 'EUR', id, 'closing', 655.957 FROM reporting_periods WHERE year = 2026;

INSERT INTO exchange_rates (currency_code, period_id, rate_type, rate)
SELECT 'MAD', id, 'average',
    CASE month
        WHEN 1 THEN 64.80 WHEN 2 THEN 64.90 WHEN 3 THEN 65.00 WHEN 4 THEN 65.10
        WHEN 5 THEN 64.95 WHEN 6 THEN 65.20 WHEN 7 THEN 65.30 WHEN 8 THEN 65.15
        WHEN 9 THEN 65.40 WHEN 10 THEN 65.25 WHEN 11 THEN 65.50 WHEN 12 THEN 65.60
    END
FROM reporting_periods WHERE year = 2026
UNION ALL
SELECT 'MAD', id, 'closing',
    CASE month
        WHEN 1 THEN 64.85 WHEN 2 THEN 64.95 WHEN 3 THEN 65.05 WHEN 4 THEN 65.00
        WHEN 5 THEN 65.10 WHEN 6 THEN 65.15 WHEN 7 THEN 65.25 WHEN 8 THEN 65.30
        WHEN 9 THEN 65.35 WHEN 10 THEN 65.45 WHEN 11 THEN 65.40 WHEN 12 THEN 65.55
    END
FROM reporting_periods WHERE year = 2026;

INSERT INTO exchange_rates (currency_code, period_id, rate_type, rate)
SELECT 'GHS', id, 'average',
    CASE month
        WHEN 1 THEN 38.20 WHEN 2 THEN 38.50 WHEN 3 THEN 38.90 WHEN 4 THEN 39.30
        WHEN 5 THEN 39.60 WHEN 6 THEN 39.90 WHEN 7 THEN 40.20 WHEN 8 THEN 40.50
        WHEN 9 THEN 40.80 WHEN 10 THEN 41.10 WHEN 11 THEN 41.40 WHEN 12 THEN 41.70
    END
FROM reporting_periods WHERE year = 2026
UNION ALL
SELECT 'GHS', id, 'closing',
    CASE month
        WHEN 1 THEN 38.40 WHEN 2 THEN 38.70 WHEN 3 THEN 39.10 WHEN 4 THEN 39.50
        WHEN 5 THEN 39.80 WHEN 6 THEN 40.10 WHEN 7 THEN 40.40 WHEN 8 THEN 40.70
        WHEN 9 THEN 41.00 WHEN 10 THEN 41.30 WHEN 11 THEN 41.60 WHEN 12 THEN 41.90
    END
FROM reporting_periods WHERE year = 2026;
