-- Migration Flotteo : Module Incidents — Gestion de l'entité et de la prise en charge assurance
--
-- 1. Ajout des colonnes entité et assurance sur la table incidents
-- 2. Indexation pour les recherches et les filtres de remboursement
-- 3. Rétrocompatibilité : initialisation des incidents existants avec l'entité de leur véhicule

ALTER TABLE `incidents`
  ADD COLUMN IF NOT EXISTS `entite_id` INT UNSIGNED NULL DEFAULT NULL AFTER `vehicule_id`,
  ADD COLUMN IF NOT EXISTS `entite` VARCHAR(140) NULL DEFAULT NULL AFTER `entite_id`,
  ADD COLUMN IF NOT EXISTS `assurance_statut` ENUM('non_prise_en_charge', 'prise_en_charge') NULL DEFAULT NULL AFTER `statut`,
  ADD COLUMN IF NOT EXISTS `remboursement_attendu` TINYINT(1) NOT NULL DEFAULT 0 AFTER `assurance_statut`,
  ADD COLUMN IF NOT EXISTS `montant_rembourse` DECIMAL(10,2) NULL DEFAULT NULL AFTER `remboursement_attendu`,
  ADD COLUMN IF NOT EXISTS `montant_franchise` DECIMAL(10,2) NULL DEFAULT NULL AFTER `montant_rembourse`,
  ADD COLUMN IF NOT EXISTS `remboursement_percu` TINYINT(1) NOT NULL DEFAULT 0 AFTER `montant_franchise`;

-- Indexation optimisée
ALTER TABLE `incidents`
  ADD INDEX IF NOT EXISTS `idx_incidents_entite` (`entite_id`),
  ADD INDEX IF NOT EXISTS `idx_incidents_assurance` (`assurance_statut`, `remboursement_attendu`, `remboursement_percu`);

-- Rétrocompatibilité : initialiser entite_id et entite depuis le véhicule associé pour les incidents existants
UPDATE `incidents` i
JOIN `vehicules` v ON v.id = i.vehicule_id
JOIN `entites` e ON e.id = v.entite_id
SET i.entite_id = v.entite_id, i.entite = e.nom
WHERE i.entite_id IS NULL;

