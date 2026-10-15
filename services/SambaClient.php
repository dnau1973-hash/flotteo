<?php
declare(strict_types=1);

namespace Services;

use Core\Logger;
use Models\Backup;

/**
 * Client d'externalisation vers un partage réseau Samba (SMB/CIFS).
 *
 * Trois transports sont essayés dans l'ordre suivant :
 *
 *  1. **Point de montage local.** Si le répertoire renseigné dans `samba_repertoire`
 *     est un dossier existant sur le serveur (ex : `/mnt/sauvegardes`), le dépôt
 *     est une copie de fichier ordinaire : aucune authentification SMB n'est requise
 *     depuis PHP, le point de montage système s'en chargeant.
 *  2. **Binaire `smbclient`.** Utilise le protocole moderne SMB2/SMB3 avec fichier
 *     d'authentification sécurisé (`0600`). Recommandé et compatible avec Windows 10/11
 *     et tous les serveurs SMB modernes.
 *  3. **cURL SMB natif.** Si `smbclient` est absent et que cURL supporte `smb://`
 *     (protocole hérité SMBv1).
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
     */
    public function disponible(): bool
    {
        $anomalie = $this->anomalieConfiguration();
        if ($anomalie !== null) {
            $this->dernierMessage = $anomalie;
            return false;
        }

        if ($this->transport() !== null) {
            return true;
        }

        $this->dernierMessage = "Aucun moyen d'accès au partage Samba disponible (ni point de montage existant "
            . "dans « " . (string) ($this->config['samba_repertoire'] ?? '') . " », ni binaire smbclient, ni support cURL SMB).";

        return false;
    }

    /**
     * Transport qui sera employé : 'montage', 'smbclient', 'curl', ou null.
     */
    public function transport(): ?string
    {
        if ($this->repertoireLocal() !== null) {
            return 'montage';
        }

        if ($this->binaire() !== null) {
            return 'smbclient';
        }

        if ($this->supporteCurlSmb()) {
            return 'curl';
        }

        return null;
    }

    /**
     * Éprouve la configuration sans déposer de fichier.
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
            return $this->echec(
                "Aucun moyen d'accès au partage Samba disponible sur le serveur : "
                . "le répertoire indiqué n'est pas un point de montage local, l'extension cURL ne supporte pas SMB et smbclient n'est pas installé."
            );
        }

        return match ($transport) {
            'montage'   => $this->testerMontage(),
            'curl'      => $this->testerCurl(),
            'smbclient' => $this->testerSmbclient(),
            default     => $this->echec("Transport inconnu : $transport"),
        };
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

        $transport = $this->transport();
        return match ($transport) {
            'montage'   => $this->deposerMontage($cheminLocal),
            'curl'      => $this->deposerCurl($cheminLocal),
            'smbclient' => $this->deposerSmbclient($cheminLocal),
            default     => $this->echec("Transport inconnu : $transport"),
        };
    }

    // ------------------------------------------------------------------ montage

    /** Répertoire local correspondant au partage monté, ou null. */
    private function repertoireLocal(): ?string
    {
        $saisi = trim((string) ($this->config['samba_repertoire'] ?? ''), " \t\n\r\0\x0B");
        if ($saisi === '') {
            return null;
        }

        if (is_dir($saisi)) {
            return rtrim($saisi, '/\\');
        }

        return null;
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
            'message' => "Partage accessible par le point de montage local « $repertoire » ("
                . $this->libelleMode() . ').',
        ];
    }

    /** @return array{ok: bool, message: string} */
    private function deposerMontage(string $cheminLocal): array
    {
        $repertoire = (string) $this->repertoireLocal();
        $destination = $repertoire . '/' . basename($cheminLocal);

        if (!@copy($cheminLocal, $destination)) {
            return $this->echec("Copie impossible vers « $destination ». Vérifiez l'espace disponible et les droits du point de montage.");
        }

        $taille = (int) @filesize($destination);

        return [
            'ok'      => true,
            'message' => 'Archive copiée sur le point de montage (' . self::poids($taille) . ').',
        ];
    }

    // --------------------------------------------------------------------- cURL

    /** Vérifie si l'extension cURL est présente et inclut le protocole SMB. */
    private function supporteCurlSmb(): bool
    {
        if (!extension_loaded('curl')) {
            return false;
        }

        $info = curl_version();
        $protocoles = $info['protocols'] ?? [];

        return in_array('smb', $protocoles, true);
    }

    /** Construit l'URL cURL pour le partage smb://. */
    private function urlCurl(string $cible = ''): string
    {
        $hote = trim((string) ($this->config['samba_hote'] ?? ''), " \t\n\r\0\x0B/\\");
        $partage = trim((string) ($this->config['samba_partage'] ?? ''), " \t\n\r\0\x0B/\\");

        $url = 'smb://' . $hote . '/' . $partage . '/';
        if ($cible !== '') {
            $url .= ltrim(str_replace('\\', '/', $cible), '/');
        }

        return $url;
    }

    /** Identifiants formatés pour CURLOPT_USERPWD (domaine;user:password). */
    private function authCurl(): ?string
    {
        if ($this->estInvite()) {
            return null;
        }

        $user = (string) ($this->config['samba_utilisateur'] ?? '');
        $pass = (string) ($this->config['samba_mot_de_passe'] ?? '');
        $domaine = trim((string) ($this->config['samba_domaine'] ?? ''));

        $prefixe = $domaine !== '' ? $domaine . ';' : '';

        return $prefixe . $user . ':' . $pass;
    }

    /** @return array{ok: bool, message: string} */
    private function testerCurl(): array
    {
        $repertoire = trim((string) ($this->config['samba_repertoire'] ?? ''), " \t\n\r\0\x0B/\\");
        $cible = $repertoire !== '' ? $repertoire . '/' : '';
        $url = $this->urlCurl($cible);

        $ch = curl_init($url);
        if ($ch === false) {
            return $this->echec("Impossible d'initialiser cURL.");
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);

        $userpwd = $this->authCurl();
        if ($userpwd !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
        }

        $res = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno === 0) {
            return [
                'ok'      => true,
                'message' => 'Partage ' . $this->partage() . ' accessible via cURL SMB (' . $this->libelleMode() . ').',
            ];
        }

        return $this->echec('Accès au partage Samba impossible : ' . self::traduireErreurCurl($errno, $error));
    }

    /** @return array{ok: bool, message: string} */
    private function deposerCurl(string $cheminLocal): array
    {
        $destination = $this->cheminDistant($cheminLocal);
        $url = $this->urlCurl($destination);

        $fp = @fopen($cheminLocal, 'rb');
        if (!$fp) {
            return $this->echec("Impossible d'ouvrir l'archive locale en lecture : $cheminLocal");
        }

        $taille = (int) @filesize($cheminLocal);

        $ch = curl_init($url);
        if ($ch === false) {
            fclose($fp);
            return $this->echec("Impossible d'initialiser cURL.");
        }

        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, $taille);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $userpwd = $this->authCurl();
        if ($userpwd !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
        }

        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($errno === 0) {
            return [
                'ok'      => true,
                'message' => 'Archive transférée avec succès sur ' . $this->partage() . '\\' . str_replace('/', '\\', $destination)
                    . ' (' . self::poids($taille) . ').',
            ];
        }

        return $this->echec('Échec du transfert vers le partage Samba : ' . self::traduireErreurCurl($errno, $error));
    }

    /** Traduit les erreurs de cURL SMB en messages clairs et exploitables. */
    private static function traduireErreurCurl(int $errno, string $error): string
    {
        return match ($errno) {
            6       => "Hôte introuvable : impossible de résoudre l'adresse du serveur Samba.",
            7       => "Connexion refusée ou port 445/139 fermé sur le serveur Samba. Vérifiez que le serveur est démarré et joignable sur le port 445.",
            28      => "Délai de connexion dépassé (timeout) : le serveur Samba ne répond pas.",
            67      => "Échec d'authentification : utilisateur, mot de passe ou domaine Samba incorrect.",
            78      => "Ressource ou partage introuvable sur le serveur Samba.",
            default => $error !== '' ? "$error (code $errno)" : "erreur cURL $errno",
        };
    }

    // --------------------------------------------------------------- smbclient

    /** Chemin du binaire `smbclient`, ou null s'il est absent. */
    private function binaire(): ?string
    {
        static $chemin = false;
        if ($chemin !== false) {
            return $chemin;
        }

        $candidats = [
            dirname(__DIR__) . '/bin/smbclient',
            '/usr/bin/smbclient',
            '/usr/local/bin/smbclient',
            '/bin/smbclient',
        ];

        foreach ($candidats as $candidat) {
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
        if (!$this->estInvite() && $auth === null) {
            return $this->echec("Fichier d'authentification Samba inaccessible : le dossier temporaire ne peut pas être écrit.");
        }

        $repertoire = trim((string) ($this->config['samba_repertoire'] ?? ''), " \t\n\r\0\x0B/\\");

        try {
            $resultat = $this->executer(['-c', 'ls'], $auth);
        } finally {
            if ($auth !== null) {
                @unlink($auth);
            }
        }

        if ($resultat['code'] === 0) {
            return [
                'ok'      => true,
                'message' => 'Partage ' . $this->partage() . ' accessible via smbclient (' . $this->libelleMode() . ').',
            ];
        }

        return $this->echec('Partage inaccessible via smbclient : ' . self::resumeSortie($resultat['erreur'], $resultat['sortie']));
    }

    /** @return array{ok: bool, message: string} */
    private function deposerSmbclient(string $cheminLocal): array
    {
        $auth = $this->fichierAuth();
        if (!$this->estInvite() && $auth === null) {
            return $this->echec("Fichier d'authentification Samba inaccessible : le dossier temporaire ne peut pas être écrit.");
        }

        $repertoire = trim((string) ($this->config['samba_repertoire'] ?? ''), " \t\n\r\0\x0B/\\");
        $nomFichier = basename($cheminLocal);

        try {
            if ($repertoire !== '') {
                $repSmb = str_replace('/', '\\', $repertoire);
                // On tente de se positionner dans le répertoire puis de déposer
                $cmd = 'cd "' . $repSmb . '"; put "' . $cheminLocal . '" "' . $nomFichier . '"';
                $resultat = $this->executer(['-c', $cmd], $auth);

                if ($resultat['code'] !== 0) {
                    // Si le répertoire n'existe pas encore, on tente de le créer
                    $cmdMkdir = 'mkdir "' . $repSmb . '"; cd "' . $repSmb . '"; put "' . $cheminLocal . '" "' . $nomFichier . '"';
                    $resultat = $this->executer(['-c', $cmdMkdir], $auth);
                }
            } else {
                $cmd = 'put "' . $cheminLocal . '" "' . $nomFichier . '"';
                $resultat = $this->executer(['-c', $cmd], $auth);
            }
        } finally {
            if ($auth !== null) {
                @unlink($auth);
            }
        }

        if ($resultat['code'] !== 0) {
            return $this->echec('Transfert refusé via smbclient : ' . self::resumeSortie($resultat['erreur'], $resultat['sortie']));
        }

        $taille = (int) @filesize($cheminLocal);
        $cheminDistantAffiche = $this->partage() . '\\' . ($repertoire !== '' ? str_replace('/', '\\', $repertoire) . '\\' : '') . $nomFichier;

        return [
            'ok'      => true,
            'message' => 'Archive transférée via smbclient vers ' . $cheminDistantAffiche . ' (' . self::poids($taille) . ').',
        ];
    }

    /**
     * Chemin de destination dans le partage, sous le répertoire demandé.
     */
    private function cheminDistant(string $cheminLocal): string
    {
        $repertoire = trim((string) ($this->config['samba_repertoire'] ?? ''), " \t\n\r\0\x0B/\\");
        $fichier    = basename($cheminLocal);

        return $repertoire === '' ? $fichier : $repertoire . '/' . $fichier;
    }

    /**
     * Fichier d'authentification éphémère pour smbclient (-A).
     *
     * Format officiel smbclient :
     *   username = <nom>
     *   password = <mot_de_passe>
     *   domain   = <domaine>
     */
    private function fichierAuth(): ?string
    {
        if ($this->estInvite()) {
            return null;
        }

        $user    = (string) ($this->config['samba_utilisateur'] ?? '');
        $pass    = (string) ($this->config['samba_mot_de_passe'] ?? '');
        $domaine = trim((string) ($this->config['samba_domaine'] ?? ''));

        $lignes = [
            'username = ' . $user,
            'password = ' . $pass,
        ];
        if ($domaine !== '') {
            $lignes[] = 'domain   = ' . $domaine;
        }

        $contenu = implode("\n", $lignes) . "\n";

        $fichier = @tempnam(sys_get_temp_dir(), 'flotteo_smb_');
        if ($fichier === false) {
            return null;
        }

        $h = @fopen($fichier, 'w');
        if ($h === false) {
            @unlink($fichier);
            return null;
        }

        if (@fwrite($h, $contenu) === false || !@fflush($h)) {
            fclose($h);
            @unlink($fichier);
            return null;
        }
        fclose($h);

        if (@chmod($fichier, 0600) === false) {
            @unlink($fichier);
            return null;
        }

        return $fichier;
    }

    /**
     * Exécute `smbclient` sans passer par un interpréteur shell.
     *
     * @param string[] $arguments
     * @return array{code: int, sortie: string, erreur: string}
     */
    private function executer(array $arguments, ?string $fichierAuth = null): array
    {
        $service = '//' . trim((string) ($this->config['samba_hote'] ?? ''), '\\/')
            . '/' . trim((string) ($this->config['samba_partage'] ?? ''), '\\/');

        $commande = [(string) $this->binaire(), $service];
        if ($this->estInvite()) {
            $commande[] = '-N';
        } elseif ($fichierAuth !== null) {
            $commande[] = '-A';
            $commande[] = $fichierAuth;
        }

        foreach ($arguments as $arg) {
            $commande[] = $arg;
        }

        $descripteurs = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $processus = @proc_open($commande, $descripteurs, $pipes);
        if (!is_resource($processus)) {
            return ['code' => 127, 'sortie' => '', 'erreur' => "smbclient n'a pas pu être exécuté."];
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

    public function libelleMode(): string
    {
        $transport = $this->transport();
        $mode = $this->estInvite() ? 'accès invité' : 'accès identifié';
        $nomTransport = match ($transport) {
            'montage'   => 'point de montage local',
            'curl'      => 'cURL SMB natif',
            'smbclient' => 'smbclient',
            default     => 'aucun',
        };

        return "$nomTransport, $mode";
    }

    /**
     * Anomalie de configuration, ou null si elle est recevable.
     */
    private function anomalieConfiguration(): ?string
    {
        $hote = trim((string) ($this->config['samba_hote'] ?? ''));
        if ($hote === '') {
            return "Renseignez l'hôte ou l'adresse IP du serveur Samba.";
        }
        if (preg_match(self::MOTIF_HOTE, $hote) !== 1) {
            return "L'hôte Samba contient des caractères non admis : nom d'hôte, adresse IP ou nom NetBIOS attendu.";
        }

        $partage = trim((string) ($this->config['samba_partage'] ?? ''));
        if ($partage === '') {
            return 'Renseignez le nom du partage Samba.';
        }
        if (preg_match(self::MOTIF_PARTAGE, $partage) !== 1) {
            return 'Le nom du partage contient des caractères non admis.';
        }

        $repertoire = trim((string) ($this->config['samba_repertoire'] ?? ''), " \t\n\r\0\x0B/\\");
        if ($repertoire !== '' && preg_match(self::MOTIF_REPERTOIRE, $repertoire) !== 1) {
            return 'Le répertoire cible contient des caractères non admis.';
        }

        if ($this->estInvite() && (string) ($this->config['samba_domaine'] ?? '') !== '') {
            return 'Un domaine ne peut être renseigné en accès invité : renseignez aussi un utilisateur, ou retirez le domaine.';
        }

        return null;
    }

    /** Première ligne utile d'une sortie de commande, traduite si possible. */
    private static function resumeSortie(string $erreur, string $sortie): string
    {
        $texte = $erreur . "\n" . $sortie;

        if (str_contains($texte, 'NT_STATUS_LOGON_FAILURE') || str_contains($texte, 'NT_STATUS_WRONG_PASSWORD')) {
            return "Échec d'authentification : utilisateur, mot de passe ou domaine Samba incorrect.";
        }
        if (str_contains($texte, 'NT_STATUS_ACCESS_DENIED')) {
            return "Accès refusé au partage Samba : permissions insuffisantes pour cet utilisateur.";
        }
        if (str_contains($texte, 'NT_STATUS_BAD_NETWORK_NAME')) {
            return "Partage Samba introuvable : vérifiez le nom du partage sur le serveur distant.";
        }
        if (str_contains($texte, 'NT_STATUS_HOST_UNREACHABLE') || str_contains($texte, 'NT_STATUS_UNSUCCESSFUL') || str_contains($texte, 'NT_STATUS_CONNECTION_REFUSED')) {
            return "Connexion impossible au serveur Samba : hôte introuvable ou port 445 fermé.";
        }
        if (str_contains($texte, 'NT_STATUS_OBJECT_NAME_NOT_FOUND') || str_contains($texte, 'NT_STATUS_NO_SUCH_FILE') || str_contains($texte, 'NT_STATUS_OBJECT_PATH_NOT_FOUND')) {
            return "Répertoire cible introuvable sur le partage Samba.";
        }
        if (str_contains($texte, 'NT_STATUS_ACCOUNT_DISABLED')) {
            return "Le compte utilisateur Samba est désactivé sur le serveur distant.";
        }

        foreach (preg_split('/\R/', $texte) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne !== '' && !str_starts_with($ligne, 'lp_load_ex:') && !str_starts_with($ligne, 'WARNING:')) {
                return mb_substr($ligne, 0, 200);
            }
        }

        return 'aucun détail transmis.';
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