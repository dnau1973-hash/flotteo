-- Migration : Forcer le renouvellement de mot de passe à la prochaine connexion
-- Date : 2026-10-15

ALTER TABLE utilisateurs
    ADD COLUMN IF NOT EXISTS doit_changer_mot_de_passe TINYINT(1) NOT NULL DEFAULT 0 AFTER actif;

