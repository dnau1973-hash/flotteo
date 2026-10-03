<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\Database;
use Core\Flash;
use Core\Logger;
use Core\Mailer;
use Core\Request;
use Core\SmtpClient;

/**
 * Paramétrage système de l'application.
 *
 * La page est découpée en sections servies par une sous-navigation verticale ;
 * chaque section possède son propre envoi, ce qui évite qu l'enregistrement de
 * la messagerie n'écrase les alertes et réciproquement.
 *
 * Sections :
 *  - `messagerie` : mode d'envoi (frontal natif ou serveur SMTP), paramètres du
 *    serveur, authentification, adresse expéditrice et test d'envoi ;
 *  - `alertes`    : activation, paliers d'anticipation, destinataire des rappels,
 *    envoi manuel et tâche planifiée.
 */
final class ParamController extends Controller
{
    /** Section servie lorsqu'aucune n'est demandée ou que la demande est inconnue. */
    private const SECTION_DEFAUT = 'messagerie';

    /**
     * Sections de la page et clés qu'elles détiennent.
     *
     * La répartition des clés est structurante : `save()` n'écrit que celles de
     * la section visée, jamais toutes.
     *
     * @var array<string, array{libelle: string, icone: string, resume: string, cles: string[]}>
     */
    private const SECTIONS = [
        'messagerie' => [
            'libelle' => 'Messagerie',
            'icone'   => 'envelope',
            'resume'  => 'Mode d\'envoi, serveur SMTP et adresse expéditrice.',
            'cles'    => [
                'mail_transport', 'smtp_host', 'smtp_port', 'smtp_chiffrement',
                'smtp_user', 'smtp_password', 'email_expediteur',
            ],
        ],
        'alertes' => [
            'libelle' => 'Alertes',
            'icone'   => 'bell',
            'resume'  => 'Paliers d\'anticipation et destinataire des rappels.',
            'cles'    => [
                'email_gestionnaire', 'alerte_palier_1', 'alerte_palier_2',
                'alerte_palier_3', 'alerte_active',
            ],
        ],
    ];

    /** Valeurs appliquées à une clé absente de la base. */
    private const DEFAUTS = [
        'mail_transport'    => 'mail',
        'smtp_host'         => '',
        'smtp_port'         => '587',
        'smtp_chiffrement'  => 'tls',
        'smtp_user'         => '',
        'smtp_password'     => '',
        'email_expediteur'  => 'no-reply@flotteo.local',
        'email_gestionnaire' => '',
        'alerte_palier_1'   => '90',
        'alerte_palier_2'   => '180',
        'alerte_palier_3'   => '270',
        'alerte_active'     => '1',
    ];

    /** Section demandée, ramenée à une valeur exploitable. */
    private function section(): string
    {
        $sectione = (string) Request::param('section', '');

        return isset(self::SECTIONS[$sectione]) ? $sectione : self::SECTION_DEFAUT;
    }

    public function index(): void
    {
        $this->guard(Auth::ROLE_ADMIN);
        $section = $this->section();

        if ($section === 'messagerie') {
            // Le pied de page préfixe déjà « js/ » (Url::asset('js/' . $script)) :
            // le nom transmis doit donc être relatif à ce dossier.
            $this->view()->useScript('parametres.js');
        }

        $this->render('admin/parametres', [
            'base_url' => $this->baseUrl(),
            'sections' => self::SECTIONS,
            'section'  => $section,
            'valeurs'  => $this->valeurs(),
            'paliers'  => \Services\AlertService::paliers(),
        ]);
    }

    /**
     * Enregistrement d'une section (POST).
     *
     * Seules les clés de la section demandée sont écrites : un envoi partiel ne
     * peut donc pas neutraliser une autre section.
     */
    public function save(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);
        $section = $this->section();

