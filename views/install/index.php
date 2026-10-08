<?php
declare(strict_types=1);

/**
 * Assistant d'installation initial — vue autonome (hors layout applicatif).
 *
 * Deux formulaires enchaînés :
 *   Étape 1 : paramètres MySQL, test de connexion AJAX avant poursuite.
 *   Étape 2 : compte administrateur, qui déclenche l'exécution (étape 3).
 *
 * @var array $etat, $etapes
 * @var array $saisie : valeurs de l'étape 1 à réafficher après un re-rendu serveur
 * @var string $admin : nom d'utilisateur saisi, réaffiché après un re-rendu serveur
 * @var ?string $erreur
 * @var string[] $realisees
 * @var string $version
 */

use Core\Csrf;
use Core\Url;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$erreur = $erreur ?? null;
// Valeurs de l'étape 1 conservées d'un affichage à l'autre. Le mot de passe MySQL
// n'y figure pas : un secret n'est jamais réémis dans la réponse HTML.
$saisie += ['host' => 'localhost', 'port' => '3306', 'database' => 'flotteo', 'username' => 'flotteo'];
// Nom d'utilisateur pré-rempli après un re-rendu serveur ; les deux secrets ne
// sont jamais réémis, ni celui de l'administrateur ni celui de MySQL.
$admin = (string) ($admin ?? '');
?>

<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1a2334">
    <title>Installation — Flotteo</title>

    <link rel="icon" type="image/svg+xml" href="<?= $e(Url::asset('img/flotteo.svg')) ?>">
    <link rel="stylesheet" href="<?= $e(Url::asset('tabler/css/tabler.min.css')) ?>">
    <link rel="stylesheet" href="<?= $e(Url::asset('css/flotteo.css')) ?>">
</head>
<body class="install-body">

