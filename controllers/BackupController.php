<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Models\Backup;
use Services\BackupService;
use Services\SambaClient;

/**
 * Sauvegardes : production d'archive, rotation, externalisation Samba, et accès
 * aux fichiers produits.
 *
 * Toutes les actions sont réservées au rôle *administration* : une archive
 * contient l'intégralité de la base — donc les mots de passe condensés et les
 * adresses des utilisateurs. Aucune action n'est accessible en simple lecture.
 *
 * La configuration du module est enregistrée par `ParamController`, avec les
 * autres paramètres : les clés sont décrites dans `Models\Backup::REGLAGES`. Ce
 * contrôleur ne fait que les **actions**, jamais la persistance des réglages,
 * sauf le test de connexion qui travaille sur des valeurs non encore
 * enregistrées — comme le test d'envoi SMTP.
 */
final class BackupController extends Controller
{
    /** Redirection de retour : la section « Sauvegardes » des paramètres. */
    private const RETOUR = '/admin/parametres/sauvegardes';

    /** Motif du nom d'une archive produite par le service. */
    private const MOTIF_NOM = '/^flotteo_backup_\d{4}-\d{2}-\d{2}_\d{4}(?:_\d+|\d+)?\.zip$/';

    /** Période de carence entre deux sauvegardes, en secondes. */
    private const CARENCE = 30;

    /** Clé de session mémorisant l'instant du dernier envoi. */
    private const CLE_EXECUTION = '_sauvegarde_dernier';

    /**
     * Test de la connexion Samba (POST JSON).
     *
     * Éprouve les valeurs **saisies** et non celles enregistrées : un partage
     * doit pouvoir être validé avant d'être persisté. Le mot de passe retombe
     * sur la valeur enregistrée lorsqu'il est laissé vide, puisqu'il n'est
     * jamais renvoyé par le formulaire — même règle que le SMTP.
     */
    public function testerSamba(): void
    {
        if (!Csrf::check()) {
            $this->ko('Jeton de sécurité invalide. Rechargez la page.', 419);
        }
        if (!Auth::can(Auth::ROLE_ADMIN)) {
            $this->ko('Accès réservé aux administrateurs.', 403);
        }

        $reglages = $this->reglagesSaisis();
        $client   = new SambaClient($reglages);
        $verdict  = $client->tester();

        $verdict['ok']
            ? $this->ok($verdict['message'])
            : $this->ko($verdict['message'], 422);
    }

    /** Sauvegarde immédiate (POST). */
    public function lancer(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);

        // Une sauvegarde est lourde : l'utilisateur peut double-cliquer, et deux
        // exports simultanés de la même base dégradent le service sans rien
        // apporter. La période de carence est communicatee.
        if (time() - self::derniereExecution() < self::CARENCE) {
            Flash::add(
                'warning',
                'Une sauvegarde vient d\'être lancée : patientez ' . self::CARENCE
                . ' secondes avant d\'en déclencher une autre.'
            );
            $this->redirect(self::RETOUR);
        }

        self::marquerExecution();

        $resultat = BackupService::generer();

        if (!$resultat['ok']) {
            Flash::add('danger', $resultat['message']);
            $this->redirect(self::RETOUR);
        }

        // La sauvegarde locale a réussi : le verdict Samba est un élément
        // distinct du message, jamais une cause d'échec global.
        Flash::add('success', $resultat['message']);

        $samba = (string) ($resultat['samba'] ?? 'non_configure');
        if ($samba === 'echec') {
            Flash::add('warning', 'Archive conservée localement, mais la copie vers le partage a échoué : '
                . (string) ($resultat['message'] ?? '') . ' Vérifiez le point de montage ou le binaire smbclient.');
        } elseif ($samba === 'reussi') {
            Flash::add('info', 'Archive copiée sur le partage réseau.');
        }

