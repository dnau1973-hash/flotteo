<?php
declare(strict_types=1);

namespace Core;

/**
 * Client SMTP minimal, écrit nativement.
 *
 * Le projet n'embarque aucune bibliothèque tierce et la règle d'or interdit d'en
 * introduire sans accord : le dialogue SMTP est donc implémenté directement
 * au-dessus de `stream_socket_client()`.
 *
 * Prend en charge trois modes de chiffrement : `aucun` (texte en clair),
 * `tls` (STARTTLS, port 587 en principe) et `ssl` (TLS établi dès la connexion,
 * port 465 en principe). L'authentification est facultative ; lorsque des
 * identifiants sont fournis, elle s'appuie sur AUTH PLAIN après avoir vérifié
 * que le serveur annonce cette méthode.
 *
 * Sûreté : aucun mot de passe n'est journalisé. Les erreurs sont remontées à
 * l'appelant pour affichage, et seul un code SMTP accompagné d'un extrait
 * neutralisé est conservé.
 */
final class SmtpClient
{
    private const DELAI_PAR_DEFAUT = 10;

    /** Modes acceptés pour le chiffrement. */
    public const CHIFFREMENTS = ['aucun', 'tls', 'ssl'];

    private string $erreur = '';

    /** @var resource|null */
    private $flux = null;

    /** Extensions annoncées par le serveur, en majuscules. */
    private string $extensions = '';

