-- Migration : Ajout du profil Comptabilité dans l'ENUM role de la table utilisateurs
ALTER TABLE `utilisateurs` 
    MODIFY COLUMN `role` ENUM('lecture_seule','comptabilite','documentaliste','modification','administration') NOT NULL DEFAULT 'lecture_seule';

