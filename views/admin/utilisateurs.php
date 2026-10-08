<?php
declare(strict_types=1);

/**
 * Administration : comptes utilisateurs, en grille de cartes de profil.
 *
 * Présentation inspirée des cartes utilisateurs de Tabler : avatar, nom, rôle et
 * état au centre, actions dans le pied de carte. Les actions « Email » et
 * « Call » de la maquette de référence sont volontairement absentes — elles
 * ouvriraient un client de messagerie ou un composeur téléphonique, hors du
 * périmètre d'un écran d'administration.
 *
 * Édition et suppression passent toutes deux par des modales Tabler : la
 * première par `data-bs-toggle="modal"`, la seconde par l'attribut
 * `data-confirmer` traité dans `public/assets/js/app.js`. Aucune boîte de
 * dialogue native du navigateur n'est employée.
 *
 * @var array $utilisateurs Liste des comptes
 * @var array $roles       Libellés des rôles, indexés par identifiant
 * @var ?int  $moi         Identifiant du compte connecté
 * @var string $base_url
 */

use Core\Csrf;
use Core\Icon;
use Models\User;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);

// Teintes du bandeau supérieur et du badge, par rôle.
$teintes = [
    'lecture_seule' => ['bandeau' => 'bg-secondary-lt', 'badge' => 'bg-secondary-lt'],
    'modification'  => ['bandeau' => 'bg-blue-lt',      'badge' => 'bg-blue-lt'],
    'administration' => ['bandeau' => 'bg-purple-lt',   'badge' => 'bg-purple-lt'],
];
$teinte = static fn (string $role): array => $teintes[$role] ?? $teintes['lecture_seule'];
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <?= $icone('users', 'me-2') ?>Comptes utilisateurs
        </h3>
        <div class="card-actions">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-utilisateur">
                <?= $icone('plus', 'me-1') ?>Nouvel utilisateur
            </button>
        </div>
    </div>

    <?php if ($utilisateurs === []): ?>
        <div class="empty-state">
            <?= $icone('users', 'mb-2') ?>
            <p class="mb-0">Aucun compte utilisateur. Utilisez « Nouvel utilisateur » pour créer le premier.</p>
        </div>
    <?php else: ?>
        <div class="card-body">
            <div class="row row-cards">
                <?php foreach ($utilisateurs as $u): ?>
                    <?php
                    $role     = (string) $u['role'];
                    $couleur  = $teinte($role);
                    $avatar   = User::avatarUrl($u['avatar'] ?? null);
                    $id       = (int) $u['id'];
                    $moiMeme  = $id === (int) $moi;
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-status-top <?= $couleur['bandeau'] ?>"></div>

                            <div class="card-body text-center">
                                <?php if ($avatar !== null): ?>
                                    <span class="avatar avatar-xl avatar-rounded mb-3 bg-cover"
                                          style="background-image: url('<?= $e($avatar) ?>')"
                                          role="img" aria-label="Avatar de <?= $e((string) $u['nom']) ?>"></span>
                                <?php else: ?>
                                    <span class="avatar avatar-xl avatar-rounded mb-3"
                                          aria-label="Initiales de <?= $e((string) $u['nom']) ?>">
                                        <?= $e(User::initiales((string) $u['nom'])) ?>
                                    </span>
                                <?php endif; ?>

                                <h3 class="mb-1">
                                    <?= $e((string) $u['nom']) ?>
                                    <?php if ($moiMeme): ?>
                                        <span class="badge bg-green-lt">Vous</span>
                                    <?php endif; ?>
                                </h3>

                                <div class="text-secondary mb-2">
                                    <?= $icone('envelope', 'me-1') ?><?= $e((string) $u['email']) ?>
                                </div>

                                <div class="mb-2">
                                    <span class="badge <?= $couleur['badge'] ?> badge-statut">
                                        <?= $e($roles[$role] ?? $role) ?>
                                    </span>
                                    <?php if ((int) $u['actif'] !== 1): ?>
                                        <span class="badge bg-secondary-lt">Désactivé</span>
                                    <?php endif; ?>
                                </div>

                                <div class="text-secondary small">
                                    Créé le <?= $e(mb_substr((string) $u['created_at'], 0, 10)) ?>
                                </div>
                            </div>

                            <div class="card-footer d-flex">
                                <button class="btn btn-outline-secondary btn-sm flex-fill me-2"
                                        data-bs-toggle="modal" data-bs-target="#modal-utilisateur"
                                        data-edition-utilisateur
                                        data-id="<?= $id ?>"
                                        data-nom="<?= $e((string) $u['nom']) ?>"
                                        data-email="<?= $e((string) $u['email']) ?>"
                                        data-role="<?= $e($role) ?>"
                                        data-actif="<?= (int) $u['actif'] ?>"
                                        data-avatar="<?= $e((string) ($u['avatar'] ?? '')) ?>"
                                        data-avatar-url="<?= $e(User::avatarUrl($u['avatar'] ?? null) ?? '') ?>"
                                        data-initiales="<?= $e(User::initiales((string) $u['nom'])) ?>"
                                        title="Modifier <?= $e((string) $u['nom']) ?>">
                                    <?= $icone('pen', 'me-1') ?>Éditer
                                </button>

                                <?php if (!$moiMeme): ?>
                                    <form method="post"
                                          action="<?= $e($base_url . '/admin/utilisateurs/supprimer') ?>"
                                          data-confirmer="Supprimer le compte de <?= $e((string) $u['nom']) ?> ? Cette action est définitive.">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm flex-fill"
                                                title="Supprimer <?= $e((string) $u['nom']) ?>">
                                            <?= $icone('trash-can', 'me-1') ?>Supprimer
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h4 class="mb-2">Matrice des rôles</h4>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Rôle</th><th>Consultation</th><th>Écriture (flotte, entretien, incidents)</th>
                    <th>Utilisateurs &amp; paramétrage</th></tr></thead>
                <tbody>
                <tr>
                    <td><span class="badge bg-secondary-lt">Lecture Seule</span></td>
                    <td>✔</td><td>—</td><td>—</td>
                </tr>
                <tr>
                    <td><span class="badge bg-blue-lt">Modification</span></td>
                    <td>✔</td><td>✔</td><td>—</td>
                </tr>
                <tr>
                    <td><span class="badge bg-purple-lt">Administration</span></td>
                    <td>✔</td><td>✔</td><td>✔</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal modal-blur fade" id="modal-utilisateur" tabindex="-1" role="dialog" aria-hidden="true"
     aria-labelledby="titre-utilisateur">
    <div class="modal-dialog" role="document">
        <!-- `enctype` est indispensable : sans lui le fichier n'est jamais transmis. -->
        <form class="modal-content" method="post" enctype="multipart/form-data"
              action="<?= $e($base_url . '/admin/utilisateurs/enregistrer') ?>"
              id="formulaire-utilisateur">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="0" id="utilisateur-id">

            <div class="modal-header">
                <h5 class="modal-title" id="titre-utilisateur">Nouvel utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <div class="modal-body">
                <div class="text-center mb-4">
                    <!-- L'aperçu reflète le fichier choisi ; à défaut, l'image en
                         cours ou les initiales. Le fond est posé par le script. -->
                    <span class="avatar avatar-xl avatar-rounded" id="u_avatar_apercu"
                          role="img" aria-label="Aperçu de l'avatar">?</span>
                    <input type="file" name="avatar" id="u_avatar_fichier"
                           class="visually-hidden" accept="image/jpeg,image/png,image/webp"
                           data-apercu="#u_avatar_apercu">
                    <div class="mt-2">
                        <!--
                            Le champ de fichier est réellement masqué (`visually-hidden`,
                            1 px clippé). Appeler `input.click()` depuis un bouton ne
                            repose alors que sur une activation programmatique : selon le
                            navigateur et la version, le sélecteur ne s'ouvre pas, sans
                            erreur. Une étiquette `for` délègue l'activation au champ de
                            façon native — le clic ouvre le sélecteur sans JavaScript,
                            et l'association reste annoncée aux lecteurs d'écran.
                        -->
                        <label for="u_avatar_fichier" id="u_avatar_choisir" class="btn btn-sm btn-outline-secondary">
                            <?= $icone('user', 'me-1') ?>Choisir une image
                        </label>
                    </div>
                    <div class="form-hint mt-1" id="u_avatar_aide">
                        JPG, PNG ou WEBP, 8 Mo maximum. Sans image, les initiales du nom sont affichées.
                    </div>
                    <label class="form-check form-switch d-inline-flex mt-2" id="u_avatar_supprimer_bloc" hidden>
                        <input class="form-check-input" type="checkbox" name="avatar_supprimer" value="1"
                               id="u_avatar_supprimer">
                        <span class="form-check-label">Supprimer l'image actuelle</span>
                    </label>
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="u_nom">Nom et prénom</label>
                    <input type="text" class="form-control" id="u_nom" name="nom" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="u_email">Adresse email</label>
                    <input type="email" class="form-control" id="u_email" name="email" required maxlength="190">
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="u_role">Rôle</label>
                    <select class="form-select" id="u_role" name="role" required>
                        <?php foreach ($roles as $cle => $libelle): ?>
                            <option value="<?= $e($cle) ?>"><?= $e($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="u_password">Mot de passe</label>
                    <input type="password" class="form-control" id="u_password" name="password" autocomplete="new-password"
                           minlength="8" placeholder="8 caractères minimum">
                    <small class="form-hint" id="aide-mdp">Obligatoire à la création. Laisser vide pour conserver l'existant.</small>
                </div>
                <label class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="actif" value="1" id="u_actif" checked>
                    <span class="form-check-label">Compte actif</span>
                </label>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>