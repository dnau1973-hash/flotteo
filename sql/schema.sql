-- =============================================================================
-- Flotteo - Schéma complet de la base de données
-- MySQL / MariaDB - utf8mb4
--
-- Fichier unique de référence : il décrit l'état cible complet de la base
-- (toutes les tables, toutes les colonnes, paramètres par défaut). Aucune
-- migration « ALTER TABLE » n'est nécessaire après son application.
--
-- Appliqué par l'assistant d'installation (web ou scripts/install.php) sur une
-- base vide. En manuel :  mysql -u <user> -p <base> < sql/schema.sql
-- ATTENTION : les DROP ci-dessous effacent les tables existantes.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS releves_odometre;
DROP TABLE IF EXISTS sauvegardes;
DROP TABLE IF EXISTS incidents_fichiers;
DROP TABLE IF EXISTS incidents;
DROP TABLE IF EXISTS maintenances_fichiers;
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
    logo VARCHAR(64) NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marques_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modeles (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    marque_id INT UNSIGNED NOT NULL,
    nom       VARCHAR(120) NOT NULL,
    photo     VARCHAR(64) NULL DEFAULT NULL,
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
    couleur               VARCHAR(50) NULL DEFAULT NULL,
    motorisation          ENUM('essence','diesel','electrique','hybride') NULL DEFAULT NULL,
    type_boite            ENUM('mecanique','automatique') NOT NULL DEFAULT 'mecanique',
    entite_id             INT UNSIGNED NOT NULL,
    loueur_id             INT UNSIGNED NOT NULL,
    lieu_id               INT UNSIGNED NOT NULL,
    date_entree           DATE NOT NULL,
    date_sortie_prevue    DATE NOT NULL,
    date_sortie_effective DATE NULL,
    statut                ENUM('actif','immobilise','sorti') NOT NULL DEFAULT 'actif',
    immatricule           TINYINT(1) NOT NULL DEFAULT 0,
    kilometrage           INT UNSIGNED NOT NULL DEFAULT 0,
    commentaire           TEXT NULL,
    duree_contrat        SMALLINT UNSIGNED NULL,
    km_maxi              INT UNSIGNED NULL,
    hayon                TINYINT(1) NOT NULL DEFAULT 0,
    temps_controle_hayon         SMALLINT UNSIGNED NULL DEFAULT NULL,
    date_dernier_controle_hayon  DATE NULL DEFAULT NULL,
    date_prochain_controle_hayon DATE NULL DEFAULT NULL,
    sortie_reelle                DATE NULL,
    sortie_angelus       DATE NULL,
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

-- Pièces jointes d'une prestation d'entretien : factures, devis, rapports de
-- contrôle, photographs. Meme structure que `incidents_fichiers` afin que les
-- deux modules se lisent de facon identique.
--
-- `nom_fichier` ne contient que le nom sur disque : le repertoire de stockage
-- est decide par le code (public/uploads/maintenances), jamais deduit d'une
-- donnee saisie. La suppression de la prestation emporte ses fichiers (CASCADE
-- sur la cle etrangere) ; le retrait du fichier sur disque est traite par le
-- modele, la base ne portant que des metadonnees.
CREATE TABLE maintenances_fichiers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    maintenance_id  INT UNSIGNED NOT NULL,
    nom_fichier     VARCHAR(80) NOT NULL,
    nom_original    VARCHAR(190) NOT NULL,
    mime            VARCHAR(80) NOT NULL,
    taille          INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fichiers_maintenance (maintenance_id),
    CONSTRAINT fk_fichiers_maintenance FOREIGN KEY (maintenance_id) REFERENCES maintenances (id) ON DELETE CASCADE
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

-- --- Sauvegardes -------------------------------------------------------------
-- Historique des archives produites par le module de sauvegarde.
--
-- La ligne est écrite dès l'archive obtenue sur le disque local, puis complétée
-- du verdict d'externalisation : une sauvegarde locale réussie dont le transfert
-- Samba a échoué reste en base et doit être visible comme telle. La taille est
-- stockée en octets (INT UNSIGNED plafonné à 4 Go) ; l'empreinte SHA-256 permet
-- de vérifier l'intégrité de l'archive après transport réseau.
--
-- `fichier` ne contient que le nom de fichier : le répertoire de stockage est
-- décidé par le code (storage/backups), jamais déduit d'une donnée saisie.
CREATE TABLE sauvegardes (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fichier           VARCHAR(190) NOT NULL,
    taille            INT UNSIGNED NOT NULL DEFAULT 0,
    empreinte         CHAR(64) NULL,
    tables_dump       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    fichiers_inclus   INT UNSIGNED NOT NULL DEFAULT 0,
    samba_statut      ENUM('non_configure','reussi','echec') NOT NULL DEFAULT 'non_configure',
    samba_message     VARCHAR(255) NULL,
    samba_at          DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sauvegardes_fichier (fichier),
    KEY idx_sauvegardes_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Déclarations et relevés kilométriques -----------------------------------
CREATE TABLE releves_odometre (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicule_id     INT UNSIGNED NOT NULL,
    periode         VARCHAR(7) NOT NULL, -- Format 'YYYY-MM'
    index_km        INT UNSIGNED NOT NULL,
    distance_mois   INT NOT NULL DEFAULT 0,
    statut          ENUM('saisi', 'verrouille') NOT NULL DEFAULT 'saisi',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_releve_vehicule_periode (vehicule_id, periode),
    KEY idx_releve_periode (periode),
    KEY idx_releve_vehicule (vehicule_id),
    CONSTRAINT fk_releve_vehicule FOREIGN KEY (vehicule_id) REFERENCES vehicules (id) ON DELETE CASCADE
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
    ('smtp_password', ''),
    ('sauvegarde_retention_jours', '30'),
    ('sauvegarde_partage_actif', '0'),
    ('samba_hote', ''),
    ('samba_partage', ''),
    ('samba_repertoire', ''),
    ('samba_utilisateur', ''),
    ('samba_mot_de_passe', ''),
    ('samba_domaine', ''),
    ('km_recurrence', 'mensuelle'),
    ('km_destinataire_type', 'gestionnaire'),
    ('km_email_fixe', ''),
    ('km_delai_jours_relance', '3'),
    ('km_relance_active', '1'),
    ('depot_github', '');

INSERT INTO types_intervention (libelle, categorie) VALUES
    ('Révision constructeur', 'constructeur'),
    ('Vidange / filtres', 'constructeur'),
    ('Pneumatiques', 'pneumatique'),
    ('Freinage', 'freinage'),
    ('Contrôle technique', 'controle'),
    ('Carrosserie', 'carrosserie');
