-- Migration : Ajout des colonnes km_arrivee et km_fin sur la table vehicules
ALTER TABLE `vehicules`
    ADD COLUMN IF NOT EXISTS `km_arrivee` INT UNSIGNED NULL DEFAULT NULL AFTER `kilometrage`,
    ADD COLUMN IF NOT EXISTS `km_fin` INT UNSIGNED NULL DEFAULT NULL AFTER `km_arrivee`;