<div class="page">
    <div class="page-body">
        <div class="container install-container py-4">

            <!-- ============================ En-tête ============================ -->
            <div class="install-entete text-center mb-4">
                <div class="install-sceau" aria-hidden="true">
                    <svg class="icon" viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor"
                         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 17h-2v-5l2 -5h14l2 5v5h-2"/><path d="M5 12h14"/>
                        <circle cx="7" cy="14" r="1"/><circle cx="17" cy="14" r="1"/>
                    </svg>
                </div>
                <h1 class="install-titre">Flotteo</h1>
                <p class="install-sous-titre">Assistant d'installation &middot; version <?= $e($version) ?></p>
                <p class="text-secondary small mb-0">
                    Trois étapes : connexion à la base, création du compte administrateur, initialisation du schéma.
                </p>
            </div>

            <?php if ($erreur !== null): ?>
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-title">Installation interrompue</h4>
                    <div class="text-secondary"><?= $e($erreur) ?></div>
                    <?php if (!empty($realisees)): ?>
                        <hr class="my-2">
                        <div class="small">
                            <strong>Étapes déjà réalisées :</strong>
                            <ul class="mb-0 mt-1">
                                <?php foreach ($realisees as $etape): ?>
                                    <li><?= $e($etape) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ========================= Progression =========================== -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row text-center g-0 install-etapes">
                        <div class="col-4" data-etape-marqueur="1">
                            <span class="etape-puce">1</span>
                            <div class="etape-libelle">Base de données</div>
                        </div>
                        <div class="col-4" data-etape-marqueur="2">
                            <span class="etape-puce">2</span>
                            <div class="etape-libelle">Administrateur</div>
                        </div>
                        <div class="col-4" data-etape-marqueur="3">
                            <span class="etape-puce">3</span>
                            <div class="etape-libelle">Initialisation</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== Étape 1 : base de données ================= -->
            <div class="card mb-3" id="etape-1">
                <div class="card-header">
                    <h2 class="card-title install-sous-titre">Étape 1 — Paramètres de la base de données</h2>
                    <div class="card-actions text-secondary small">MySQL / MariaDB</div>
                </div>
                <div class="card-body">
                    <form id="formulaire-bdd" method="post" action="<?= $e(Url::to('/api/install/tester')) ?>" novalidate>
                        <?= Csrf::field() ?>
                        <div class="row g-3">
                            <div class="col-6 col-md-8">
                                <label class="form-label required" for="db_host">Hôte du serveur</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" value="<?= $e($saisie['host']) ?>"
                                       required maxlength="255" autocomplete="off">
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label required" for="db_port">Port</label>
                                <input type="number" class="form-control" id="db_port" name="db_port" value="<?= $e($saisie['port']) ?>"
                                       min="1" max="65535" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label required" for="db_name">Nom de la base</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" value="<?= $e($saisie['database']) ?>"
                                       required maxlength="64" pattern="[A-Za-z0-9_\-]{1,64}" autocomplete="off">
                                <small class="form-hint">Lettres, chiffres, tirets et soulignés uniquement.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label required" for="db_username">Utilisateur MySQL</label>
                                <input type="text" class="form-control" id="db_username" name="db_username"
                                       value="<?= $e($saisie['username']) ?>" required maxlength="80" autocomplete="username">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="db_password">Mot de passe MySQL</label>
                                <input type="password" class="form-control" id="db_password" name="db_password"
                                       maxlength="200" autocomplete="current-password">
                                <small class="form-hint">Peut être vide si l'utilisateur MySQL n'en exige pas.</small>
                            </div>
                        </div>

                        <div class="mt-3" id="retour-test" role="status" aria-live="polite"></div>
                    </form>
                </div>
                <div class="card-footer install-actions">
                    <button type="button" class="btn btn-primary" id="bouton-tester">
                        <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12l5 5l10 -10"/>
                        </svg>
                        Tester la connexion
                    </button>
                    <div class="install-note">
                        L'utilisateur MySQL doit disposer des droits <code>CREATE</code> et <code>ALTER</code>.
                    </div>
                </div>
            </div>

            <!-- ================ Étape 2 : compte administrateur ==============
                 Le `d-none` initial porte sur la carte `etape-2` et non sur le
                 formulaire : `install.js` bascule précisément ces blocs. Un
                 `d-none` sur le <form> masquerait la carte même une fois celle-ci
                 rendue visible, et l'étape 2 s'afficherait vide. -->
            <form method="post" action="<?= $e(Url::to('/install')) ?>" id="formulaire-admin" novalidate>
                <?= Csrf::field() ?>

                <!-- Les paramètres BDD sont réémis en champs masqués : l'étape 1
                     n'est jamais recalculée ni perdue lors de la soumission finale. -->
                <input type="hidden" name="db_host"     id="repren_host">
                <input type="hidden" name="db_port"     id="repren_port">
                <input type="hidden" name="db_name"     id="repren_name">
                <input type="hidden" name="db_username" id="repren_user">
                <input type="hidden" name="db_password" id="repren_password">

                <div class="card mb-3 d-none" id="etape-2">
                    <div class="card-header">
                        <h2 class="card-title install-sous-titre">Étape 2 — Compte administrateur initial</h2>
                        <div class="card-actions text-secondary small">Droits maximaux</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label required" for="nom_d_utilisateur">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="nom_d_utilisateur" name="nom_d_utilisateur"
                                       placeholder="nom_d_utilisateur" value="<?= $e($admin) ?>"
                                       required maxlength="120" autocomplete="nickname">
                                <small class="form-hint">Enregistré dans la colonne <code>utilisateurs.nom</code>.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label required" for="admin_email">Adresse e-mail</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email"
                                       required maxlength="190" autocomplete="username">
                                <small class="form-hint">Sert d'identifiant de connexion.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label required" for="admin_password">Mot de passe</label>
                                <input type="password" class="form-control" id="admin_password" name="admin_password"
                                       required minlength="10" autocomplete="new-password">
                                <div class="jauge mt-2" id="jauge-force">
                                    <span style="width:0"></span>
                                </div>
                                <small class="form-hint" id="aide-force">
                                    10 caractères minimum, avec au moins une minuscule, une majuscule et un chiffre.
                                </small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label required" for="admin_password_confirm">Confirmation du mot de passe</label>
                                <input type="password" class="form-control" id="admin_password_confirm" name="admin_password_confirm"
                                       required minlength="10" autocomplete="new-password">
                                <small class="form-hint" id="aide-confirmation">&nbsp;</small>
                            </div>
                        </div>

                        <hr class="my-3">

                        <h3 class="install-sous-titre mb-2">Recherche de mise à jour (facultatif)</h3>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="github_token">Jeton personnel GitHub</label>
                                <input type="password" class="form-control font-monospace" id="github_token"
                                       name="github_token" maxlength="255" autocomplete="off"
                                       spellcheck="false" placeholder="ghp_…">
                                <small class="form-hint">
                                    Écrit dans <code>config/secrets.php</code>, hors base et hors archive de sauvegarde.
                                    Laissez le champ vide pour rester en accès anonyme : la recherche fonctionne alors sur un
                                    dépôt public, dans la limite de 60 requêtes par heure et par adresse IP.
                                    Périmètre recommandé : lecture seule sur le dépôt des versions.
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer install-actions">
                        <button type="button" class="btn btn-outline-secondary" id="retour-etape-1">
                            <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M5 12h14"/><path d="M12 5l-7 7l7 7"/>
                            </svg>
                            Retour
                        </button>
                        <button type="submit" class="btn btn-primary install-action-final" id="bouton-installer">
                            <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M5 12l5 5l10 -10"/>
                            </svg>
                            Installer Flotteo
                        </button>
                    </div>
                </div>
            </form>

            <!-- ================ Étape 3 : récapitulatif ============== -->
            <div class="card d-none" id="etape-3">
                <div class="card-header">
                    <h2 class="card-title install-sous-titre">Étape 3 — Initialisation en cours</h2>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0" id="liste-etapes">
                        <li class="mb-2"><span class="etape-etat">En attente</span> Création de la base de données</li>
                        <li class="mb-2"><span class="etape-etat">En attente</span> Application du schéma
                            (<span data-compteur-tables><?= count($etapes) ?></span> tables)</li>
                        <li class="mb-2"><span class="etape-etat">En attente</span> Création du compte administrateur</li>
                        <li class="mb-0"><span class="etape-etat">En attente</span> Écriture de la configuration et verrouillage</li>
                    </ul>
                    <div class="progress progress-sm mt-3" role="progressbar" aria-label="Progression">
                        <div class="progress-bar progress-bar-indeterminate" id="barre-progression" style="width:100%"></div>
                    </div>
                    <p class="text-secondary small mt-2 mb-0">
                        Ne fermez pas cette page. La redirection vers la connexion survient à la fin.
                    </p>
                </div>
            </div>

            <!-- ================== Schéma qui sera créé ================== -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Schéma qui sera créé</h3>
                    <div class="card-actions text-secondary small">
                        <?= count($etapes) ?> tables
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($etapes as $table): ?>
                            <div class="col-6 col-md-3">
                                <span class="badge bg-blue-lt font-monospace"><?= $e($table) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <p class="text-center text-secondary small mt-3 mb-0">
                L'assistant se verrouille automatiquement dès la création du compte administrateur.
                Aucune donnée n'est transmise à un service externe.
            </p>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="flotteo-toasts"></div>

<script src="<?= $e(Url::asset('tabler/js/tabler.min.js')) ?>"></script>
<script>window.FLOTTEO = { base: <?= json_encode(Url::baseRoute(), JSON_UNESCAPED_SLASHES) ?>, token: <?= json_encode(Csrf::token(), JSON_UNESCAPED_SLASHES) ?> };</script>
<script src="<?= $e(Url::asset('js/app.js')) ?>"></script>
<script src="<?= $e(Url::asset('js/install.js')) ?>"></script>
</body>
</html>