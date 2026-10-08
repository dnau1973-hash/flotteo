<?php
declare(strict_types=1);

namespace Services;

use Core\Logger;
use Models\Backup;

/**
 * Client d'externalisation vers un partage réseau Samba (SMB/CIFS).
 *
 * **Aucun accès direct à SMB depuis PHP n'existe sur cette plateforme.** Ni
 * l'extension `smbclient` (libsmbclient), ni le binaire `smbclient`, ni
 * `mount.cifs` ne sont présents : la classe ne peut donc pas se contenter
 * d'exister, elle doit dire *comment* elle va parler au partage et échouer
 * explicitement lorsqu'aucun des deux transports n'est là.
 *
 * Deux transports, essayés dans cet ordre :
 *
 *  1. **Point de montage déjà en place.** Si l'administrateur a monté le
 *     partage dans `samba_repertoire`, la dépôt est une copie de fichier
 *     ordinaire : aucune authentification SMB n'est nécessaire depuis PHP, le
 *     montage s'en charge. C'est le mode le plus robuste, et le seul qui
 *     fonctionne quand le serveur Apache n'a aucun droit sur le réseau.
 *  2. **Binaire `smbclient`.** À défaut, si l'outil est installé, la copie
 *     passe par lui. Les identifiants ne sont jamais portés par la ligne de
 *     commande — ils sont écrits dans un fichier d'authentification à `0600`,
 *     supprimé à la fin, sinon le mot de passe serait lisible par tout
 *     processus du serveur dans `/proc`.
 *
 * Les deux modes demandés par le cahier des charges sont couverts :
 *  - **invité / anonyme** : ni utilisateur ni mot de passe transmis, ce que
 *    `smbclient -N` exprime explicitement ;
 *  - **identifié** : utilisateur, mot de passe et domaine/workgroup.
 *
 * La commande n'est jamais assemblée dans un shell : `proc_open()` reçoit un
 * **tableau**, ce qui interdit toute interpolation. Les valeurs issues de la
 * saisie sont par ailleurs filtrées avant d'atteindre le binaire.
 */
final class SambaClient
{
    /** Hôtes et partages : nom d'hôte, IPv4, IPv6 ou nom NetBIOS, sans espace ni option. */
    private const MOTIF_HOTE    = '/^[A-Za-z0-9._:-]{1,255}$/';
    private const MOTIF_PARTAGE = '/^[A-Za-z0-9 _.-]{1,80}$/';

    /** Profondeur maximale d'un chemin relatif dans le partage. */
    private const MOTIF_REPERTOIRE = '/^[A-Za-z0-9 _.\/-]{0,255}$/';

    /** @var array<string, string> Réglages Samba en vigueur */
    private array $config;

    private string $dernierMessage = '';

    public function __construct(?array $reglages = null)
    {
        $reglages ??= Backup::reglages();
        $this->config = $reglages;
    }

    /** Dernier message d'erreur produit, destiné à l'écran ou au journal. */
    public function dernierMessage(): string
    {
        return $this->dernierMessage;
    }

    /** Le mode non identifié est-il demandé ? */
    public function estInvite(): bool
    {
        return (string) ($this->config['samba_utilisateur'] ?? '') === '';
    }

    /** Partage distant complet, au format `\\hote\partage`. */
    public function partage(): string
    {
        return '\\\\' . trim((string) ($this->config['samba_hote'] ?? ''), '\\/')
            . '\\' . trim((string) ($this->config['samba_partage'] ?? ''), '\\/');
    }

