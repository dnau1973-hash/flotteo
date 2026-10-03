<?php
declare(strict_types=1);

/**
 * Profil personnel : identité, email et changement de mot de passe.
 *
 * @var array $utilisateur
 * @var string $base_url
 */

use Core\Csrf;
?>
<div class="row row-deck row-cards">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Identité du compte</h3></div>
            <div class="card-body">
                <form method="post" action="<?= htmlspecialchars($base_url . '/profil', ENT_QUOTES, 'UTF-8') ?>">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="nom">Nom et prénom</label>
                        <input type="text" class="form-control" id="nom" name="nom" required
                               value="<?= htmlspecialchars((string) $utilisateur['nom'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Adresse email</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?= htmlspecialchars((string) $utilisateur['email'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="mb-3">
                        <span class="form-label">Rôle attribué</span>
                        <input type="text" class="form-control" disabled
                               value="<?= htmlspecialchars(\Models\User::ROLES[(string) $utilisateur['role']] ?? (string) $utilisateur['role'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <hr class="my-4">
                    <h4 class="mb-3">Changer de mot de passe</h4>
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
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                               autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </form>
            </div>
        </div>
    </div>
</div>
