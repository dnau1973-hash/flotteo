<?php
declare(strict_types=1);

namespace Seed;

/**
 * Jeu de données de démonstration Flotteo.
 *
 * Les données sont décrites en PHP afin de permettre un contrôle de cohérence
 * avant toute injection (cf. Data::verifier()). Les dates sont exprimées en
 * décalages de jours relatifs à la date du jour : les fixtures restent ainsi
 * réalistes dans le temps et les paliers d'alerte (90 / 180 / 270 jours)
 * demeurent atteints, quelle que soit la date d'exécution.
 *
 * Marché français : les immatriculations respectent le format SIV, soit deux
 * caractères, trois chiffres, puis le code du département — numérique, ou 2A/2B
 * pour la Corse — correspondant au lieu d'exploitation du véhicule.
 */
final class Data
{
    /** Paliers d'alerte de fin de contrat, en jours (cf. table parametres). */
    public const PALIERS = [90, 180, 270];

    /** Valeurs autorisées par les ENUM de sql/schema.sql. */
    private const STATUTS_VEHICULE = ['actif', 'immobilise', 'sorti'];
    private const CATEGORIES       = ['constructeur', 'pneumatique', 'freinage', 'controle', 'carrosserie', 'autre'];
    private const TYPES_INCIDENT   = ['accident', 'panne', 'vandalisme', 'bris_glace', 'autre'];
    private const STATUTS_INCIDENT = ['ouvert', 'en_traitement', 'cloture'];
    private const ROLES            = ['lecture_seule', 'modification', 'administration'];

    /** Motif SIV : 2 lettres, 3 chiffres, code département (numérique ou 2A/2B). */
    private const MOTIF_SIV = '/^[A-Z]{2}-\d{3}-(\d{2}|2A|2B)$/';

    /** TVA française applicable aux prestations d'entretien. */
    public const TVA = 1.20;

    // ------------------------------------------------------------- utilisateurs

    /** @return list<array{id:int,nom:string,email:string,role:string,actif:int}> */
    public static function utilisateurs(): array
    {
        return [
            ['id' => 1, 'nom' => 'Sarah Kellner', 'email' => 'admin@flotteo.local',       'role' => 'administration', 'actif' => 1],
            ['id' => 2, 'nom' => 'Marc Delaunay',  'email' => 'gestion@flotteo.local',    'role' => 'modification',    'actif' => 1],
            ['id' => 3, 'nom' => 'Léa Brissot',    'email' => 'consultation@flotteo.local', 'role' => 'lecture_seule', 'actif' => 1],
        ];
    }

    // -------------------------------------------------------------- référentiels

    /** @return list<array{id:int,nom:string}> */
    public static function marques(): array
    {
        $noms = ['Renault', 'Peugeot', 'Citroën', 'Volkswagen', 'Toyota', 'Tesla', 'Dacia', 'Mercedes-Benz'];

        return array_map(
            static fn (string $nom, int $index): array => ['id' => $index + 1, 'nom' => $nom],
            $noms,
            array_keys($noms)
        );
    }

    /** @return list<array{id:int,marque_id:int,nom:string}> */
    public static function modeles(): array
    {
        return [
            ['id' => 1,  'marque_id' => 1, 'nom' => 'Clio V'],
            ['id' => 2,  'marque_id' => 1, 'nom' => 'Kangoo II'],
            ['id' => 3,  'marque_id' => 1, 'nom' => 'Master III'],
            ['id' => 4,  'marque_id' => 2, 'nom' => '208'],
            ['id' => 5,  'marque_id' => 2, 'nom' => '308'],
            ['id' => 6,  'marque_id' => 2, 'nom' => 'Partner III'],
            ['id' => 7,  'marque_id' => 3, 'nom' => 'Berlingo III'],
            ['id' => 8,  'marque_id' => 3, 'nom' => 'Jumpy III'],
            ['id' => 9,  'marque_id' => 4, 'nom' => 'Transporter T6'],
            ['id' => 10, 'marque_id' => 4, 'nom' => 'Golf VIII'],
            ['id' => 11, 'marque_id' => 5, 'nom' => 'Proace'],
            ['id' => 12, 'marque_id' => 6, 'nom' => 'Model 3'],
            ['id' => 13, 'marque_id' => 7, 'nom' => 'Dokker'],
            ['id' => 14, 'marque_id' => 8, 'nom' => 'Sprinter'],
        ];
    }

    /** @return list<array{id:int,nom:string,code:string}> */
    public static function entites(): array
    {
        return [
            ['id' => 1, 'nom' => 'Flotteo France SAS', 'code' => 'FR'],
            ['id' => 2, 'nom' => 'Flotteo Logistique', 'code' => 'LOG'],
            ['id' => 3, 'nom' => 'Flotteo Mobilité',   'code' => 'MOB'],
        ];
    }

    /** @return list<array{id:int,nom:string,contact_email:string,telephone:string}> */
    public static function loueurs(): array
    {
        return [
            ['id' => 1, 'nom' => 'Arval',                      'contact_email' => 'parc@arval.example',         'telephone' => '+33 1 55 00 40 00'],
            ['id' => 2, 'nom' => 'ALD Automotive',             'contact_email' => 'flotte@aldauto.example',     'telephone' => '+33 4 78 00 60 00'],
            ['id' => 3, 'nom' => 'LeasePlan',                  'contact_email' => 'gest.lease@leaseplan.example', 'telephone' => '+33 2 40 20 20 20'],
            ['id' => 4, 'nom' => 'BNP Paribas Fleet Services', 'contact_email' => 'flotte@bnppfs.example',       'telephone' => '+33 1 42 31 40 00'],
        ];
    }