    public function __construct(
        private readonly string $hote,
        private readonly int $port,
        private readonly string $chiffrement = 'aucun',
        private readonly string $utilisateur = '',
        private readonly string $motDePasse = '',
        private readonly int $delai = self::DELAI_PAR_DEFAUT
    ) {
        if (trim($this->hote) === '') {
            throw new \InvalidArgumentException('Hôte SMTP vide.');
        }
        if ($this->port < 1 || $this->port > 65535) {
            throw new \InvalidArgumentException('Port SMTP hors bornes.');
        }

        // Un mode de chiffrement inconnu ne doit jamais se replier sur du texte
        // en clair : l'utilisateur croirait ses messages chiffrés alors qu'ils
        // partiraient en clair. On rejette donc explicitement.
        if (!in_array($this->chiffrement, self::CHIFFREMENTS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Mode de chiffrement inconnu (« %s »). Valeurs acceptées : %s.',
                $this->chiffrement,
                implode(', ', self::CHIFFREMENTS)
            ));
        }
    }

    /** Dernière erreur rencontrée, destinée à l'affichage. */
    public function lastError(): string
    {
        return $this->erreur;
    }

    /**
     * Envoie un message HTML.
     *
     * @return bool Vrai si le serveur a acquitté la fin des données (réponse 250).
     */
    public function send(string $expediteur, string $destinataire, string $sujet, string $corpsHtml): bool
    {
        $this->erreur = '';

        try {
            $this->connecter();
            $this->negocier();

            if ($this->utilisateur !== '') {
                $this->authentifier();
            }

            $this->commander(
                'MAIL FROM:<' . $expediteur . '>',
                [250, 251],
                'adresse expéditeur refusée'
            );
            $this->commander(
                'RCPT TO:<' . $destinataire . '>',
                [250, 251],
                'adresse destinataire refusée'
            );

            $this->commander('DATA', [354], 'DATA refusé');
            $this->ecrireDonnees($this->composerMessage($expediteur, $destinataire, $sujet, $corpsHtml));
            $this->commander('.', [250], 'message rejeté');

            $this->fermer(true);

            return true;
        } catch (\RuntimeException $e) {
            if ($this->erreur === '') {
                $this->erreur = $e->getMessage();
            }
            $this->fermer(false);
            Logger::error("Échec d'envoi SMTP : " . $this->erreur);

            return false;
        }
    }

    // ------------------------------------------------------------- transport

    private function connecter(): void
    {
        $schema = $this->chiffrement === 'ssl' ? 'ssl://' : 'tcp://';
        $cible  = $schema . $this->hote . ':' . $this->port;

        $codeErreur     = 0;
        $erreurSysteme  = '';
        $flux = @stream_socket_client(
            $cible,
            $codeErreur,
            $erreurSysteme,
            $this->delai,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'SNI_enabled'       => true,
                'peer_name'         => $this->hote,
                'allow_self_signed' => false,
            ]])
        );

        if ($flux === false) {
            throw new \RuntimeException(sprintf(
                'Connexion SMTP impossible vers %s : %s',
                $cible,
                $this->raisonConnexion($erreurSysteme, $codeErreur)
            ));
        }

        $this->flux = $flux;
        stream_set_timeout($this->flux, $this->delai);

        $salutation = $this->lireReponse();
        if ($salutation[0] !== 220) {
            throw new \RuntimeException('Serveur SMTP inattendu : ' . $this->neutraliser($salutation[1]));
        }
    }

    /** EHLO, puis STARTTLS si le mode l'exige. */
    private function negocier(): void
    {
        $this->ehlo();

        if ($this->chiffrement !== 'tls') {
            return;
        }

        $this->commander('STARTTLS', [220], 'STARTTLS refusé par le serveur');

        $crypto = @stream_socket_enable_crypto(
            $this->flux,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );

        if ($crypto !== true) {
            throw new \RuntimeException('Négociation TLS impossible avec le serveur : ' . $this->raisonTls());
        }

        // La liste des extensions doit être relue sur la session chiffrée.
        $this->ehlo();
    }

    private function ehlo(): void
    {
        $reponse = $this->commander('EHLO ' . $this->nomHote(), [250], 'EHLO refusé');
        $this->extensions = strtoupper($reponse[1]);
    }

    private function authentifier(): void
    {
        if ($this->extensions !== '' && !str_contains($this->extensions, 'AUTH')) {
            throw new \RuntimeException('Le serveur SMTP n’annonce pas d’authentification.');
        }

        $jeton = base64_encode("\0" . $this->utilisateur . "\0" . $this->motDePasse);
        $this->commander('AUTH PLAIN ' . $jeton, [235], 'authentification refusée');
    }

    // ------------------------------------------------------------- protocole

    /**
     * Envoie une commande et contrôle le code de réponse.
     *
     * @param int[] $codesAttendus
     *
     * @return array{0:int,1:string}
     */
    private function commander(string $commande, array $codesAttendus, string $messageEchec): array
    {
        $this->ecrire($commande . "\r\n");
        $reponse = $this->lireReponse();

        if (!in_array($reponse[0], $codesAttendus, true)) {
            throw new \RuntimeException($messageEchec . ' (' . $reponse[0] . ').');
        }

        return $reponse;
    }

    /**
     * Lit une réponse éventuellement multi-lignes.
     *
     * @return array{0:int,1:string}
     */
    private function lireReponse(): array
    {
        $lignes = [];
        while (true) {
            $ligne = fgets($this->flux, 2048);
            if ($ligne === false) {
                $meta = stream_get_meta_data($this->flux);
                $raison = ($meta['timed_out'] ?? false)
                    ? 'délai dépassé'
                    : 'connexion fermée par le serveur';
                throw new \RuntimeException('Lecture SMTP interrompue : ' . $raison . '.');
            }

            $ligne = rtrim($ligne, "\r\n");
            $lignes[] = $ligne;

            // Une réponse multi-lignes se poursuit par « 250-… » ; elle est
            // close par « 250 … ».
            if (preg_match('/^(\d{3}) /', $ligne, $m) === 1) {
                return [(int) $m[1], implode("\n", $lignes)];
            }
        }
    }

    private function ecrire(string $texte): void
    {
        $ecrit = @fwrite($this->flux, $texte);
        if ($ecrit === false || $ecrit < strlen($texte)) {
            throw new \RuntimeException('Écriture SMTP interrompue.');
        }
    }

    /**
     * Transmet le corps du message, sans le point final.
     *
     * Chaque ligne est préfixée d'un point, conformément à la RFC 5321 : sans
     * cela une ligne commençant par « . » tronquerait le message. Le point de
     * fin reste à la charge de l'appelant, seul à même de contrôler la réponse
     * d'acquittement du serveur.
     */
    private function ecrireDonnees(string $message): void
    {
        $normalise = preg_replace('/\r?\n/', "\r\n", $message) ?? $message;
        $protege = preg_replace('/^\./m', '..', $normalise) ?? $normalise;

        $this->ecrire($protege);
        if (!str_ends_with($protege, "\r\n")) {
            $this->ecrire("\r\n");
        }
    }

    private function composerMessage(string $expediteur, string $destinataire, string $sujet, string $corpsHtml): string
    {
        $conf = (require dirname(__DIR__) . '/config/config.php');
        $nom = (string) $conf['app']['nom'];

        $entetes = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . self::encoderAdresse($nom, $expediteur),
            'To: <' . $destinataire . '>',
            'Subject: ' . '=?UTF-8?B?' . base64_encode($sujet) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::domaine($expediteur) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: ' . $nom . '/' . $conf['app']['version'],
        ];

        return implode("\r\n", $entetes) . "\r\n\r\n"
            . chunk_split(base64_encode($corpsHtml), 76, "\r\n");
    }

    // ---------------------------------------------------------------- helpers

    private function nomHote(): string
    {
        $fqdn = gethostname();
        return is_string($fqdn) && $fqdn !== '' ? $fqdn : 'localhost';
    }

    private static function encoderAdresse(string $nom, string $adresse): string
    {
        $nom = trim($nom);
        if ($nom === '') {
            return '<' . $adresse . '>';
        }

        // Un nom d'expéditeur contenant des caractères spéciaux doit être cité.
        if (preg_match('/[^\p{L}\p{N} ._-]/u', $nom) === 1) {
            $nom = '"' . addcslashes($nom, '"\\') . '"';
        }

        return $nom . ' <' . $adresse . '>';
    }

    private static function domaine(string $adresse): string
    {
        $position = strrpos($adresse, '@');
        return $position === false ? 'localhost' : substr($adresse, $position + 1);
    }

    /**
     * Traduit la cause technique d'un échec de connexion.
     *
     * `stream_socket_client()` laisse souvent `errno` à zéro pour les couches
     * TLS : la dernière erreur PHP est alors la seule source exploitable, et le
     * message doit rester actionnable pour l'utilisateur.
     */
    private function raisonConnexion(string $erreurSysteme, int $codeErreur): string
    {
        $brut = $erreurSysteme;
        if ($brut === '') {
            $dernier = error_get_last();
            $brut = (string) ($dernier['message'] ?? '');
            $brut = trim(preg_replace('/\s*\(_\w+\.php:\d+\)\s*$/', '', $brut) ?? $brut);
        }

        if ($brut !== '') {
            $motif = $this->motifTls($brut);
            return $motif !== '' ? $motif : $brut;
        }

        if ($codeErreur === 111 || $codeErreur === 110) {
            return 'le serveur ne répond pas sur ce port (connexion refusée). Vérifiez l\'hôte et le port saisis.';
        }

        if ($codeErreur === 0) {
            return 'aucune réponse du serveur (délai dépassé ou hôte injoignable).';
        }

        return 'code ' . $codeErreur;
    }

    /**
     * Traduit la cause technique d'un échec de négociation.
     *
     * `stream_socket_enable_crypto()` n'expose aucune raison exploitable : on
     * remonte donc la dernière erreur PHP. Les deux causes les plus fréquentes
     * en exploitation sont distinguées, car elles appellent des corrections
     * très différentes chez l'utilisateur.
     */
    private function raisonTls(): string
    {
        $dernier = error_get_last();
        $detail = trim(preg_replace(
            '/\s*\(_\w+\.php:\d+\)\s*$/',
            '',
            $this->neutraliser((string) ($dernier['message'] ?? ''))
        ) ?? '');

        if ($detail === '') {
            return 'le serveur a refusé la connexion chiffrée.';
        }

        $motif = $this->motifTls($detail);
        return $motif !== '' ? $motif : 'le serveur et le client ne partagent aucune version de TLS commune.';
    }

    /**
     * Traduit les messages OpenSSL les plus courants.
     *
     * @return string Vide si le motif n'est pas reconnu.
     */
    private function motifTls(string $detail): string
    {
        if (stripos($detail, 'unknown ca') !== false
            || stripos($detail, 'certificate verify failed') !== false
            || stripos($detail, 'self signed') !== false
            || stripos($detail, 'self-signed') !== false
        ) {
            return 'certificat non reconnu (autorité de certification absente ou certificat '
                . 'auto-signé). Vérifiez l\'autorité de certification de votre serveur de '
                . 'messagerie, ou choisissez STARTTLS si votre hébergeur le propose.';
        }

        if (stripos($detail, 'hostname') !== false) {
            return 'le nom du certificat ne correspond pas à l\'hôte saisi.';
        }

        if (stripos($detail, 'wrong version') !== false
            || stripos($detail, 'no shared cipher') !== false
            || stripos($detail, 'unsupported protocol') !== false
        ) {
            return 'le serveur et le client ne partagent aucune version de TLS commune.';
        }

        return '';
    }

    /** Retire tout ce qu'un serveur pourrait renvoyer de sensible. */
    private function neutraliser(string $texte): string
    {
        $texte = trim($texte);
        if ($this->motDePasse !== '') {
            $texte = str_replace([$this->motDePasse, base64_encode("\0" . $this->utilisateur . "\0" . $this->motDePasse)], '[masque]', $texte);
        }

        return mb_substr(preg_replace('/\s+/', ' ', $texte) ?? $texte, 0, 200);
    }

    private function fermer(bool $gracieux): void
    {
        if ($this->flux === null) {
            return;
        }

        if ($gracieux) {
            @fwrite($this->flux, "QUIT\r\n");
        }

        @fclose($this->flux);
        $this->flux = null;
    }
}