    /**
     * Un dépôt est-il techniquement possible dans la configuration actuelle ?
     *
     * Répond non si le partage est vide, si aucune valeur n'est valide, et si
     * aucun des deux transports n'est disponible. Le message indique alors quoi
     * faire, plutôt qu'un échec obscur au moment de la copie.
     */
    public function disponible(): bool
    {
        $anomalie = $this->anomalieConfiguration();
        if ($anomalie !== null) {
            $this->dernierMessage = $anomalie;
            return false;
        }

        if ($this->transport() === 'montage' || $this->binaire() !== null) {
            return true;
        }

        $this->dernierMessage = "Aucun moyen d'accès au partage : ni point de montage accessible "
            . "dans « " . (string) ($this->config['samba_repertoire'] ?? '') . " », ni binaire smbclient "
            . "installé sur le serveur. Montez le partage, ou installez le paquet smbclient.";

        return false;
    }

    /** Transport qui sera employé, ou null si aucun ne l'est. */
    public function transport(): ?string
    {
        $repertoire = $this->repertoireLocal();
        if ($repertoire !== null) {
            return 'montage';
        }

        return $this->binaire() !== null ? 'smbclient' : null;
    }

    /**
     * Épreuve la configuration sans déposer de fichier.
     *
     * En mode montage, le dossier doit simplement exister et être inscriptible.
     * En mode `smbclient`, la commande `ls` interroge le partage : c'est le
     * seul moyen de savoir si les identifiants sont acceptés.
     *
     * @return array{ok: bool, message: string}
     */
    public function tester(): array
    {
        $this->dernierMessage = '';
        $anomalie = $this->anomalieConfiguration();
        if ($anomalie !== null) {
            return $this->echec($anomalie);
        }

        $transport = $this->transport();
        if ($transport === null) {
            return $this->echec($this->dernierMessage);
        }

        return $transport === 'montage'
            ? $this->testerMontage()
            : $this->testerSmbclient();
    }

    /**
     * Dépose une archive sur le partage.
     *
     * @return array{ok: bool, message: string}
     */
    public function deposer(string $cheminLocal): array
    {
        $this->dernierMessage = '';
        if (!is_file($cheminLocal)) {
            return $this->echec('Archive introuvable sur le disque : rien à externaliser.');
        }

        $test = $this->tester();
        if (!$test['ok']) {
            return $test;
        }

        return $this->transport() === 'montage'
            ? $this->deposerMontage($cheminLocal)
            : $this->deposerSmbclient($cheminLocal);
    }

    // ------------------------------------------------------------------ montage

    /** Répertoire local correspondant au partage monté, ou null. */
    private function repertoireLocal(): ?string
    {
        $saisi = trim((string) ($this->config['samba_repertoire'] ?? ''), " \t\n\r\0\x0B/");
        if ($saisi === '' || !is_dir($saisi)) {
            return null;
        }

        return $saisi;
    }

    /** @return array{ok: bool, message: string} */
    private function testerMontage(): array
    {
        $repertoire = (string) $this->repertoireLocal();
        if (!is_writable($repertoire)) {
            return $this->echec("Le point de montage « $repertoire » n'est pas accessible en écriture par le serveur web.");
        }

        return [
            'ok'      => true,
            'message' => "Partage accessible par le point de montage « $repertoire » ("
                . $this->libelleMode() . ', aucun identifiant transmis).',
        ];
    }

    /** @return array{ok: bool, message: string} */
    private function deposerMontage(string $cheminLocal): array
    {
        $repertoire = (string) $this->repertoireLocal();
        $destination = $repertoire . '/' . basename($cheminLocal);

        // `copy()` n'écrase pas : une archive déjà présente est conservée, ce
        // qui évite qu'un transfert interrompu détruise la seule copie distante
        // d'une sauvegarde antérieure.
        if (!@copy($cheminLocal, $destination)) {
            return $this->echec("Copie impossible vers « $destination ». Vérifiez l'espace disponible et les droits du montage.");
        }

        $taille = (int) @filesize($destination);

        return [
            'ok'      => true,
            'message' => 'Archive copiée sur le point de montage (' . self::poids($taille) . ').',
        ];
    }

    // --------------------------------------------------------------- smbclient

