<?php
declare(strict_types=1);

namespace Services;

use Core\Url;
use Models\Maintenance;
use Models\Vehicle;

/**
 * Constitution des événements de l'agenda.
 *
 * L'agenda réunit trois sources qui n'ont ni la même nature ni le même degré de
 * certitude, et il serait malhonnête de les présenter de la même façon :
 *
 *  - **échéance** : `vehicules.date_sortie_prevue` est une date réelle, contractuelle ;
 *  - **révision** : `maintenances.date_operation` est une date réelle, mais celle
 *    d'une intervention **déjà réalisée**. Le schéma ne porte ni périodicité ni
 *    date de prochaine révision (`0` occurrence de `periodicite` ou
 *    `prochaine_revision` dans tout le dépôt) : l'agenda ne peut donc afficher
 *    que l'historique, et le dit ;
 *  - **immobilisation** : `vehicules.statut = 'immobilise'` est un **état**, sans
 *    aucune date — ni début, ni fin prévue, ni fin réelle, la table
 *    `immobilisations` n'existe pas. L'événement est donc ancré sur la date de
 *    consultation et marqué « en cours », ce qui revient à dire la vérité : on ne
 *    sait pas depuis quand, on sait seulement que c'est le cas aujourd'hui.
 *
 * Aucune donnée n'est mise en cache ni recalculée à l'affichage : chaque
 * navigation dans le calendrier reconstruit la plage affichée, sur le même
 * modèle que le reste de l'application.
 *
 * **Format des événements.** FullCalendar v7 a retiré `backgroundColor`,
 * `borderColor` et `textColor`, et ne lit plus `classNames` mais `className`,
 * attendu sous forme de chaîne. Un seul `color` tient lieu d'aplat et de
 * bordure, la couleur du texte venant de la classe CSS. Le tout est vérifié sur
 * la page servie : un `classNames` passé en tableau n'aurait produit aucun style,
 * sans la moindre erreur.
 */
final class AgendaService
{
    /** Familles d'événements, dans l'ordre d'affichage de la légende. */
    public const TYPES = [
        'echeance'    => 'Échéances',
        'revision'    => 'Révisions',
        'immobilise'  => 'Immobilisations',
    ];

    /**
     * Teintes par famille.
     *
     * Reprises des variables de `flotteo.css` pour que l'agenda et le reste de
     * l'interface parlent du même bleu, du même orange et du même rouge.
     */
    private const COULEURS = [
        'echeance'   => '#f76707',  // --flotteo-warning
        'revision'   => '#206bc4',  // --flotteo-accent
        'immobilise' => '#d63939',  // --flotteo-danger
    ];

    /** Seuil sous lequel une échéance passe au rouge, comme sur l'échéancier. */
    private const JOURS_CRITIQUES = 30;

    /**
     * Événements de la plage `[du, au]` au format attendu par FullCalendar.
     *
     * @param list<string> $types Familles retenues ; vide signifie « toutes ».
     * @return list<array<string, mixed>>
     */
    public static function evenements(string $du, string $au, array $types = []): array
    {
        $retenues = $types === [] ? array_keys(self::TYPES) : $types;
        $evenements = [];

        if (in_array('echeance', $retenues, true)) {
            $evenements = array_merge($evenements, self::echeances($du, $au));
        }

        if (in_array('revision', $retenues, true)) {
            $evenements = array_merge($evenements, self::revisions($du, $au));
        }

        if (in_array('immobilise', $retenues, true)) {
            $evenements = array_merge($evenements, self::immobilisations());
        }

        // FullCalendar accepte les événements dans n'importe quel ordre ; le
        // tri par date rend la charge utile lisible au débogage, et évite
        // que deux événements d'un même jour se chevauchent dans un ordre
        // imprévisible d'une requête à l'autre.
        usort(
            $evenements,
            static fn (array $a, array $b): int => [$a['start'], $a['id']] <=> [$b['start'], $b['id']]
        );

        return $evenements;
    }

    /**
     * Échéances de fin de contrat.
     *
     * La teinte suit l'urgence, avec les mêmes paliers que la page
     * « Échéances » : sous 30 jours en rouge, puis orange jusqu'à 90, jaune
     * jusqu'à 180, bleu au-delà.
     *
     * @return list<array<string, mixed>>
     */
    private static function echeances(string $du, string $au): array
    {
        $evenements = [];

        foreach (Vehicle::echeancesEntre($du, $au) as $vehicule) {
            $reste = (int) Vehicle::joursRestants($vehicule);

            $evenements[] = [
                'id'            => 'echeance-' . (int) $vehicule['id'],
                'title'         => 'Échéance · ' . $vehicule['immatriculation'],
                'start'         => (string) $vehicule['date_sortie_prevue'],
                'allDay'        => true,
                'url'           => self::ficheVehicule((int) $vehicule['id']),
                'color'      => self::teinteEcheance($reste),
                'className'  => 'flotteo-evenement flotteo-evenement-echeance',
                'extendedProps' => [
                    'type'     => 'echeance',
                    'famille'  => 'Échéance',
                    'vehicule' => (string) $vehicule['immatriculation'],
                    'detail'   => trim(sprintf(
                        '%s %s — %s, fin de contrat dans %d jour%s',
                        $vehicule['marque_nom'],
                        $vehicule['modele_nom'],
                        $vehicule['loueur_nom'],
                        abs($reste),
                        abs($reste) > 1 ? 's' : ''
                    )),
                    'jours'    => $reste,
                ],
            ];
        }

        return $evenements;
    }

