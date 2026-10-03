-- =============================================================================
-- Flotteo - Schéma de la base de données
-- MySQL / MariaDB - utf8mb4
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS incidents_fichiers;
DROP TABLE IF EXISTS incidents;
DROP TABLE IF EXISTS maintenances;
DROP TABLE IF EXISTS vehicules;
DROP TABLE IF EXISTS types_intervention;
DROP TABLE IF EXISTS lieux;
DROP TABLE IF EXISTS loueurs;
DROP TABLE IF EXISTS modeles;
DROP TABLE IF EXISTS marques;
DROP TABLE IF EXISTS entites;
DROP TABLE IF EXISTS utilisateurs;
DROP TABLE IF EXISTS parametres;

-- --- Utilisateurs & sécurité -----------------------------------------------
CREATE TABLE utilisateurs (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom           VARCHAR(120) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('lecture_seule','modification','administration') NOT NULL DEFAULT 'lecture_seule',
    actif         TINYINT(1) NOT NULL DEFAULT 1,
    avatar        VARCHAR(64) NULL DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_utilisateurs_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Paramétrage système ----------------------------------------------------
CREATE TABLE parametres (
    cle   VARCHAR(80) NOT NULL,
    valeur TEXT NULL,
    PRIMARY KEY (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Dictionnaires ---------------------------------------------------------
CREATE TABLE marques (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom  VARCHAR(90) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marques_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modeles (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    marque_id INT UNSIGNED NOT NULL,
    nom       VARCHAR(120) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_modeles (marque_id, nom),
    CONSTRAINT fk_modeles_marque FOREIGN KEY (marque_id) REFERENCES marques (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entites (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom  VARCHAR(140) NOT NULL,
    code VARCHAR(30) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entites_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loueurs (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom            VARCHAR(140) NOT NULL,
    contact_email  VARCHAR(190) NULL,
    telephone      VARCHAR(40) NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lieux (
    id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom    VARCHAR(120) NOT NULL,
    ville  VARCHAR(120) NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE types_intervention (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    libelle   VARCHAR(120) NOT NULL,
    categorie ENUM('constructeur','pneumatique','freinage','controle','carrosserie','autre')
              NOT NULL DEFAULT 'autre',
    actif     TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Parc de véhicules ------------------------------------------------------
CREATE TABLE vehicules (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    immatriculation       VARCHAR(20) NOT NULL,
    modele_id             INT UNSIGNED NOT NULL,
    entite_id             INT UNSIGNED NOT NULL,
    loueur_id             INT UNSIGNED NOT NULL,
    lieu_id               INT UNSIGNED NOT NULL,
    date_entree           DATE NOT NULL,
    date_sortie_prevue    DATE NOT NULL,
    date_sortie_effective DATE NULL,
    statut                ENUM('actif','immobilise','sorti') NOT NULL DEFAULT 'actif',
    immatricule           TINYINT(1) NOT NULL DEFAULT 0,
    commentaire           TEXT NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicules_immat (immatriculation),
    KEY idx_vehicules_sortie (date_sortie_prevue),
    KEY idx_vehicules_statut (statut),
    CONSTRAINT fk_vehicules_modele FOREIGN KEY (modele_id)  REFERENCES modeles (id),
    CONSTRAINT fk_vehicules_entite FOREIGN KEY (entite_id)  REFERENCES entites (id),
    CONSTRAINT fk_vehicules_loueur FOREIGN KEY (loueur_id)  REFERENCES loueurs (id),
    CONSTRAINT fk_vehicules_lieu   FOREIGN KEY (lieu_id)    REFERENCES lieux (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Entretien --------------------------------------------------------------
CREATE TABLE maintenances (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicule_id           INT UNSIGNED NOT NULL,
    type_intervention_id  INT UNSIGNED NOT NULL,
    date_operation        DATE NOT NULL,
    kilometrage           INT UNSIGNED NOT NULL DEFAULT 0,
    cout_ht               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cout_ttc              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    commentaire           TEXT NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_maintenances_vehicule (vehicule_id),
    KEY idx_maintenances_date (date_operation),
    CONSTRAINT fk_maintenances_vehicule FOREIGN KEY (vehicule_id)          REFERENCES vehicules (id) ON DELETE CASCADE,
    CONSTRAINT fk_maintenances_type    FOREIGN KEY (type_intervention_id) REFERENCES types_intervention (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Incidents & sinistres --------------------------------------------------
CREATE TABLE incidents (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicule_id   INT UNSIGNED NOT NULL,
    type          ENUM('accident','panne','vandalisme','bris_glace','autre') NOT NULL DEFAULT 'autre',
    date_incident DATE NOT NULL,
    lieu          VARCHAR(160) NULL,
    responsable   TINYINT(1) NOT NULL DEFAULT 0,
    immatricule   TINYINT(1) NOT NULL DEFAULT 0,
    statut        ENUM('ouvert','en_traitement','cloture') NOT NULL DEFAULT 'ouvert',
    description   TEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_incidents_vehicule (vehicule_id),
    KEY idx_incidents_date (date_incident),
    KEY idx_incidents_type (type),
    CONSTRAINT fk_incidents_vehicule FOREIGN KEY (vehicule_id) REFERENCES vehicules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE incidents_fichiers (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    incident_id   INT UNSIGNED NOT NULL,
    nom_fichier   VARCHAR(80) NOT NULL,
    nom_original  VARCHAR(190) NOT NULL,
    mime          VARCHAR(80) NOT NULL,
    taille        INT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fichiers_incident (incident_id),
    CONSTRAINT fk_fichiers_incident FOREIGN KEY (incident_id) REFERENCES incidents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --- Données de référence ---------------------------------------------------
INSERT INTO parametres (cle, valeur) VALUES
    ('email_gestionnaire', 'gestion@flotteo.local'),
    ('email_expediteur', 'no-reply@flotteo.local'),
    ('alerte_palier_1', '90'),
    ('alerte_palier_2', '180'),
    ('alerte_palier_3', '270'),
    ('alerte_active', '1'),
    ('mail_transport', 'mail'),
    ('smtp_host', ''),
    ('smtp_port', '587'),
    ('smtp_chiffrement', 'tls'),
    ('smtp_user', ''),
    ('smtp_password', '');

INSERT INTO types_intervention (libelle, categorie) VALUES
    ('Révision constructeur', 'constructeur'),
    ('Vidange / filtres', 'constructeur'),
    ('Pneumatiques', 'pneumatique'),
    ('Freinage', 'freinage'),
    ('Contrôle technique', 'controle'),
    ('Carrosserie', 'carrosserie');
