-- =============================================================================
-- Flotteo - Jeu de données de démonstration
-- FICHIER GÉNÉRÉ : ne pas éditer à la main.
-- Source : scripts/seed/Data.php   Générateur : php scripts/seed_demo.php --sql
--
-- Usage : mysql -u <utilisateur> -p <base> < sql/demodata.sql
--
-- ATTENTION : ce jeu écrase les données existantes et installe des mots de
-- passe connus. Il est réservé au développement et à la recette.
--
-- Comptes de démonstration, mot de passe commun pour les trois :
--     Password123!               (à changer impérativement hors développement)
--   admin@flotteo.local        rôle administration
--   gestion@flotteo.local      rôle modification
--   consultation@flotteo.local  rôle lecture_seule
--
-- Les clés étrangères sont désactivées pendant la purge puis réactivées :
-- TRUNCATE réinitialise aussi les compteurs AUTO_INCREMENT.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE incidents_fichiers;
TRUNCATE TABLE incidents;
TRUNCATE TABLE maintenances;
TRUNCATE TABLE vehicules;
TRUNCATE TABLE types_intervention;
TRUNCATE TABLE lieux;
TRUNCATE TABLE loueurs;
TRUNCATE TABLE modeles;
TRUNCATE TABLE marques;
TRUNCATE TABLE entites;
TRUNCATE TABLE utilisateurs;
TRUNCATE TABLE parametres;

SET FOREIGN_KEY_CHECKS = 1;


-- --- Utilisateurs de démonstration -----------------------------------------
-- Mot de passe en clair : Password123!
-- Algorithme : Argon2id (password_hash)
--
INSERT INTO utilisateurs (id, nom, email, password_hash, role, actif) VALUES (1, 'Sarah Kellner', 'admin@flotteo.local', '$argon2id$v=19$m=65536,t=4,p=1$OVFjL2s3ODl2OW11V29ybA$lb6XwmyOatzajat3G/1sB8Hjzf3/Zku3ksmHr5F4n0E', 'administration', 1);
INSERT INTO utilisateurs (id, nom, email, password_hash, role, actif) VALUES (2, 'Marc Delaunay', 'gestion@flotteo.local', '$argon2id$v=19$m=65536,t=4,p=1$OVFjL2s3ODl2OW11V29ybA$lb6XwmyOatzajat3G/1sB8Hjzf3/Zku3ksmHr5F4n0E', 'modification', 1);
INSERT INTO utilisateurs (id, nom, email, password_hash, role, actif) VALUES (3, 'Léa Brissot', 'consultation@flotteo.local', '$argon2id$v=19$m=65536,t=4,p=1$OVFjL2s3ODl2OW11V29ybA$lb6XwmyOatzajat3G/1sB8Hjzf3/Zku3ksmHr5F4n0E', 'lecture_seule', 1);

-- --- Marques et modèles ------------------------------------------------
INSERT INTO marques (id, nom) VALUES
    (1, 'Renault'),
    (2, 'Peugeot'),
    (3, 'Citroën'),
    (4, 'Volkswagen'),
    (5, 'Toyota'),
    (6, 'Tesla'),
    (7, 'Dacia'),
    (8, 'Mercedes-Benz');

INSERT INTO modeles (id, marque_id, nom) VALUES
    (1, 1, 'Clio V'),
    (2, 1, 'Kangoo II'),
    (3, 1, 'Master III'),
    (4, 2, '208'),
    (5, 2, '308'),
    (6, 2, 'Partner III'),
    (7, 3, 'Berlingo III'),
    (8, 3, 'Jumpy III'),
    (9, 4, 'Transporter T6'),
    (10, 4, 'Golf VIII'),
    (11, 5, 'Proace'),
    (12, 6, 'Model 3'),
    (13, 7, 'Dokker'),
    (14, 8, 'Sprinter');

INSERT INTO entites (id, nom, code) VALUES
    (1, 'Flotteo France SAS', 'FR'),
    (2, 'Flotteo Logistique', 'LOG'),
    (3, 'Flotteo Mobilité', 'MOB');