    /**
     * Révisions : interventions **réalisées**.
     *
     * @return list<array<string, mixed>>
     */
    private static function revisions(string $du, string $au): array
    {
        $evenements = [];

        foreach (Maintenance::search(['du' => $du, 'au' => $au]) as $entretien) {
            $couleur = self::COULEURS['revision'];

            $evenements[] = [
                'id'            => 'revision-' . (int) $entretien['id'],
                'title'         => $entretien['type_libelle'] . ' · ' . $entretien['immatriculation'],
                'start'         => (string) $entretien['date_operation'],
                'allDay'        => true,
                'url'           => self::ficheVehicule((int) $entretien['vehicule_id']),
                'color'      => $couleur,
                'className'  => 'flotteo-evenement flotteo-evenement-revision',
                'extendedProps' => [
                    'type'     => 'revision',
                    'famille'  => 'Révision',
                    'vehicule' => (string) $entretien['immatriculation'],
                    'detail'   => trim(sprintf(
                        '%s %s — %s, %s km',
                        $entretien['marque_nom'],
                        $entretien['modele_nom'],
                        $entretien['type_libelle'],
                        number_format((float) $entretien['kilometrage'], 0, ',', ' ')
                    )),
                    'jours'    => null,
                ],
            ];
        }

        return $evenements;
    }

    /**
     * Immobilisations en cours.
     *
     * Le schéma ne date pas une immobilisation : l'événement est donc placé le
     * jour de la consultation. Il est marqué comme tel dans sa légende, faute de
     * quoi il se lirait comme un début d'immobilisation.
     *
     * @return list<array<string, mixed>>
     */
    private static function immobilisations(): array
    {
        $evenements = [];
        $aujourdhui = date('Y-m-d');
        $couleur    = self::COULEURS['immobilise'];

        foreach (Vehicle::search(['statut' => 'immobilise']) as $vehicule) {
            $motif = trim((string) ($vehicule['commentaire'] ?? ''));
            // `commentaire` porte le motif d'immobilisation dans le jeu de
            // démonstration, mais il est aussi le champ libre du véhicule : on
            // n'affiche donc que ce qui suit le mot « immobilisé », et rien du
            // reste du commentaire.
            $motif = (string) preg_replace('/^.*immobili[sz]é[e]?\s*:?\s*/iu', '', $motif);
            if ($motif === (string) ($vehicule['commentaire'] ?? '')) {
                $motif = '';
            }

            $evenements[] = [
                'id'            => 'immobilise-' . (int) $vehicule['id'],
                'title'         => 'Immobilisé · ' . $vehicule['immatriculation'],
                'start'         => $aujourdhui,
                'allDay'        => true,
                'url'           => self::ficheVehicule((int) $vehicule['id']),
                'color'      => $couleur,
                'className'  => 'flotteo-evenement flotteo-evenement-immobilise',
                'extendedProps' => [
                    'type'     => 'immobilise',
                    'famille'  => 'Immobilisation en cours',
                    'vehicule' => (string) $vehicule['immatriculation'],
                    'detail'   => trim(sprintf(
                        '%s %s — immobilisé%s',
                        $vehicule['marque_nom'],
                        $vehicule['modele_nom'],
                        $motif !== '' ? ' : ' . $motif : ''
                    )),
                    'jours'    => null,
                ],
            ];
        }

        return $evenements;
    }

    /**
     * Adresse de la fiche d'un véhicule.
     *
     * Le calendrier est la seule page où un événement n'a pas de ligne
     * associée : FullCalendar rend alors l'événement en lien, ce qui conserve le
     * ctrl-clic, l'ouverture dans un nouvel onglet et le menu contextuel du
     * navigateur, autant de comportements qu'un lien ordinaire.
     */
    private static function ficheVehicule(int $id): string
    {
        return Url::to('/vehicules/voir?id=' . $id);
    }

    /** Teinte d'une échéance selon l'urgence, alignée sur la page « Échéances ». */
    private static function teinteEcheance(int $reste): string
    {
        return match (true) {
            $reste <= self::JOURS_CRITIQUES => self::COULEURS['immobilise'],
            $reste <= 90                    => self::COULEURS['echeance'],
            $reste <= 180                   => '#f59f00',
            default                         => '#5b7c99',
        };
    }

    /**
     * Effectifs par famille, pour les cartes de synthèse.
     *
     * Le comptage se fait sur les événements de la plage affichée, de sorte que
     * les cartes concordent avec ce qui est réellement à l'écran.
     *
     * @param  list<array<string, mixed>> $evenements
     * @return array<string, int>
     */
    public static function compter(array $evenements): array
    {
        $compte = array_fill_keys(array_keys(self::TYPES), 0);

        foreach ($evenements as $evenement) {
            $type = (string) ($evenement['extendedProps']['type'] ?? '');
            if (isset($compte[$type])) {
                $compte[$type]++;
            }
        }

        return $compte;
    }
}