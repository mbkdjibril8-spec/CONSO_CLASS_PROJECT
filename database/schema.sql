-- =====================================================================
-- GROUPFIN — Schema de base de données
-- Plateforme de consolidation financière et reporting de groupe
-- Moteur : MySQL / MariaDB (InnoDB, utf8mb4)
-- =====================================================================
-- Ce script est idempotent : il peut être rejoué sur une base vierge.
-- Ordre des tables : référentiels -> structure de groupe -> périodes ->
-- collecte -> workflow -> intercompany -> consolidation -> transverse.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS minority_interests;
DROP TABLE IF EXISTS eliminations;
DROP TABLE IF EXISTS consolidation_adjustments;
DROP TABLE IF EXISTS consolidation_run_steps;
DROP TABLE IF EXISTS consolidation_runs;
DROP TABLE IF EXISTS intercompany_transactions;
DROP TABLE IF EXISTS workflow_transitions;
DROP TABLE IF EXISTS budgets;
DROP TABLE IF EXISTS financial_data;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS exchange_rates;
DROP TABLE IF EXISTS reporting_periods;
DROP TABLE IF EXISTS subsidiaries;
DROP TABLE IF EXISTS currencies;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- REFERENTIELS
-- ---------------------------------------------------------------------

-- Rôles applicatifs (RBAC). Code utilisé partout dans le code (jamais l'id en dur).
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,       -- group_admin | preparer | subsidiary_controller | consolidation_manager | cfo_readonly
    label VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Devises gérées par le groupe. La devise de consolidation est XOF (is_group_currency = 1).
CREATE TABLE currencies (
    code CHAR(3) PRIMARY KEY,               -- XOF, EUR, MAD, GHS...
    name VARCHAR(60) NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    is_group_currency TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- STRUCTURE DE GROUPE
-- ---------------------------------------------------------------------

CREATE TABLE subsidiaries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    country VARCHAR(80) NOT NULL,
    zone VARCHAR(80) NULL,
    activity VARCHAR(120) NULL,
    currency_code CHAR(3) NOT NULL,
    parent_id INT UNSIGNED NULL,            -- NULL = tête de groupe (holding)
    ownership_pct DECIMAL(5,2) NOT NULL DEFAULT 100.00,   -- % de détention financière
    control_pct DECIMAL(5,2) NOT NULL DEFAULT 100.00,     -- % de contrôle (droits de vote)
    consolidation_method ENUM('full','equity','excluded') NOT NULL DEFAULT 'full',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subsidiaries_currency FOREIGN KEY (currency_code) REFERENCES currencies(code),
    CONSTRAINT fk_subsidiaries_parent FOREIGN KEY (parent_id) REFERENCES subsidiaries(id) ON DELETE SET NULL,
    INDEX idx_subsidiaries_parent (parent_id),
    INDEX idx_subsidiaries_currency (currency_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Utilisateurs applicatifs. subsidiary_id = NULL pour les rôles groupe
-- (group_admin, consolidation_manager, cfo_readonly). Obligatoire pour
-- preparer et subsidiary_controller (portée de données).
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    subsidiary_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_users_subsidiary FOREIGN KEY (subsidiary_id) REFERENCES subsidiaries(id) ON DELETE SET NULL,
    INDEX idx_users_role (role_id),
    INDEX idx_users_subsidiary (subsidiary_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- PERIODES DE REPORTING
-- ---------------------------------------------------------------------

CREATE TABLE reporting_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,        -- 1-12
    label VARCHAR(20) NOT NULL,             -- ex: "2026-12"
    status ENUM('open','in_progress','submitted','under_review','validated','consolidated','closed')
        NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_period_year_month (year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Taux de change par devise/période. Deux taux distincts par période :
-- 'average' (utilisé pour le compte de résultat) et 'closing' (bilan).
CREATE TABLE exchange_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    currency_code CHAR(3) NOT NULL,
    period_id INT UNSIGNED NOT NULL,
    rate_type ENUM('average','closing') NOT NULL,
    rate DECIMAL(18,6) NOT NULL,            -- 1 unité de currency_code = rate XOF
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rates_currency FOREIGN KEY (currency_code) REFERENCES currencies(code),
    CONSTRAINT fk_rates_period FOREIGN KEY (period_id) REFERENCES reporting_periods(id) ON DELETE CASCADE,
    UNIQUE KEY uq_rate_currency_period_type (currency_code, period_id, rate_type),
    INDEX idx_rates_period (period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- PLAN DE COMPTES ET COLLECTE FINANCIERE
-- ---------------------------------------------------------------------

CREATE TABLE accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    statement_type ENUM('IS','BS','CF') NOT NULL,   -- Income Statement / Balance Sheet / Cash Flow
    section VARCHAR(60) NOT NULL,                   -- ex: revenue, opex, fixed_assets, equity...
    normal_balance ENUM('debit','credit') NOT NULL,
    is_intercompany TINYINT(1) NOT NULL DEFAULT 0,  -- compte utilisé pour les soldes intercos
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_accounts_statement (statement_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données financières saisies par filiale/période/compte, en devise locale.
CREATE TABLE financial_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subsidiary_id INT UNSIGNED NOT NULL,
    period_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fd_subsidiary FOREIGN KEY (subsidiary_id) REFERENCES subsidiaries(id) ON DELETE CASCADE,
    CONSTRAINT fk_fd_period FOREIGN KEY (period_id) REFERENCES reporting_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_fd_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_fd_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_fd_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_fd_sub_period_account (subsidiary_id, period_id, account_id),
    INDEX idx_fd_subsidiary_period (subsidiary_id, period_id),
    INDEX idx_fd_period_account (period_id, account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE budgets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subsidiary_id INT UNSIGNED NOT NULL,
    period_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bg_subsidiary FOREIGN KEY (subsidiary_id) REFERENCES subsidiaries(id) ON DELETE CASCADE,
    CONSTRAINT fk_bg_period FOREIGN KEY (period_id) REFERENCES reporting_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_bg_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_bg_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_bg_sub_period_account (subsidiary_id, period_id, account_id),
    INDEX idx_bg_subsidiary_period (subsidiary_id, period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- WORKFLOW DE VALIDATION
-- ---------------------------------------------------------------------

-- Historique de toutes les transitions de statut d'un paquet filiale/période
-- (soumission, rejet, validation...). from_status NULL = première transition.
CREATE TABLE workflow_transitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subsidiary_id INT UNSIGNED NOT NULL,
    period_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    comment TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wf_subsidiary FOREIGN KEY (subsidiary_id) REFERENCES subsidiaries(id) ON DELETE CASCADE,
    CONSTRAINT fk_wf_period FOREIGN KEY (period_id) REFERENCES reporting_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_wf_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_wf_subsidiary_period (subsidiary_id, period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- INTERCOMPANY
-- ---------------------------------------------------------------------

CREATE TABLE intercompany_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    subsidiary_id INT UNSIGNED NOT NULL,           -- filiale déclarante
    counterparty_subsidiary_id INT UNSIGNED NOT NULL,
    type ENUM('receivable','payable','revenue','expense','dividend') NOT NULL,
    amount_local DECIMAL(18,2) NOT NULL,           -- montant en devise locale de subsidiary_id
    amount_group DECIMAL(18,2) NOT NULL,           -- montant converti en XOF
    matched_transaction_id INT UNSIGNED NULL,      -- pointe vers l'écriture miroir chez la contrepartie
    match_status ENUM('pending','matched','mismatch') NOT NULL DEFAULT 'pending',
    difference_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ic_period FOREIGN KEY (period_id) REFERENCES reporting_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_ic_subsidiary FOREIGN KEY (subsidiary_id) REFERENCES subsidiaries(id) ON DELETE CASCADE,
    CONSTRAINT fk_ic_counterparty FOREIGN KEY (counterparty_subsidiary_id) REFERENCES subsidiaries(id) ON DELETE CASCADE,
    CONSTRAINT fk_ic_matched FOREIGN KEY (matched_transaction_id) REFERENCES intercompany_transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_ic_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ic_period_subsidiary (period_id, subsidiary_id),
    INDEX idx_ic_counterparty (counterparty_subsidiary_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- CONSOLIDATION
-- ---------------------------------------------------------------------

CREATE TABLE consolidation_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    status ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
    started_by INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    notes TEXT NULL,
    CONSTRAINT fk_run_period FOREIGN KEY (period_id) REFERENCES reporting_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_run_started_by FOREIGN KEY (started_by) REFERENCES users(id),
    INDEX idx_run_period (period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Journal détaillé de chaque étape d'un run (traçabilité exigée par le cahier des charges).
CREATE TABLE consolidation_run_steps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id INT UNSIGNED NOT NULL,
    step_order TINYINT UNSIGNED NOT NULL,
    step_name VARCHAR(100) NOT NULL,
    status ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
    details TEXT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    CONSTRAINT fk_step_run FOREIGN KEY (run_id) REFERENCES consolidation_runs(id) ON DELETE CASCADE,
    INDEX idx_step_run (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Écritures de retraitement manuelles (débit/crédit), pleinement auditables.
CREATE TABLE consolidation_adjustments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    subsidiary_id INT UNSIGNED NULL,       -- NULL = écriture au niveau groupe
    account_id INT UNSIGNED NOT NULL,
    debit_credit ENUM('debit','credit') NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_adj_period FOREIGN KEY (period_id) REFERENCES reporting_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_adj_subsidiary FOREIGN KEY (subsidiary_id) REFERENCES subsidiaries(id) ON DELETE CASCADE,
    CONSTRAINT fk_adj_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_adj_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_adj_period (period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Écritures d'élimination générées automatiquement par le moteur de consolidation
-- (intercos et dividendes intra-groupe) pour un run donné.
CREATE TABLE eliminations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id INT UNSIGNED NOT NULL,
    type ENUM('intercompany','dividend') NOT NULL,
    source_transaction_id INT UNSIGNED NULL,   -- référence intercompany_transactions.id si applicable
    subsidiary_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_elim_run FOREIGN KEY (run_id) REFERENCES consolidation_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_elim_subsidiary FOREIGN KEY (subsidiary_id) REFERENCES subsidiaries(id),
    CONSTRAINT fk_elim_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_elim_source FOREIGN KEY (source_transaction_id) REFERENCES intercompany_transactions(id) ON DELETE SET NULL,
    INDEX idx_elim_run (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Intérêts minoritaires calculés par run et par filiale (quote-part des tiers).
CREATE TABLE minority_interests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id INT UNSIGNED NOT NULL,
    subsidiary_id INT UNSIGNED NOT NULL,
    minority_pct DECIMAL(5,2) NOT NULL,        -- 100 - ownership_pct
    net_income_share DECIMAL(18,2) NOT NULL,   -- quote-part minoritaire du résultat net
    equity_share DECIMAL(18,2) NOT NULL,       -- quote-part minoritaire des capitaux propres
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mi_run FOREIGN KEY (run_id) REFERENCES consolidation_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_mi_subsidiary FOREIGN KEY (subsidiary_id) REFERENCES subsidiaries(id),
    UNIQUE KEY uq_mi_run_subsidiary (run_id, subsidiary_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- TRANSVERSE : NOTIFICATIONS ET AUDIT
-- ---------------------------------------------------------------------

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(40) NOT NULL,             -- submission | rejection | mismatch | consolidation_ready
    message VARCHAR(255) NOT NULL,
    related_entity VARCHAR(40) NULL,
    related_id INT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Journal d'audit : toute modification de donnée financière et tout changement
-- de statut doit y être tracé (utilisateur, ancienne/nouvelle valeur, horodatage).
CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,           -- ex: login, login_failed, unauthorized_access, data_update...
    entity_type VARCHAR(60) NOT NULL,
    entity_id INT UNSIGNED NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