INSERT INTO loueurs (id, nom, contact_email, telephone) VALUES
    (1, 'Arval', 'parc@arval.example', '+33 1 55 00 40 00'),
    (2, 'ALD Automotive', 'flotte@aldauto.example', '+33 4 78 00 60 00'),
    (3, 'LeasePlan', 'gest.lease@leaseplan.example', '+33 2 40 20 20 20'),
    (4, 'BNP Paribas Fleet Services', 'flotte@bnppfs.example', '+33 1 42 31 40 00');

INSERT INTO lieux (id, nom, ville) VALUES
    (1, 'Dépôt Nord — Lesquin', 'Lille'),
    (2, 'Agence Île-de-France', 'Paris'),
    (3, 'Dépôt Sud — Vitrolles', 'Marseille'),
    (4, 'Base Rhône-Alpes', 'Lyon'),
    (5, 'Hub Ouest — Saint-Herblain', 'Nantes'),
    (6, 'Agence Corse — Ajaccio', 'Ajaccio');

INSERT INTO types_intervention (id, libelle, categorie, actif) VALUES
    (1, 'Révision constructeur', 'constructeur', 1),
    (2, 'Vidange / filtres', 'constructeur', 1),
    (3, 'Pneumatiques', 'pneumatique', 1),
    (4, 'Freinage', 'freinage', 1),
    (5, 'Contrôle technique', 'controle', 1),
    (6, 'Carrosserie', 'carrosserie', 1);

-- --- Paramétrage système ------------------------------------------------
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
    ('jeu_donnees_demo', '1');

-- --- Parc de véhicules (15) ---------------------------------------------
-- date_sortie_prevue est calculée à l'import : les paliers d'alerte
-- 90 / 180 / 270 jours (parametres.alerte_palier_1..3) restent atteints.
-- Le dernier groupe de l'immatriculation est le code département (2A = Corse).
INSERT INTO vehicules
    (id, immatriculation, modele_id, entite_id, loueur_id, lieu_id,
     date_entree, date_sortie_prevue, date_sortie_effective, statut, immatricule, commentaire)
VALUES
    (1, 'FT-427-75', 1, 1, 1, 2, '2023-03-14', '2027-01-01', NULL, 'actif', 0, 'Véhicule de liaison, basé à Paris. Fin de contrat Arval au palier 90 jours.'),
    (2, 'GT-812-59', 4, 1, 2, 1, '2022-11-08', '2027-04-01', NULL, 'actif', 0, 'Compacte de déplacement. Échéance ALD Automotive au palier 180 jours.'),
    (3, 'EK-304-13', 7, 2, 3, 3, '2021-09-22', '2027-06-30', NULL, 'actif', 0, 'Fourgon logistique sous contrat LeasePlan. Palier 270 jours.'),
    (4, 'FR-561-69', 3, 2, 3, 4, '2022-02-18', '2027-04-21', NULL, 'immobilise', 0, 'Immobilisé : boîte de vitesses à remplacer, pièce en commande.'),
    (5, 'BW-238-44', 11, 3, 4, 5, '2024-01-22', '2026-11-17', NULL, 'immobilise', 0, 'Immobilisé : corrosion du plancher arrière, expertise en cours.'),
    (6, 'GT-813-75', 9, 3, 2, 2, '2021-06-07', '2026-08-29', '2026-09-05', 'sorti', 0, 'Restitué à ALD Automotive le mois dernier, bon état.'),
    (7, 'BW-239-44', 10, 1, 3, 5, '2022-08-17', '2026-09-23', NULL, 'actif', 0, 'Échéance dépassée : restitution bloquée par un litige de facturation.'),
    (8, 'FT-428-59', 2, 2, 1, 1, '2023-01-10', '2026-10-15', NULL, 'actif', 0, 'Alerte immédiate : fin de contrat dans moins de trente jours.'),
    (9, 'EK-305-13', 8, 2, 2, 3, '2022-07-30', '2026-12-02', NULL, 'actif', 0, 'Fourgon d''équipe, échéance trimestrielle.'),
    (10, 'FR-562-69', 12, 1, 4, 4, '2023-06-19', '2026-12-17', NULL, 'actif', 0, 'Véhicule électrique de direction, recharge sur site.'),
    (11, 'EK-306-13', 6, 2, 1, 3, '2023-02-28', '2027-03-02', NULL, 'actif', 0, 'Fourgon utilitaire, tournée régionale.'),
    (12, 'BW-240-44', 5, 2, 3, 5, '2022-10-11', '2027-08-09', NULL, 'actif', 0, 'Compacte affectée au service commercial.'),
    (13, 'FT-429-59', 13, 3, 2, 1, '2023-11-03', '2028-02-15', NULL, 'actif', 1, 'Non immatriculé : usage interne sur site fermé.'),
    (14, 'HN-119-2A', 14, 3, 4, 6, '2023-09-14', '2027-11-07', NULL, 'actif', 0, 'Loué à un client en location longue durée, agence Corse.'),
    (15, 'HN-120-2A', 2, 3, 1, 6, '2024-03-05', '2028-06-14', NULL, 'actif', 0, 'Second véhicule de l''agence Corse, location commercialisée.');