    /**
     * Lieux d'exploitation, avec le code département servant à l'immatriculation.
     *
     * @return list<array{id:int,nom:string,ville:string,departement:string}>
     */
    public static function lieux(): array
    {
        return [
            ['id' => 1, 'nom' => 'Dépôt Nord — Lesquin',       'ville' => 'Lille',    'departement' => '59'],
            ['id' => 2, 'nom' => 'Agence Île-de-France',       'ville' => 'Paris',    'departement' => '75'],
            ['id' => 3, 'nom' => 'Dépôt Sud — Vitrolles',      'ville' => 'Marseille', 'departement' => '13'],
            ['id' => 4, 'nom' => 'Base Rhône-Alpes',            'ville' => 'Lyon',     'departement' => '69'],
            ['id' => 5, 'nom' => 'Hub Ouest — Saint-Herblain', 'ville' => 'Nantes',   'departement' => '44'],
            ['id' => 6, 'nom' => 'Agence Corse — Ajaccio',      'ville' => 'Ajaccio',  'departement' => '2A'],
        ];
    }

    /** @return list<array{id:int,libelle:string,categorie:string}> */
    public static function typesIntervention(): array
    {
        return [
            ['id' => 1, 'libelle' => 'Révision constructeur', 'categorie' => 'constructeur'],
            ['id' => 2, 'libelle' => 'Vidange / filtres',    'categorie' => 'constructeur'],
            ['id' => 3, 'libelle' => 'Pneumatiques',          'categorie' => 'pneumatique'],
            ['id' => 4, 'libelle' => 'Freinage',              'categorie' => 'freinage'],
            ['id' => 5, 'libelle' => 'Contrôle technique',   'categorie' => 'controle'],
            ['id' => 6, 'libelle' => 'Carrosserie',           'categorie' => 'carrosserie'],
        ];
    }

    /** @return list<array{cle:string,valeur:string}> */
    public static function parametres(): array
    {
        return [
            ['cle' => 'email_gestionnaire', 'valeur' => 'gestion@flotteo.local'],
            ['cle' => 'email_expediteur',   'valeur' => 'no-reply@flotteo.local'],
            ['cle' => 'alerte_palier_1',    'valeur' => '90'],
            ['cle' => 'alerte_palier_2',    'valeur' => '180'],
            ['cle' => 'alerte_palier_3',    'valeur' => '270'],
            ['cle' => 'alerte_active',      'valeur' => '1'],
            ['cle' => 'mail_transport',     'valeur' => 'mail'],
            ['cle' => 'smtp_host',          'valeur' => ''],
            ['cle' => 'smtp_port',          'valeur' => '587'],
            ['cle' => 'smtp_chiffrement',   'valeur' => 'tls'],
            ['cle' => 'smtp_user',          'valeur' => ''],
            ['cle' => 'smtp_password',      'valeur' => ''],
            ['cle' => 'sauvegarde_retention_jours', 'valeur' => '30'],
            ['cle' => 'sauvegarde_partage_actif',    'valeur' => '0'],
            ['cle' => 'samba_hote',                 'valeur' => ''],
            ['cle' => 'samba_partage',              'valeur' => ''],
            ['cle' => 'samba_repertoire',           'valeur' => ''],
            ['cle' => 'samba_utilisateur',          'valeur' => ''],
            ['cle' => 'samba_mot_de_passe',         'valeur' => ''],
            ['cle' => 'samba_domaine',              'valeur' => ''],
            ['cle' => 'jeu_donnees_demo',   'valeur' => '1'],
        ];
    }

    // ----------------------------------------------------------------- véhicules

