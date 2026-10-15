-- Migration Flotteo : Expiration semestrielle (6 mois) des mots de passe et rappel 1 mois avant
ALTER TABLE `utilisateurs` 
  ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME NULL DEFAULT NULL AFTER `doit_changer_mot_de_passe`;

-- Initialiser les comptes existants qui n'ont pas encore de date
UPDATE `utilisateurs` 
  SET `password_changed_at` = COALESCE(`created_at`, NOW()) 
  WHERE `password_changed_at` IS NULL;