    /** Chemin du binaire `smbclient`, ou null s'il est absent. */
    private function binaire(): ?string
    {
        static $chemin = false;
        if ($chemin !== false) {
            return $chemin;
        }

        foreach (['/usr/bin/smbclient', '/usr/local/bin/smbclient', '/bin/smbclient'] as $candidat) {
            if (is_file($candidat) && is_executable($candidat)) {
                return $chemin = $candidat;
            }
        }

        return $chemin = null;
    }

    /** @return array{ok: bool, message: string} */
    private function testerSmbclient(): array
    {
        $auth = $this->fichierAuth();
        if ($auth === null) {
            return $this->echec("Fichier d'authentification Samba illisible : le dossier temporaire est inaccessible.");
        }

        $resultat = $this->executer(['ls', (string) $auth], $auth);
        unlink($auth);

        if ($resultat['code'] === 0) {
            return [
                'ok'      => true,
                'message' => 'Partage ' . $this->partage() . ' accessible (' . $this->libelleMode() . ').',
            ];
        }

        return $this->echec('Partage inaccessible : ' . self::resumeSortie($resultat['erreur'], $resultat['sortie']));
    }

    /** @return array{ok: bool, message: string} */
    private function deposerSmbclient(string $cheminLocal): array
    {
        $auth = $this->fichierAuth();
        if ($auth === null) {
            return $this->echec("Fichier d'authentification Samba illisible : le dossier temporaire est inaccessible.");
        }

        $destination = $this->cheminDistant($cheminLocal);
        // `put` vers un nom déjà présent échoue : l'archive est horodatée au
        // second près, une collision Signale donc un vrai conflit, pas un
        // écrasement accidentel.
        $resultat = $this->executer(['put', $cheminLocal, $destination], $auth);
        unlink($auth);

        if ($resultat['code'] !== 0) {
            return $this->echec('Transfert refusé : ' . self::resumeSortie($resultat['erreur'], $resultat['sortie']));
        }

        return [
            'ok'      => true,
            'message' => 'Archive transférée vers ' . $this->partage() . '\\' . $destination . '.',
        ];
    }

    /**
     * Chemin de destination dans le partage, sous le répertoire demandé.
     *
     * Le séparateur est la barre oblique : c'est le chemin relatif que
     * `smbclient` attend, et non le chemin UNC complet.
     */
    private function cheminDistant(string $cheminLocal): string
    {
        $repertoire = trim((string) ($this->config['samba_repertoire'] ?? ''), " \t\n\r\0\x0B/\\");
        $fichier    = basename($cheminLocal);

        return $repertoire === '' ? $fichier : $repertoire . '/' . $fichier;
    }

    /**
     * Fichier d'authentification éphémère, en `0600`.
     *
     * Le mot de passe ne figure donc ni dans la ligne de commande — lisible par
     * tout processus du serveur — ni dans le journal.
     */
    private function fichierAuth(): ?string
    {
        if ($this->estInvite()) {
            // Mode invité : aucun identifiant n'est transmis. Le fichier reste
            // nécessaire pour que l'appel n'essaie pas de lire `~/.smbpasswd`,
            // mais il ne contient aucun secret.
            $contenu = '';
        } else {
            $domaine = trim((string) ($this->config['samba_domaine'] ?? ''));
            $ligne   = 'username = ' . (string) ($this->config['samba_utilisateur'] ?? '') . "\n"
                . 'password = ' . (string) ($this->config['samba_mot_de_passe'] ?? '') . "\n";
            $contenu = $domaine === '' ? $ligne : $domaine . '/' . $ligne;
        }

        $fichier = @tempnam(sys_get_temp_dir(), 'flotteo_smb_');
        if ($fichier === false) {
            return null;
        }
        if (@file_put_contents($fichier, $contenu) === false) {
            @unlink($fichier);
            return null;
        }
        @chmod($fichier, 0600);

        return $fichier;
    }