    /**
     * Parc de 15 véhicules.
     *
     * `jours` compte les jours entre aujourd'hui et la date de sortie prévue ;
     * une valeur négative signale une échéance déjà dépassée.
     *
     * @return list<array<string, mixed>>
     */
    public static function vehicules(): array
    {
        return [
            // -- Paliers d'alerte atteints exactement : 90, 180 et 270 jours ----
            ['id' => 1, 'immat' => 'FT-427-75', 'modele_id' => 1, 'entite_id' => 1, 'loueur_id' => 1, 'lieu_id' => 2,
             'entree' => '2023-03-14', 'jours' => 90, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Véhicule de liaison, basé à Paris. Fin de contrat Arval au palier 90 jours.'],

            ['id' => 2, 'immat' => 'GT-812-59', 'modele_id' => 4, 'entite_id' => 1, 'loueur_id' => 2, 'lieu_id' => 1,
             'entree' => '2022-11-08', 'jours' => 180, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Compacte de déplacement. Échéance ALD Automotive au palier 180 jours.'],

            ['id' => 3, 'immat' => 'EK-304-13', 'modele_id' => 7, 'entite_id' => 2, 'loueur_id' => 3, 'lieu_id' => 3,
             'entree' => '2021-09-22', 'jours' => 270, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Fourgon logistique sous contrat LeasePlan. Palier 270 jours.'],

            // -- Immobilisé : en panne -----------------------------------------
            ['id' => 4, 'immat' => 'FR-561-69', 'modele_id' => 3, 'entite_id' => 2, 'loueur_id' => 3, 'lieu_id' => 4,
             'entree' => '2022-02-18', 'jours' => 200, 'sortie_effective' => null, 'statut' => 'immobilise', 'immatricule' => 0,
             'commentaire' => 'Immobilisé : boîte de vitesses à remplacer, pièce en commande.'],

            ['id' => 5, 'immat' => 'BW-238-44', 'modele_id' => 11, 'entite_id' => 3, 'loueur_id' => 4, 'lieu_id' => 5,
             'entree' => '2024-01-22', 'jours' => 45, 'sortie_effective' => null, 'statut' => 'immobilise', 'immatricule' => 0,
             'commentaire' => 'Immobilisé : corrosion du plancher arrière, expertise en cours.'],

            // -- Restitué -------------------------------------------------------
            ['id' => 6, 'immat' => 'GT-813-75', 'modele_id' => 9, 'entite_id' => 3, 'loueur_id' => 2, 'lieu_id' => 2,
             'entree' => '2021-06-07', 'jours' => -35, 'sortie_effective' => -28, 'statut' => 'sorti', 'immatricule' => 0,
             'commentaire' => 'Restitué à ALD Automotive le mois dernier, bon état.'],

            // -- Échéance dépassée, restitution bloquée par un litige -----------
            ['id' => 7, 'immat' => 'BW-239-44', 'modele_id' => 10, 'entite_id' => 1, 'loueur_id' => 3, 'lieu_id' => 5,
             'entree' => '2022-08-17', 'jours' => -10, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Échéance dépassée : restitution bloquée par un litige de facturation.'],

            // -- Sous le palier 90 jours ---------------------------------------
            ['id' => 8, 'immat' => 'FT-428-59', 'modele_id' => 2, 'entite_id' => 2, 'loueur_id' => 1, 'lieu_id' => 1,
             'entree' => '2023-01-10', 'jours' => 12, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Alerte immédiate : fin de contrat dans moins de trente jours.'],

            ['id' => 9, 'immat' => 'EK-305-13', 'modele_id' => 8, 'entite_id' => 2, 'loueur_id' => 2, 'lieu_id' => 3,
             'entree' => '2022-07-30', 'jours' => 60, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Fourgon d\'équipe, échéance trimestrielle.'],

            ['id' => 10, 'immat' => 'FR-562-69', 'modele_id' => 12, 'entite_id' => 1, 'loueur_id' => 4, 'lieu_id' => 4,
             'entree' => '2023-06-19', 'jours' => 75, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Véhicule électrique de direction, recharge sur site.'],

            // -- Parc courant ---------------------------------------------------
            ['id' => 11, 'immat' => 'EK-306-13', 'modele_id' => 6, 'entite_id' => 2, 'loueur_id' => 1, 'lieu_id' => 3,
             'entree' => '2023-02-28', 'jours' => 150, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Fourgon utilitaire, tournée régionale.'],

            ['id' => 12, 'immat' => 'BW-240-44', 'modele_id' => 5, 'entite_id' => 2, 'loueur_id' => 3, 'lieu_id' => 5,
             'entree' => '2022-10-11', 'jours' => 310, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Compacte affectée au service commercial.'],

            ['id' => 13, 'immat' => 'FT-429-59', 'modele_id' => 13, 'entite_id' => 3, 'loueur_id' => 2, 'lieu_id' => 1,
             'entree' => '2023-11-03', 'jours' => 500, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 1,
             'commentaire' => 'Non immatriculé : usage interne sur site fermé.'],

            // -- Loué à un client tiers ------------------------------------------
            ['id' => 14, 'immat' => 'HN-119-2A', 'modele_id' => 14, 'entite_id' => 3, 'loueur_id' => 4, 'lieu_id' => 6,
             'entree' => '2023-09-14', 'jours' => 400, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Loué à un client en location longue durée, agence Corse.'],

            ['id' => 15, 'immat' => 'HN-120-2A', 'modele_id' => 2, 'entite_id' => 3, 'loueur_id' => 1, 'lieu_id' => 6,
             'entree' => '2024-03-05', 'jours' => 620, 'sortie_effective' => null, 'statut' => 'actif', 'immatricule' => 0,
             'commentaire' => 'Second véhicule de l\'agence Corse, location commercialisée.'],
        ];
    }

    // -------------------------------------------------------------- maintenances

    /**
     * Historique d'entretien : 22 opérations réparties sur l'ensemble du parc.
     * `jours` est le décalage de la date d'opération par rapport à aujourd'hui.
     *
     * @return list<array<string, mixed>>
     */
    public static function maintenances(): array
    {
        return [
            ['vehicule_id' => 1, 'type_id' => 1, 'jours' => -365, 'km' => 68200, 'ht' => 385.40, 'motif' => 'Révision 60000 km'],
            ['vehicule_id' => 1, 'type_id' => 3, 'jours' => -180, 'km' => 74100, 'ht' => 412.90, 'motif' => 'Montage 4 x 205/55 R16'],
            ['vehicule_id' => 1, 'type_id' => 5, 'jours' => -45, 'km' => 81300, 'ht' => 118.00, 'motif' => 'Contrôle technique, visite favorable'],

            ['vehicule_id' => 2, 'type_id' => 2, 'jours' => -95, 'km' => 52300, 'ht' => 198.50, 'motif' => 'Vidange moteur et filtres'],
            ['vehicule_id' => 2, 'type_id' => 4, 'jours' => -210, 'km' => 44100, 'ht' => 276.80, 'motif' => 'Plaquettes avant, contrôle des disques'],
            ['vehicule_id' => 2, 'type_id' => 5, 'jours' => -30, 'km' => 55600, 'ht' => 118.00, 'motif' => 'Contrôle technique, visite favorable'],

            ['vehicule_id' => 3, 'type_id' => 1, 'jours' => -150, 'km' => 148900, 'ht' => 742.10, 'motif' => 'Révision 150000 km et remise à zéro des voyants'],
            ['vehicule_id' => 3, 'type_id' => 3, 'jours' => -70, 'km' => 158200, 'ht' => 534.60, 'motif' => 'Pneumatiques avant et arrière'],
            ['vehicule_id' => 3, 'type_id' => 6, 'jours' => -400, 'km' => 141300, 'ht' => 1240.00, 'motif' => 'Reprise de tôle après choc de pare-choc'],

            ['vehicule_id' => 4, 'type_id' => 2, 'jours' => -60, 'km' => 176400, 'ht' => 224.70, 'motif' => 'Vidange et filtre à carburant'],
            ['vehicule_id' => 4, 'type_id' => 4, 'jours' => -120, 'km' => 168900, 'ht' => 892.30, 'motif' => 'Kit embrayage complet'],

            ['vehicule_id' => 5, 'type_id' => 1, 'jours' => -80, 'km' => 91400, 'ht' => 408.60, 'motif' => 'Révision 90000 km'],
            ['vehicule_id' => 5, 'type_id' => 3, 'jours' => -220, 'km' => 78600, 'ht' => 398.00, 'motif' => 'Pneumatiques été'],

            ['vehicule_id' => 6, 'type_id' => 1, 'jours' => -260, 'km' => 214600, 'ht' => 812.30, 'motif' => 'Révision 210000 km avant restitution'],
            ['vehicule_id' => 6, 'type_id' => 5, 'jours' => -190, 'km' => 216400, 'ht' => 118.00, 'motif' => 'Contrôle technique, visite favorable'],

            ['vehicule_id' => 7, 'type_id' => 2, 'jours' => -50, 'km' => 129800, 'ht' => 205.40, 'motif' => 'Vidange et filtre à huile'],
            ['vehicule_id' => 7, 'type_id' => 3, 'jours' => -300, 'km' => 108700, 'ht' => 472.90, 'motif' => 'Pneumatiques été'],

            ['vehicule_id' => 8, 'type_id' => 2, 'jours' => -30, 'km' => 143200, 'ht' => 215.70, 'motif' => 'Vidange moteur'],
            ['vehicule_id' => 8, 'type_id' => 4, 'jours' => -140, 'km' => 131000, 'ht' => 244.90, 'motif' => 'Plaquettes avant et contrôle des disques'],

            ['vehicule_id' => 9, 'type_id' => 1, 'jours' => -800, 'km' => 162700, 'ht' => 698.70, 'motif' => 'Révision 160000 km'],
            ['vehicule_id' => 9, 'type_id' => 6, 'jours' => -15, 'km' => 174500, 'ht' => 356.00, 'motif' => 'Retouche de peinture, porte latérale'],

            ['vehicule_id' => 11, 'type_id' => 2, 'jours' => -70, 'km' => 98700, 'ht' => 188.90, 'motif' => 'Vidange et filtres'],
            ['vehicule_id' => 12, 'type_id' => 3, 'jours' => -90, 'km' => 74300, 'ht' => 455.80, 'motif' => 'Pneumatiques 4 x 205/60 R16'],
        ];
    }

    // ----------------------------------------------------------------- incidents

    /** @return list<array<string, mixed>> */
    public static function incidents(): array
    {
        return [
            ['id' => 1, 'vehicule_id' => 6, 'type' => 'accident', 'jours' => -45, 'lieu' => 'A1, aire de Compiègne',
             'responsable' => 0, 'immatricule' => 0, 'statut' => 'cloture',
             'description' => 'Collision lors d\'un dépassement par un poids lourd. Pare-choc avant et radiateur endommagés, '
                           . 'constat amiable signé, franchise réglée par l\'assureur adverse. Véhicule immobilisé six jours.'],

            ['id' => 2, 'vehicule_id' => 4, 'type' => 'panne', 'jours' => -120, 'lieu' => 'Base Rhône-Alpes, atelier',
             'responsable' => 0, 'immatricule' => 0, 'statut' => 'en_traitement',
             'description' => 'Défaut de boîte de vitesses : patins usés et impossibilité de passer en troisième. '
                           . 'Diagnostic terminé, boîte de remplacement en commande, véhicule immobilisé.'],

            ['id' => 3, 'vehicule_id' => 1, 'type' => 'bris_glace', 'jours' => -18, 'lieu' => 'Dépôt Nord, quai 2',
             'responsable' => 0, 'immatricule' => 0, 'statut' => 'cloture',
             'description' => 'Impacts de gravier sur le pare-brise à la suite d\'une chaussée salée. '
                           . 'Remplacement du pare-brise, aucun impact sur la caméra de recul.'],

            ['id' => 4, 'vehicule_id' => 12, 'type' => 'accident', 'jours' => -9, 'lieu' => 'Rond-point D178 / D323, Nantes',
             'responsable' => 1, 'immatricule' => 0, 'statut' => 'en_traitement',
             'description' => 'Choc arrière en chaîne impliquant deux véhicules tiers. Le pare-choc et les feux '
                           . 'arrière sont endommagés, convocation de l\'assureur en cours.'],

            ['id' => 5, 'vehicule_id' => 13, 'type' => 'vandalisme', 'jours' => -4, 'lieu' => 'Hub Ouest, parking VL',
             'responsable' => 0, 'immatricule' => 1, 'statut' => 'ouvert',
             'description' => 'Brise-vitre du poste de conduite sur véhicule non immatriculé. '
                           . 'Dépôt de plainte en cours, remplacement de vitre en attente.'],

            ['id' => 6, 'vehicule_id' => 8, 'type' => 'panne', 'jours' => -2, 'lieu' => 'Dépôt Nord, atelier',
             'responsable' => 0, 'immatricule' => 0, 'statut' => 'ouvert',
             'description' => 'Voyant moteur et voyant stop allumés au retour de tournée. '
                           . 'Véhicule remorqué, diagnostic en cours.'],

            ['id' => 7, 'vehicule_id' => 15, 'type' => 'autre', 'jours' => -1, 'lieu' => 'Agence Corse, Ajaccio',
             'responsable' => 0, 'immatricule' => 0, 'statut' => 'ouvert',
             'description' => 'Rayures profondes sur le flanc droit constatées lors du contrôle hebdomadaire. '
                           . 'Aucun témoin identifié, photos prises sur place.'],
        ];
    }

    /**
     * Pièces jointes fictives rattachées aux incidents : factures et photos.
     * Les fichiers eux-mêmes ne sont pas créés ; seules leurs métadonnées sont
     * injectées, conformément au modèle de données.
     *
     * @return list<array<string, mixed>>
     */
    public static function fichiers(): array
    {
        return [
            ['id' => 1,  'incident_id' => 1, 'fichier' => '2025-c62f1a9b.pdf', 'original' => 'Facture-carrosserie-Delcourt.pdf',  'mime' => 'application/pdf', 'ko' => 284],
            ['id' => 2,  'incident_id' => 1, 'fichier' => '2025-c62f1ac4.jpg', 'original' => 'constat-amiable-camion-2025.jpg',    'mime' => 'image/jpeg',      'ko' => 1842],
            ['id' => 3,  'incident_id' => 1, 'fichier' => '2025-c62f1b10.jpg', 'original' => 'degats-par-choc-avant.jpg',          'mime' => 'image/jpeg',      'ko' => 1204],

            ['id' => 4,  'incident_id' => 2, 'fichier' => '2025-c7a44e02.pdf', 'original' => 'Devis-boite-vitesses-Perret.pdf',    'mime' => 'application/pdf', 'ko' => 156],
            ['id' => 5,  'incident_id' => 2, 'fichier' => '2025-c7a44f37.jpg', 'original' => 'boite-vitesses-deposee.jpg',         'mime' => 'image/jpeg',      'ko' => 2310],

            ['id' => 6,  'incident_id' => 3, 'fichier' => '2025-c8b21d88.pdf', 'original' => 'Facture-pare-brise-remplace.pdf',     'mime' => 'application/pdf', 'ko' => 98],

            ['id' => 7,  'incident_id' => 4, 'fichier' => '2025-c9d47b15.pdf', 'original' => 'Devis-reparation-garage-Nantes.pdf',  'mime' => 'application/pdf', 'ko' => 172],
            ['id' => 8,  'incident_id' => 4, 'fichier' => '2025-c9d47c02.jpg', 'original' => 'choc-arriere-feux.jpg',               'mime' => 'image/jpeg',      'ko' => 1673],
            ['id' => 9,  'incident_id' => 4, 'fichier' => '2025-c9d47d88.jpg', 'original' => 'constat-place-rond-point.jpg',       'mime' => 'image/jpeg',      'ko' => 2091],

            ['id' => 10, 'incident_id' => 5, 'fichier' => '2025-ca12e6f0.jpg', 'original' => 'pare-brise-casse.jpg',               'mime' => 'image/jpeg',      'ko' => 1455],
            ['id' => 11, 'incident_id' => 5, 'fichier' => '2025-ca12e712.pdf', 'original' => 'declaration-prejudice-casse.pdf',    'mime' => 'application/pdf', 'ko' => 213],

            ['id' => 12, 'incident_id' => 6, 'fichier' => '2025-cb33f10a.jpg', 'original' => 'voyant-moteur-tableau-bord.jpg',     'mime' => 'image/jpeg',      'ko' => 890],

            ['id' => 13, 'incident_id' => 7, 'fichier' => '2025-cb44a2c9.jpg', 'original' => 'rayures-flanc-droit.jpg',            'mime' => 'image/jpeg',      'ko' => 1120],
            ['id' => 14, 'incident_id' => 7, 'fichier' => '2025-cb44b3d1.jpg', 'original' => 'rayures-flanc-avant.jpg',            'mime' => 'image/jpeg',      'ko' => 998],
        ];
    }

    // ------------------------------------------------------------------ contrôle

    /**
     * Contrôle de cohérence du jeu de données, sans toucher à la base.
     *
     * @return list<string> anomalies détectées, liste vide si tout est correct
     */
    public static function verifier(): array
    {
        $erreurs = [];

        foreach ([
            self::controlesReferentiels(),
            self::controlesUtilisateurs(),
            self::controlesVehicules(),
            self::controlesPaliers(),
            self::controlesMaintenance(),
            self::controlesIncidents(),
            self::controlesExigences(),
        ] as $lot) {
            foreach ($lot as $message) {
                $erreurs[] = $message;
            }
        }

        return $erreurs;
    }

    /** @return list<string> */
    private static function controlesReferentiels(): array
    {
        $erreurs = [];

        $idsMarques = array_column(self::marques(), 'id');
        foreach (self::modeles() as $modele) {
            if (!in_array($modele['marque_id'], $idsMarques, true)) {
                $erreurs[] = sprintf('Modèle %d : marque_id %d inexistante.', $modele['id'], $modele['marque_id']);
            }
        }

        $referentiels = [
            'modele_id'  => array_column(self::modeles(), 'id'),
            'entite_id'  => array_column(self::entites(), 'id'),
            'loueur_id'  => array_column(self::loueurs(), 'id'),
            'lieu_id'    => array_column(self::lieux(), 'id'),
        ];

        foreach (self::vehicules() as $vehicule) {
            foreach ($referentiels as $colonne => $identifiants) {
                $valeur = $vehicule[$colonne] ?? null;
                if ($valeur === null) {
                    $erreurs[] = sprintf('Véhicule %s : colonne « %s » absente.', $vehicule['immat'], $colonne);
                    continue;
                }
                if (!in_array($valeur, $identifiants, true)) {
                    $erreurs[] = sprintf('Véhicule %s : %s %s inexistant.', $vehicule['immat'], $colonne, (string) $valeur);
                }
            }
        }

        $idsTypes     = array_column(self::typesIntervention(), 'id');
        $idsVehicules = array_column(self::vehicules(), 'id');

        foreach (self::maintenances() as $index => $maintenance) {
            $libelle = sprintf('Maintenance n° %d', $index + 1);
            if (!in_array($maintenance['type_id'], $idsTypes, true)) {
                $erreurs[] = $libelle . sprintf(' : type_id %d inexistant.', $maintenance['type_id']);
            }
            if (!in_array($maintenance['vehicule_id'], $idsVehicules, true)) {
                $erreurs[] = $libelle . sprintf(' : vehicule_id %d inexistant.', $maintenance['vehicule_id']);
            }
        }

        $idsIncidents = array_column(self::incidents(), 'id');
        foreach (self::fichiers() as $fichier) {
            if (!in_array($fichier['incident_id'], $idsIncidents, true)) {
                $erreurs[] = sprintf('Fichier %s : incident_id %d inexistant.', $fichier['fichier'], $fichier['incident_id']);
            }
        }

        foreach (self::typesIntervention() as $type) {
            if (!in_array($type['categorie'], self::CATEGORIES, true)) {
                $erreurs[] = sprintf('Type d\'intervention %s : catégorie « %s » hors ENUM.', $type['libelle'], $type['categorie']);
            }
        }

        return $erreurs;
    }

    /** @return list<string> */
    private static function controlesUtilisateurs(): array
    {
        $erreurs = [];
        $emails  = [];

        foreach (self::utilisateurs() as $utilisateur) {
            $libelle = $utilisateur['email'];

            if (!in_array($utilisateur['role'], self::ROLES, true)) {
                $erreurs[] = sprintf('Utilisateur %s : rôle « %s » hors ENUM.', $libelle, $utilisateur['role']);
            }
            if (filter_var($libelle, FILTER_VALIDATE_EMAIL) === false) {
                $erreurs[] = sprintf('Utilisateur %d : adresse e-mail invalide.', $utilisateur['id']);
            }
            if (isset($emails[$libelle])) {
                $erreurs[] = sprintf('Adresse e-mail « %s » en double (contrainte uq_utilisateurs_email).', $libelle);
            }
            $emails[$libelle] = true;

            if ($utilisateur['actif'] !== 1) {
                $erreurs[] = sprintf('Utilisateur %s : fixture attendue active.', $libelle);
            }
        }

        return $erreurs;
    }

    /** @return list<string> */
    private static function controlesVehicules(): array
    {
        $erreurs       = [];
        $departements  = array_column(self::lieux(), 'departement', 'id');
        $immatriculees = [];

        foreach (self::vehicules() as $vehicule) {
            $immat = (string) $vehicule['immat'];

            if (preg_match(self::MOTIF_SIV, $immat, $captures) !== 1) {
                $erreurs[] = sprintf('Immatriculation « %s » : format SIV attendu, soit AA-123-DD.', $immat);
            } elseif (isset($departements[$vehicule['lieu_id']])
                && $captures[1] !== $departements[$vehicule['lieu_id']]) {
                $erreurs[] = sprintf(
                    'Véhicule %s : code département %s incohérent avec son lieu d\'exploitation (%s).',
                    $immat,
                    $captures[1],
                    $departements[$vehicule['lieu_id']]
                );
            }

            if (isset($immatriculees[$immat])) {
                $erreurs[] = sprintf('Immatriculation « %s » en double (contrainte uq_vehicules_immat).', $immat);
            }
            $immatriculees[$immat] = true;

            if (!in_array($vehicule['statut'], self::STATUTS_VEHICULE, true)) {
                $erreurs[] = sprintf('Véhicule %s : statut « %s » hors ENUM.', $immat, $vehicule['statut']);
            }

            // Cohérence entre le statut et les dates de sortie.
            $sorti = $vehicule['statut'] === 'sorti';
            if ($sorti && $vehicule['sortie_effective'] === null) {
                $erreurs[] = sprintf('Véhicule %s : statut « sorti » sans date de sortie effective.', $immat);
            }
            if (!$sorti && $vehicule['sortie_effective'] !== null) {
                $erreurs[] = sprintf('Véhicule %s : date de sortie effective renseignée alors que le statut n\'est pas « sorti ».', $immat);
            }
            // Une restitution en retard par rapport au prévu est un cas métier
            // légitime : seule une sortie effective antérieure à l'entrée au parc
            // serait incohérente.
            if ($sorti && $vehicule['sortie_effective'] !== null && $vehicule['sortie_effective'] < -3650) {
                $erreurs[] = sprintf('Véhicule %s : date de sortie effective incohérente avec l\'entrée au parc.', $immat);
            }

            if ((int) $vehicule['immatricule'] !== 0 && (int) $vehicule['immatricule'] !== 1) {
                $erreurs[] = sprintf('Véhicule %s : le drapeau « immatricule » doit valoir 0 ou 1.', $immat);
            }

            $entree = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $vehicule['entree']);
            if ($entree === false) {
                $erreurs[] = sprintf('Véhicule %s : date d\'entrée illisible.', $immat);
            } elseif ($entree > new \DateTimeImmutable('today')) {
                $erreurs[] = sprintf('Véhicule %s : date d\'entrée postérieure à aujourd\'hui.', $immat);
            }
        }

        return $erreurs;
    }

    /**
     * Vérifie que les cas limites d'alerte e-mail sont bien couverts : un
     * véhicule sortant dans 3, 6 et 9 mois, plus au moins une échéance dépassée.
     *
     * @return list<string>
     */
    private static function controlesPaliers(): array
    {
        $erreurs = [];
        $jours   = array_map(static fn (array $v): int => (int) $v['jours'], self::vehicules());

        foreach (self::PALIERS as $palier) {
            // Tolérance d'un jour : les dates sont recalculées à chaque exécution.
            $trouve = count(array_filter($jours, static fn (int $j): bool => abs($j - $palier) <= 1)) > 0;
            if (!$trouve) {
                $erreurs[] = sprintf('Aucun véhicule ne sort dans %d jours : le palier d\'alerte n\'est pas couvert.', $palier);
            }
        }

        if (array_filter($jours, static fn (int $j): bool => $j < 0) === []) {
            $erreurs[] = 'Aucun véhicule en retard : le cas d\'alerte de retard n\'est pas couvert.';
        }
        if (array_filter($jours, static fn (int $j): bool => $j >= 0 && $j < self::PALIERS[0]) === []) {
            $erreurs[] = 'Aucun véhicule sortant sous 90 jours : le cas d\'alerte immédiate n\'est pas couvert.';
        }

        return $erreurs;
    }

    /** @return list<string> */
    private static function controlesMaintenance(): array
    {
        $erreurs = [];
        $cumuls  = [];

        foreach (self::maintenances() as $index => $maintenance) {
            $libelle = sprintf('Maintenance n° %d (véhicule %d)', $index + 1, $maintenance['vehicule_id']);

            if (!isset($cumuls[$maintenance['vehicule_id']])) {
                $cumuls[$maintenance['vehicule_id']] = ['ht' => 0.0, 'km' => 0];
            }

            if ($maintenance['ht'] <= 0) {
                $erreurs[] = $libelle . ' : coût HT nul ou négatif.';
            }
            if ($maintenance['km'] <= 0) {
                $erreurs[] = $libelle . ' : kilométrage nul.';
            }

            $cumuls[$maintenance['vehicule_id']]['ht'] += (float) $maintenance['ht'];
            $cumuls[$maintenance['vehicule_id']]['km']  = max(
                $cumuls[$maintenance['vehicule_id']]['km'],
                (int) $maintenance['km']
            );
        }

        // Le kilométrage ne peut qu'augmenter au fil du temps. Les opérations
        // sont donc parcourues de la plus ancienne à la plus récente : le décalage
        // le plus négatif correspond à l'intervention la plus ancienne.
        $parVehicule = [];
        foreach (self::maintenances() as $index => $maintenance) {
            $parVehicule[$maintenance['vehicule_id']][] = $maintenance + ['rang' => $index];
        }

        foreach ($parVehicule as $vehicule => $operations) {
            usort($operations, static fn (array $a, array $b): int => $a['jours'] <=> $b['jours']);
            $precedent = null;

            foreach ($operations as $operation) {
                if ($precedent !== null && $operation['km'] < $precedent) {
                    $erreurs[] = sprintf(
                        'Véhicule %d : kilométrage %d incohérent, une opération plus récente (%d km) affiche davantage.',
                        $vehicule,
                        $operation['km'],
                        $precedent
                    );
                }
                $precedent = max($precedent ?? 0, (int) $operation['km']);
            }
        }

        return $erreurs;
    }

    /** @return list<string> */
    private static function controlesIncidents(): array
    {
        $erreurs = [];

        foreach (self::incidents() as $incident) {
            $libelle = sprintf('Incident n° %d', $incident['id']);

            if (!in_array($incident['type'], self::TYPES_INCIDENT, true)) {
                $erreurs[] = $libelle . ' : type « ' . $incident['type'] . ' » hors ENUM.';
            }
            if (!in_array($incident['statut'], self::STATUTS_INCIDENT, true)) {
                $erreurs[] = $libelle . ' : statut « ' . $incident['statut'] . ' » hors ENUM.';
            }
            if (mb_strlen((string) $incident['description']) < 60) {
                $erreurs[] = $libelle . ' : description trop courte pour être exploitable.';
            }
            if (trim((string) $incident['lieu']) === '') {
                $erreurs[] = $libelle . ' : lieu manquant.';
            }
        }

        foreach (self::fichiers() as $fichier) {
            $nom = (string) $fichier['fichier'];

            if (preg_match('/^\d{4}-[0-9a-f]{8}\.(pdf|jpe?g|png)$/i', $nom) !== 1) {
                $erreurs[] = sprintf('Fichier « %s » : nom de stockage non conforme au format attendu.', $nom);
            }
            if (preg_match('/\.(jpe?g|png)$/i', $nom) === 1 && str_starts_with($fichier['mime'], 'application/')) {
                $erreurs[] = sprintf('Fichier « %s » : extension incohérente avec le type MIME %s.', $nom, $fichier['mime']);
            }
            if (str_ends_with(strtolower($nom), '.pdf') && $fichier['mime'] !== 'application/pdf') {
                $erreurs[] = sprintf('Fichier « %s » : extension PDF mais type MIME %s.', $nom, $fichier['mime']);
            }
            if ((int) $fichier['ko'] <= 0) {
                $erreurs[] = sprintf('Fichier « %s » : taille nulle.', $nom);
            }
        }

        // Un incident doit disposer d'au moins une pièce jointe : sans cela la
        // page de détail n'a rien à montrer.
        $incidentsAvecFichier = array_unique(array_column(self::fichiers(), 'incident_id'));
        foreach (self::incidents() as $incident) {
            if (!in_array($incident['id'], $incidentsAvecFichier, true)) {
                $erreurs[] = sprintf('Incident n° %d : aucune pièce jointe rattachée.', $incident['id']);
            }
        }

        return $erreurs;
    }

    /** @return list<string> */
    private static function controlesExigences(): array
    {
        $erreurs = [];

        $exigences = [
            ['utilisateurs', self::utilisateurs(), 3],
            ['véhicules', self::vehicules(), 10],
            ['interventions', self::maintenances(), 15],
            ['incidents', self::incidents(), 5],
            ['entités', self::entites(), 2],
            ['lieux', self::lieux(), 3],
            ['loueurs', self::loueurs(), 3],
            ['fichiers', self::fichiers(), 5],
        ];

        foreach ($exigences as [$nom, $donnees, $minimum]) {
            if (count($donnees) < $minimum) {
                $erreurs[] = sprintf('Au moins %d %s sont requis, %d fournis.', $minimum, $nom, count($donnees));
            }
        }

        if (count(array_unique(array_column(self::utilisateurs(), 'role'))) !== count(self::ROLES)) {
            $erreurs[] = 'Les trois rôles doivent être tous représentés : administration, modification, lecture_seule.';
        }

        foreach (['constructeur', 'pneumatique', 'freinage', 'controle'] as $categorie) {
            if (!in_array($categorie, array_column(self::typesIntervention(), 'categorie'), true)) {
                $erreurs[] = sprintf('Type d\'entretien manquant pour la catégorie « %s ».', $categorie);
            }
        }

        foreach (self::STATUTS_VEHICULE as $statut) {
            if (!in_array($statut, array_column(self::vehicules(), 'statut'), true)) {
                $erreurs[] = sprintf('Statut de véhicule « %s » non représenté dans le parc.', $statut);
            }
        }

        foreach (self::STATUTS_INCIDENT as $statut) {
            if (!in_array($statut, array_column(self::incidents(), 'statut'), true)) {
                $erreurs[] = sprintf('Statut d\'incident « %s » non représenté.', $statut);
            }
        }

        foreach (['Clio V', '208', 'Berlingo III', 'Model 3'] as $modeleAttendu) {
            $noms = array_column(self::modeles(), 'nom');
            if (!in_array($modeleAttendu, $noms, true)) {
                $erreurs[] = sprintf('Modèle « %s » attendu dans le référentiel.', $modeleAttendu);
            }
        }

        foreach (['Arval', 'ALD Automotive', 'LeasePlan'] as $loueurAttendu) {
            if (!in_array($loueurAttendu, array_column(self::loueurs(), 'nom'), true)) {
                $erreurs[] = sprintf('Organisme loueur « %s » attendu.', $loueurAttendu);
            }
        }

        return $erreurs;
    }
}