-- --- Historique d'entretien (22 opérations) ------------------------------
INSERT INTO maintenances
    (vehicule_id, type_intervention_id, date_operation, kilometrage, cout_ht, cout_ttc, commentaire)
VALUES
    (1, 1, '2025-10-03', 68200, 385.40, 462.48, 'Révision 60000 km'),
    (1, 3, '2026-04-06', 74100, 412.90, 495.48, 'Montage 4 x 205/55 R16'),
    (1, 5, '2026-08-19', 81300, 118.00, 141.60, 'Contrôle technique, visite favorable'),
    (2, 2, '2026-06-30', 52300, 198.50, 238.20, 'Vidange moteur et filtres'),
    (2, 4, '2026-03-07', 44100, 276.80, 332.16, 'Plaquettes avant, contrôle des disques'),
    (2, 5, '2026-09-03', 55600, 118.00, 141.60, 'Contrôle technique, visite favorable'),
    (3, 1, '2026-05-06', 148900, 742.10, 890.52, 'Révision 150000 km et remise à zéro des voyants'),
    (3, 3, '2026-07-25', 158200, 534.60, 641.52, 'Pneumatiques avant et arrière'),
    (3, 6, '2025-08-29', 141300, 1240.00, 1488.00, 'Reprise de tôle après choc de pare-choc'),
    (4, 2, '2026-08-04', 176400, 224.70, 269.64, 'Vidange et filtre à carburant'),
    (4, 4, '2026-06-05', 168900, 892.30, 1070.76, 'Kit embrayage complet'),
    (5, 1, '2026-07-15', 91400, 408.60, 490.32, 'Révision 90000 km'),
    (5, 3, '2026-02-25', 78600, 398.00, 477.60, 'Pneumatiques été'),
    (6, 1, '2026-01-16', 214600, 812.30, 974.76, 'Révision 210000 km avant restitution'),
    (6, 5, '2026-03-27', 216400, 118.00, 141.60, 'Contrôle technique, visite favorable'),
    (7, 2, '2026-08-14', 129800, 205.40, 246.48, 'Vidange et filtre à huile'),
    (7, 3, '2025-12-07', 108700, 472.90, 567.48, 'Pneumatiques été'),
    (8, 2, '2026-09-03', 143200, 215.70, 258.84, 'Vidange moteur'),
    (8, 4, '2026-05-16', 131000, 244.90, 293.88, 'Plaquettes avant et contrôle des disques'),
    (9, 1, '2024-07-25', 162700, 698.70, 838.44, 'Révision 160000 km'),
    (9, 6, '2026-09-18', 174500, 356.00, 427.20, 'Retouche de peinture, porte latérale'),
    (11, 2, '2026-07-25', 98700, 188.90, 226.68, 'Vidange et filtres'),
    (12, 3, '2026-07-05', 74300, 455.80, 546.96, 'Pneumatiques 4 x 205/60 R16');

-- --- Incidents et sinistres (7) ------------------------------------------
INSERT INTO incidents
    (id, vehicule_id, type, date_incident, lieu, responsable, immatricule, statut, description)
