<?php
declare(strict_types=1);

/**
 * Section « Messagerie » : mode d'envoi, serveur SMTP, adresse expéditrice et
 * test d'envoi.
 *
 * Les champs du serveur SMTP sont regroupés dans un bloc que le mode d'envoi
 * active ou masque ; le script de la page s'en charge. Le bloc reste présent
 * dans le HTML afin que les valeurs saisies survivent au changement de mode,
 * ce qui évite d'en ressaisir un serveur pour tester un mode puis l'autre.
 *
 * Le mot de passe enregistré n'est jamais renvoyé au navigateur : le champ est
 * vide à l'affichage et son effacement passe par une case explicite.
 *
 * @var array $valeurs, $paliers
 * @var string $base_url
 */

use Core\Csrf;
use Core\Icon;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);

$v = static fn (string $cle): string => (string) ($valeurs[$cle] ?? '');
$smtpConfigure = $v('smtp_host') !== '';
$motDePasseEnregistre = $v('smtp_password') !== '';
$transport = in_array($v('mail_transport'), ['mail', 'smtp'], true) ? $v('mail_transport') : 'mail';
$testDestinataire = (string) ($valeurs['email_gestionnaire'] ?? '');
?>

<form id="form-messagerie" method="post" autocomplete="off"
      action="<?= $e($base_url . '/admin/parametres/messagerie/enregistrer') ?>">
    <?= Csrf::field() ?>

    <div class="card-body">

        <div class="mb-4">
            <label class="form-label" for="mail_transport">
                <?= $icone('database', 'me-1') ?>Mode d'envoi
            </label>
            <select class="form-select" id="mail_transport" name="mail_transport">
                <option value="mail" <?= $transport === 'mail' ? 'selected' : '' ?>>
                    Frontal PHP natif (mail)
                </option>
                <option value="smtp" <?= $transport === 'smtp' ? 'selected' : '' ?>>
                    Serveur SMTP
                </option>
            </select>
            <p class="form-hint">
                Le frontal natif délègue l'envoi à la configuration PHP du serveur.
                Les réglages ci-dessous ne sont pris en compte
                <strong>que si le mode « Serveur SMTP » est sélectionné</strong> ;
                vous pouvez donc les saisir à tout moment.
            </p>
        </div>

        <div class="mb-4" id="bloc-smtp">
            <h4 class="mb-3"><?= $icone('lock', 'me-2') ?>Serveur SMTP</h4>

            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label required" for="smtp_host">Serveur</label>
                    <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                           maxlength="190" placeholder="smtp.exemple.fr"
                           value="<?= $e($v('smtp_host')) ?>">
                </div>

                <div class="col-md-5">
                    <label class="form-label required" for="smtp_port">Port</label>
                    <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                           min="1" max="65535" step="1" placeholder="587"
                           value="<?= $e($v('smtp_port')) ?>">
                </div>

                <div class="col-md-5">
                    <label class="form-label required" for="smtp_chiffrement">Chiffrement</label>
                    <select class="form-select" id="smtp_chiffrement" name="smtp_chiffrement">
                        <?php foreach ([
                            'tls'  => 'STARTTLS — connexion chiffrée après l\'accueil (587)',
                            'ssl'  => 'TLS direct — connexion chiffrée d\'emblée (465)',
                            'aucun' => 'Aucun — texte en clair, déconseillé',
                        ] as $mode => $libelle): ?>
                            <option value="<?= $e($mode) ?>" <?= $v('smtp_chiffrement') === $mode ? 'selected' : '' ?>>
                                <?= $e($libelle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint">
                        Le certificat du serveur est vérifié. Un certificat auto-signé
                        sera refusé.
                    </p>
                </div>

                <div class="col-md-7">
                    <label class="form-label" for="smtp_user">Identifiant</label>
                    <input type="text" class="form-control" id="smtp_user" name="smtp_user"
                           maxlength="190" autocomplete="off" placeholder="vide si l'authentification est inutile"
                           value="<?= $e($v('smtp_user')) ?>">
                </div>

                <div class="col-md-7">
                    <label class="form-label" for="smtp_password">Mot de passe</label>
                    <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                           autocomplete="new-password"
                           placeholder="<?= $motDePasseEnregistre ? 'Mot de passe enregistré — laissez vide pour le conserver' : 'Mot de passe du compte' ?>">
                    <?php if ($motDePasseEnregistre): ?>
                        <label class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="smtp_password_effacer" value="1"
                                   id="smtp_password_effacer">
                            <span class="form-check-label">
                                Effacer le mot de passe enregistré
                            </span>
                        </label>
                    <?php endif; ?>
                    <p class="form-hint mb-0">
                        Un champ laissé vide conserve le mot de passe enregistré.
                        La plupart des serveurs SMTP l'exigent.
                    </p>
                </div>
            </div>
        </div>

        <div class="mb-0">
            <label class="form-label required" for="email_expediteur">
                <?= $icone('envelope', 'me-1') ?>Adresse expéditrice
            </label>
            <input type="email" class="form-control" id="email_expediteur" name="email_expediteur"
                   maxlength="190" placeholder="no-reply@exemple.fr"
                   value="<?= $e($v('email_expediteur')) ?>">
            <p class="form-hint mb-0">
                Adresse présentée aux destinataires. La plupart des serveurs exigent
                qu'elle appartienne au domaine déclaré.
            </p>
        </div>

    </div>

    <div class="card-footer d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <?= $icone('check', 'me-2') ?>Enregistrer
        </button>
    </div>
</form>

<div class="card-body border-top">
    <h4 class="mb-3"><?= $icone('check', 'me-2') ?>Vérifier la configuration</h4>

    <div class="row g-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label" for="test_destinataire">Destinataire du test</label>
            <input type="email" class="form-control" id="test_destinataire"
                   maxlength="190" placeholder="nom@exemple.fr"
                   value="<?= $e($testDestinataire) ?>">
            <p class="form-hint mb-0">
                Le test porte sur les valeurs actuellement saisies, pas sur celles
                enregistrées : validez donc votre configuration avant de l'enregistrer.
            </p>
        </div>
        <div class="col-md-6">
            <button type="button" class="btn btn-outline-primary" id="btn-test-envoi">
                <?= $icone('envelope', 'me-2') ?>Envoyer un message de test
            </button>
        </div>
    </div>

    <div class="mt-3" id="resultat-test" role="status" aria-live="polite"></div>
</div>