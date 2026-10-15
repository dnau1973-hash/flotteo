-- Migration : Ajout de la gestion dynamique des catégories d'intervention
-- Date : 2026-10-15

CREATE TABLE IF NOT EXISTS categories_intervention (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code      VARCHAR(50) NOT NULL UNIQUE,
    nom       VARCHAR(100) NOT NULL,
    actif     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE types_intervention MODIFY categorie VARCHAR(80) NOT NULL DEFAULT 'autre';

INSERT IGNORE INTO categories_intervention (code, nom) VALUES
    ('constructeur', 'Entretien constructeur'),
    ('pneumatique', 'Pneumatiques'),
    ('freinage', 'Freinage'),
    ('controle', 'Contrôle technique'),
    ('carrosserie', 'Carrosserie'),
    ('autre', 'Autre');