    /**
     * Exécute `smbclient` sans shell.
     *
     * `proc_open()` reçoit un **tableau** : aucune chaîne n'est interprétée par
     * un interpréteur, donc aucune valeur issue du formulaire ne peut devenir
     * une option du binaire (`-c`, redirection, substitution).
     *
     * @param string[] $arguments  commande et arguments, hors service et auth
     * @return array{code: int, sortie: string, erreur: string}
     */
    private function executer(array $arguments, string $fichierAuth): array
    {
        $service = '//' . trim((string) ($this->config['samba_hote'] ?? ''), '\\/')
            . '/' . trim((string) ($this->config['samba_partage'] ?? ''), '\\/');

        // `-N` (aucun mot de passe demandé) ne figure qu'en mode invité. Avec
        // des identifiants, il ferait ignorer le fichier d'authentification et la
        // connexion échouerait systématiquement.
        $commande = array_merge([(string) $this->binaire()], $this->estInvite() ? ['-N'] : [], $arguments);
        $commande = array_merge($commande, [$service, '-A', $fichierAuth]);

        $descripteurs = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $processus = @proc_open($commande, $descripteurs, $pipes);
        if (!is_resource($processus)) {
            return ['code' => 127, 'sortie' => '', 'erreur' => 'smbclient n\'a pas pu être exécuté.'];
        }

        fclose($pipes[0]);
        $sortie = (string) stream_get_contents($pipes[1]);
        $erreur = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'code'   => proc_close($processus),
            'sortie' => $sortie,
            'erreur' => $erreur,
        ];
    }

    // -------------------------------------------------------------------- aide

    /** @return array{ok: bool, message: string} */
    private function echec(string $message): array
    {
        $this->dernierMessage = $message;
        Logger::error('Samba : ' . $message);

        return ['ok' => false, 'message' => $message];
    }

    private function libelleMode(): string
    {
        return $this->estInvite() ? 'accès invité' : 'accès identifié';
    }

    /**
     * Anomalie de configuration, ou null si elle est recevable.
     *
     * Le contrôle est fait avant tout contact réseau : une saisie malformée
     * ne doit pas atteindre le binaire.
     */
    private function anomalieConfiguration(): ?string
    {
        $hote = trim((string) ($this->config['samba_hote'] ?? ''));
        if ($hote === '') {
            return "Renseignez l'hôte du serveur Samba.";
        }
        if (preg_match(self::MOTIF_HOTE, $hote) !== 1) {
            return "L'hôte Samba contient des caractères non admis : nom d'hôte, adresse IP ou nom NetBIOS.";
        }

        $partage = trim((string) ($this->config['samba_partage'] ?? ''));
        if ($partage === '') {
            return 'Renseignez le nom du partage Samba.';
        }
        if (preg_match(self::MOTIF_PARTAGE, $partage) !== 1) {
            return 'Le nom du partage contient des caractères non admis.';
        }

        $repertoire = trim((string) ($this->config['samba_repertoire'] ?? ''), " \t\n\r\0\x0B/\\");
        if (preg_match(self::MOTIF_REPERTOIRE, $repertoire) !== 1) {
            return 'Le répertoire cible contient des caractères non admis.';
        }

        if ($this->estInvite() && (string) ($this->config['samba_domaine'] ?? '') !== '') {
            return 'Un domaine ne peut être renseigné en accès invité : renseignez aussi un utilisateur, ou retirez le domaine.';
        }

        return null;
    }

    /** Première ligne utile d'une sortie de commande, pour un message d'écran. */
    private static function resumeSortie(string $erreur, string $sortie): string
    {
        foreach (preg_split('/\R/', $erreur . "\n" . $sortie) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne !== '') {
                return mb_substr($ligne, 0, 200);
            }
        }

        return 'aucun détail transmis par smbclient.';
    }

    /** Poids lisible, pour un message de taille. */
    public static function poids(int $octets): string
    {
        if ($octets >= 1048576) {
            return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
        }
        if ($octets >= 1024) {
            return number_format($octets / 1024, 0, ',', ' ') . ' Ko';
        }

        return $octets . ' o';
    }
}