        try {
            $ecrites = [];
            $stockees = $this->valeurs();
            foreach (self::SECTIONS[$section]['cles'] as $cle) {
                $valeur = $this->valeurAEnregistrer($cle, $stockees);

                $anomalie = self::controler($cle, $valeur);
                if ($anomalie !== null) {
                    Flash::add('danger', $anomalie);
                    $this->redirect('/admin/parametres/' . $section);
                }

                $this->ecrire($cle, $valeur);
                $ecrites[$cle] = $valeur;
            }

            Flash::add('success', 'Paramètres « ' . self::SECTIONS[$section]['libelle'] . ' » enregistrés.');
        } catch (\PDOException $e) {
            Logger::error('Enregistrement des paramètres impossible', $e);
            Flash::add('danger', 'Enregistrement des paramètres impossible.');
        }

        $this->redirect('/admin/parametres/' . $section);
    }

    /**
     * Valeur à persister pour une clé, après traitement propre à la clé.
     *
     * Deux cas ne se lisent pas dans le formulaire lui-même :
     *  - `alerte_active` est un interrupteur : une case décochée n'est pas
     *    soumise du tout, l'absence vaut donc désactivation ;
     *  - `smtp_password` n'est jamais renvoyé par le formulaire. Un champ laissé
     *    vide conserve le mot de passe enregistré ; son effacement est une
     *    demande explicite, portée par la case à cocher dédiée.
     *
     * @param array<string, string> $stockees Valeurs actuellement en base
     */
    private function valeurAEnregistrer(string $cle, array $stockees): string
    {
        if ($cle === 'alerte_active') {
            return Request::input('alerte_active', '') === '1' ? '1' : '0';
        }

        if ($cle === 'smtp_password') {
            if ((string) Request::input('smtp_password_effacer', '') === '1') {
                return '';
            }
            $saisi = (string) Request::input('smtp_password', '');

            return $saisi !== '' ? $saisi : ($stockees['smtp_password'] ?? '');
        }

        return mb_substr((string) Request::input($cle, ''), 0, 190);
    }

    /**
     * Contrôle de validité d'une valeur.
     *
     * @return string|null Message d'anomalie, ou null si la valeur est recevable.
     */
    private static function controler(string $cle, string $valeur): ?string
    {
        if ($cle === 'email_expediteur') {
            if ($valeur === '') {
                return 'Renseignez l\'adresse expéditrice : aucun message ne peut partir sans elle.';
            }
            if (!filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
                return 'L\'adresse expéditrice n\'est pas une adresse valide.';
            }
        }

        if ($cle === 'email_gestionnaire') {
            if ($valeur !== '' && !filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
                return 'L\'adresse du gestionnaire destinataire n\'est pas valide.';
            }
        }

        if ($cle === 'mail_transport') {
            if (!in_array($valeur, Mailer::TRANSPORTS, true)) {
                return 'Mode d\'envoi inconnu : choisissez ' . self::listeDe(Mailer::TRANSPORTS) . '.';
            }
        }

        if ($cle === 'smtp_chiffrement') {
            if (!in_array($valeur, SmtpClient::CHIFFREMENTS, true)) {
                return 'Mode de chiffrement inconnu : choisissez ' . self::listeDe(SmtpClient::CHIFFREMENTS) . '.';
            }
        }

        if ($cle === 'smtp_port') {
            $port = (int) $valeur;
            if ($port < 1 || $port > 65535) {
                return 'Le port du serveur SMTP doit être compris entre 1 et 65535.';
            }
        }

        if (str_starts_with($cle, 'alerte_palier_')) {
            if ($valeur !== '' && (!ctype_digit($valeur) || (int) $valeur > 3650)) {
                return 'Un palier d\'anticipation doit être un nombre de jours compris entre 1 et 3650.';
            }
        }

        return null;
    }

    /**
     * Énumère des valeurs en français pour un message d'erreur.
     *
     * « a », « a ou b », « a, b ou c » : l'accord du dernier élément change avec
     * le nombre d'options, qu'un simple assemblage ne restitue pas.
     *
     * @param string[] $valeurs
     */
    private static function listeDe(array $valeurs): string
    {
        $guillemets = array_map(static fn (string $v): string => '« ' . $v . ' »', $valeurs);
        $dernier = array_pop($guillemets);

        if ($guillemets === []) {
            return (string) $dernier;
        }

        return implode(', ', $guillemets) . ' ou ' . $dernier;
    }

    /**
     * Test d'envoi (POST JSON).
     *
     * Le serveur est éprouvé avec les valeurs actuellement saisies au formulaire,
     * et non avec celles enregistrées : l'utilisateur peut ainsi valider une
     * configuration avant de la persiste. Seul le mot de passe retombe sur la
     * valeur enregistrée, puisqu'il n'est jamais renvoyé par le formulaire.
     */
    public function testerCourriel(): void
    {
        if (!Csrf::check()) {
            $this->ko('Jeton de sécurité invalide. Rechargez la page.', 419);
        }
        if (!Auth::can(Auth::ROLE_ADMIN)) {
            $this->ko('Accès réservé aux administrateurs.', 403);
        }

        $destinataire = mb_substr((string) Request::input('test_destinataire', ''), 0, 190);
        if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            $this->ko('Renseignez une adresse de test valide.', 422);
        }

        $expediteur = mb_substr((string) Request::input('email_expediteur', ''), 0, 190);
        if (!filter_var($expediteur, FILTER_VALIDATE_EMAIL)) {
            $this->ko('Renseignez une adresse expéditrice valide avant de tester l\'envoi.', 422);
        }

        $motDePasse = (string) Request::input('smtp_password', '');
        if ($motDePasse === '') {
            $motDePasse = (string) $this->valeur('smtp_password', '');
        }

        try {
            $client = new SmtpClient(
                mb_substr((string) Request::input('smtp_host', ''), 0, 190),
                (int) Request::input('smtp_port', '587'),
                mb_substr((string) Request::input('smtp_chiffrement', 'tls'), 0, 20),
                mb_substr((string) Request::input('smtp_user', ''), 0, 190),
                $motDePasse
            );
        } catch (\InvalidArgumentException $e) {
            $this->ko($e->getMessage(), 422);
        }

        $conf = (require dirname(__DIR__) . '/config/config.php');
        $client->send(
            $expediteur,
            $destinataire,
            'Test d\'envoi — ' . $conf['app']['nom'],
            '<p>Ce message confirme que la messagerie de <strong>' . htmlspecialchars($conf['app']['nom'], ENT_QUOTES, 'UTF-8')
            . '</strong> est correctement configurée.</p>'
            . '<p>Il a été émis le ' . date('d/m/Y à H:i') . ' depuis un envoi déclenché manuellement.</p>'
        );

        if ($client->lastError() !== '') {
            $this->ko('Échec de l\'envoi de test : ' . $client->lastError(), 422);
        }

        $this->ok('Message de test envoyé à ' . $destinataire . '.');
    }

    /** Déclenchement manuel du récapitulatif (POST). */
    public function runAlerts(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);

        if ((int) Mailer::param('alerte_active', '1') !== 1) {
            Flash::add('warning', 'Les alertes sont désactivées dans le paramétrage.');
            $this->redirect('/admin/parametres/alertes');
        }

        $resultat = \Services\AlertService::notifier();
        Flash::add($resultat['envoi'] ? 'success' : 'warning', $resultat['message']);
        $this->redirect('/admin/parametres/alertes');
    }

    /** @return array<string, string> */
    private function valeurs(): array
    {
        $valeurs = self::DEFAUTS;
        foreach (Database::all('SELECT cle, valeur FROM parametres') as $ligne) {
            $valeurs[(string) $ligne['cle']] = (string) $ligne['valeur'];
        }

        return $valeurs;
    }

    private function valeur(string $cle, string $defaut): string
    {
        return (string) Mailer::param($cle, $defaut);
    }

    private function ecrire(string $cle, string $valeur): void
    {
        Database::run(
            'INSERT INTO parametres (cle, valeur) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valeur = :v2',
            ['c' => $cle, 'v' => $valeur, 'v2' => $valeur]
        );
    }
}