        $this->redirect(self::RETOUR);
    }

    /** Rotation immédiate (POST). */
    public function purger(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);

        $resultat = BackupService::purger();
        Flash::add($resultat['ok'] ? 'success' : 'danger', $resultat['message']);

        $this->redirect(self::RETOUR);
    }

    /** Suppression d'une archive et de son fichier (POST). */
    public function supprimer(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);

        $ligne = Backup::find(Request::int('id'));
        if ($ligne === null) {
            Flash::add('danger', 'Sauvegarde introuvable.');
            $this->redirect(self::RETOUR);
        }

        $fichier = (string) $ligne['fichier'];
        $chemin  = BackupService::cheminArchive($fichier);

        // Un nom d'archive déjà absent du disque n'est pas un échec : la seule
        // trace restante est la ligne d'historique, qu'il faut retirer.
        if ($chemin !== null && !@unlink($chemin)) {
            Logger::error("Suppression de l'archive $fichier impossible");
            Flash::add('danger', 'Archive verrouillée ou suppression impossible : ' . $fichier);
            $this->redirect(self::RETOUR);
        }

        Backup::supprimer((int) $ligne['id']);
        Logger::info("Sauvegarde supprimee: $fichier");
        Flash::add('success', 'Sauvegarde supprimée : ' . $fichier);

        $this->redirect(self::RETOUR);
    }

    /** Téléchargement d'une archive (GET). */
    public function telecharger(): void
    {
        $this->guard(Auth::ROLE_ADMIN);

        $nom    = basename((string) Request::input('fichier', ''));
        $chemin = BackupService::cheminArchive($nom);
        if ($chemin === null) {
            // Réponse neutre : ni l'existence du répertoire de stockage, ni sa
            // capacité ne doivent être déductibles d'un 404 éloquent.
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Archive introuvable.';
            exit;
        }

        // `basename()` garantit déjà l'absence de séparateur ; le motif
        // refait la vérification sur la forme attendue du nom.
        if (preg_match(self::MOTIF_NOM, $nom) !== 1) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Archive introuvable.';
            exit;
        }

        $taille = (int) @filesize($chemin);
        Logger::info("Telechargement de la sauvegarde $nom");

        header('Content-Type: application/zip');
        header('Content-Length: ' . $taille);
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($chemin);
        exit;
    }

    /** Vérification d'intégrité d'une archive (POST JSON). */
    public function verifier(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);

        $ligne = Backup::find(Request::int('id'));
        if ($ligne === null) {
            $this->ko('Sauvegarde introuvable.', 404);
        }

        $chemin = BackupService::cheminArchive((string) $ligne['fichier']);
        if ($chemin === null) {
            $this->ko('Archive absente du stockage : elle ne peut plus être vérifiée.', 410);
        }

        $attendu = (string) ($ligne['empreinte'] ?? '');
        $calcule = (string) hash_file('sha256', $chemin);

        $this->ok(
            hash_equals($attendu, $calcule)
                ? 'Archive intacte : l\'empreinte correspond à celle relevée à la création.'
                : 'Archive altérée : l\'empreinte calculée diffère de celle relevée à la création.',
            ['empreinte' => $calcule]
        );
    }

    /**
     * Réglages issus du formulaire, complétés par les valeurs enregistrées.
     *
     * Seules les clés Samba sont concernées : le mot de passe est le seul champ
     * qui ne peut pas venir du formulaire quand il est vide. Un utilisateur
     * effacé du formulaire bascule en accès invité — l'ancien identifiant n'est
     * pas conservé, il ferait échouer une configuration volontairement anonyme.
     *
     * @return array<string, string>
     */
    private function reglagesSaisis(): array
    {
        $reglages = Backup::reglages();

        foreach (['samba_hote', 'samba_partage', 'samba_repertoire', 'samba_utilisateur', 'samba_domaine'] as $cle) {
            $reglages[$cle] = mb_substr((string) Request::input($cle, ''), 0, 190);
        }

        $saisi = (string) Request::input('samba_mot_de_passe', '');
        if ($saisi !== '') {
            $reglages['samba_mot_de_passe'] = mb_substr($saisi, 0, 190);
        }

        return $reglages;
    }

    /** Instant de la dernière sauvegarde lancée, 0 si aucune. */
    private static function derniereExecution(): int
    {
        Auth::start();

        return (int) ($_SESSION[self::CLE_EXECUTION] ?? 0);
    }

    private static function marquerExecution(): void
    {
        Auth::start();
        $_SESSION[self::CLE_EXECUTION] = time();
    }
}