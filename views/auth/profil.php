<?php
declare(strict_types=1);

/**
 * Profil personnel : avatar, identité, email et changement de mot de passe.
 *
 * L'avatar se dépose dans le même formulaire que l'identité, et non dans une
 * modale : le compte affiché ici est le sien, et un envoi suffit pour les trois.
 *
 * @var array $utilisateur
 * @var string $base_url
 */

use Core\Csrf;
use Core\Icon;
use Models\User;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);

$config = (require dirname(__DIR__, 2) . '/config/config.php');
$tailleMax = round($config['securite']['taille_max_upload'] / 1048576);
$avatar    = User::avatarUrl($utilisateur['avatar'] ?? null);
$initiales = User::initiales((string) $utilisateur['nom']);

$this->useScript('profil.js');
?>
<div class="row row-deck row-cards">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Identité du compte</h3>
            </div>
            <div class="card-body">
                <form method="post" action="<?= $e($base_url . '/profil') ?>"
                      enctype="multipart/form-data" id="formulaire-profil">
                    <?= Csrf::field() ?>

                    <div class="text-center mb-4">
                        <!--
                            L'aperçu reflète le fichier choisi ; à défaut, l'image
                            en cours, ou les initiales. Le fond est posé par le
                            script, qui lit `data-avatar-url` au chargement.
                        -->
                        <?php if ($avatar !== null): ?>
                            <span class="avatar avatar-xl avatar-rounded bg-cover"
                                  id="p_avatar_apercu" role="img"
                                  aria-label="Aperçu de votre avatar"
                                  style="background-image: url('<?= $e($avatar) ?>')"
                                  data-avatar-url="<?= $e($avatar) ?>"
                                  data-initiales="<?= $e($initiales) ?>"></span>
                        <?php else: ?>
                            <span class="avatar avatar-xl avatar-rounded"
                                  id="p_avatar_apercu" role="img"
                                  aria-label="Aperçu de votre avatar"
                                  data-avatar-url=""
                                  data-initiales="<?= $e($initiales) ?>"><?= $e($initiales) ?></span>
                        <?php endif; ?>

                        <input type="file" name="avatar" id="p_avatar_fichier"
                               class="visually-hidden" accept="image/jpeg,image/png,image/webp"
                               data-apercu="#p_avatar_apercu">
                        <div class="mt-2">
                            <!--
                                Le champ est réellement masqué (`visually-hidden`).
                                Appeler `input.click()` depuis un bouton ne repose
                                alors que sur une activation programmatique, que
                                certains navigateurs refusent sans signaler d'erreur.
                                Une étiquette `for` délègue l'activation au champ de
                                façon native : le clic ouvre le sélecteur sans
                                JavaScript, et l'association reste annoncée aux
                                lecteurs d'écran.
                            -->
                            <label for="p_avatar_fichier" id="p_avatar_choisir" class="btn btn-sm btn-outline-secondary">
                                <?= $icone('user', 'me-1') ?>Choisir une image
                            </label>
                        </div>
                        <div class="form-hint mt-1" id="p_avatar_aide">
                            JPG, PNG ou WEBP, <?= $e((string) $tailleMax) ?> Mo maximum.
                            Sans image, vos initiales sont affichées.
                        </div>
                        <label class="form-check form-switch d-inline-flex mt-2" id="p_avatar_supprimer_bloc"
                               <?= $avatar === null ? 'hidden' : '' ?>>
                            <input class="form-check-input" type="checkbox" name="avatar_supprimer" value="1"
                                   id="p_avatar_supprimer">
                            <span class="form-check-label">Supprimer l'image actuelle</span>
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="nom">Nom et prénom</label>
                        <input type="text" class="form-control" id="nom" name="nom" required
                               value="<?= $e((string) $utilisateur['nom']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="email">Adresse email</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?= $e((string) $utilisateur['email']) ?>">
                    </div>
                    <div class="mb-3">
                        <span class="form-label">Rôle attribué</span>
                        <input type="text" class="form-control" disabled
                               value="<?= $e(User::ROLES[(string) $utilisateur['role']] ?? (string) $utilisateur['role']) ?>">
                    </div>

                    <hr class="my-4">
                    <h4 class="mb-3">Changer de mot de passe</h4>
                    <p class="form-hint mb-3">
                        Laissez ces trois champs vides pour conserver votre mot de passe actuel.
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="password_actuel">Mot de passe actuel</label>
                        <input type="password" class="form-control" id="password_actuel" name="password_actuel"
                               autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_nouveau">Nouveau mot de passe (8 caractères min.)</label>
                        <input type="password" class="form-control" id="password_nouveau" name="password_nouveau"
                               autocomplete="new-password">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">Confirmation</label>
                        <input type="password" class="form-control" id="password_confirmation"
                               name="password_confirmation" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </form>
            </div>
        </div>
    </div>
</div>