VALUES
    (1, 6, 'accident', '2026-08-19', 'A1, aire de Compiègne', 0, 0, 'cloture', 'Collision lors d''un dépassement par un poids lourd. Pare-choc avant et radiateur endommagés, constat amiable signé, franchise réglée par l''assureur adverse. Véhicule immobilisé six jours.'),
    (2, 4, 'panne', '2026-06-05', 'Base Rhône-Alpes, atelier', 0, 0, 'en_traitement', 'Défaut de boîte de vitesses : patins usés et impossibilité de passer en troisième. Diagnostic terminé, boîte de remplacement en commande, véhicule immobilisé.'),
    (3, 1, 'bris_glace', '2026-09-15', 'Dépôt Nord, quai 2', 0, 0, 'cloture', 'Impacts de gravier sur le pare-brise à la suite d''une chaussée salée. Remplacement du pare-brise, aucun impact sur la caméra de recul.'),
    (4, 12, 'accident', '2026-09-24', 'Rond-point D178 / D323, Nantes', 1, 0, 'en_traitement', 'Choc arrière en chaîne impliquant deux véhicules tiers. Le pare-choc et les feux arrière sont endommagés, convocation de l''assureur en cours.'),
    (5, 13, 'vandalisme', '2026-09-29', 'Hub Ouest, parking VL', 0, 1, 'ouvert', 'Brise-vitre du poste de conduite sur véhicule non immatriculé. Dépôt de plainte en cours, remplacement de vitre en attente.'),
    (6, 8, 'panne', '2026-10-01', 'Dépôt Nord, atelier', 0, 0, 'ouvert', 'Voyant moteur et voyant stop allumés au retour de tournée. Véhicule remorqué, diagnostic en cours.'),
    (7, 15, 'autre', '2026-10-02', 'Agence Corse, Ajaccio', 0, 0, 'ouvert', 'Rayures profondes sur le flanc droit constatées lors du contrôle hebdomadaire. Aucun témoin identifié, photos prises sur place.');

-- --- Pièces jointes (14) ------------------------------------------------
-- Les fichiers eux-mêmes ne sont pas fournis : seules leurs métadonnées
-- sont enregistrées, conformément au modèle de données.
INSERT INTO incidents_fichiers
    (id, incident_id, nom_fichier, nom_original, mime, taille)
VALUES
    (1, 1, '2025-c62f1a9b.pdf', 'Facture-carrosserie-Delcourt.pdf', 'application/pdf', 290816),
    (2, 1, '2025-c62f1ac4.jpg', 'constat-amiable-camion-2025.jpg', 'image/jpeg', 1886208),
    (3, 1, '2025-c62f1b10.jpg', 'degats-par-choc-avant.jpg', 'image/jpeg', 1232896),
    (4, 2, '2025-c7a44e02.pdf', 'Devis-boite-vitesses-Perret.pdf', 'application/pdf', 159744),
    (5, 2, '2025-c7a44f37.jpg', 'boite-vitesses-deposee.jpg', 'image/jpeg', 2365440),
    (6, 3, '2025-c8b21d88.pdf', 'Facture-pare-brise-remplace.pdf', 'application/pdf', 100352),
    (7, 4, '2025-c9d47b15.pdf', 'Devis-reparation-garage-Nantes.pdf', 'application/pdf', 176128),
    (8, 4, '2025-c9d47c02.jpg', 'choc-arriere-feux.jpg', 'image/jpeg', 1713152),
    (9, 4, '2025-c9d47d88.jpg', 'constat-place-rond-point.jpg', 'image/jpeg', 2141184),
    (10, 5, '2025-ca12e6f0.jpg', 'pare-brise-casse.jpg', 'image/jpeg', 1489920),
    (11, 5, '2025-ca12e712.pdf', 'declaration-prejudice-casse.pdf', 'application/pdf', 218112),
    (12, 6, '2025-cb33f10a.jpg', 'voyant-moteur-tableau-bord.jpg', 'image/jpeg', 911360),
    (13, 7, '2025-cb44a2c9.jpg', 'rayures-flanc-droit.jpg', 'image/jpeg', 1146880),
    (14, 7, '2025-cb44b3d1.jpg', 'rayures-flanc-avant.jpg', 'image/jpeg', 1021952);

