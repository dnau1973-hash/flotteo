<?php
declare(strict_types=1);

/**
 * Registres documentaires, rendus dans le layout général de l'application.
 *
 * Gabarit repris de la page Paramètres : navigation des quatre registres à
 * gauche (un quart), document courant à droite (trois quarts). Le registre
 * affiché est porté par la route (`/docs/{cle}`).
 *
 * Le corps du document est du HTML produit par `Core\Markdown` à partir des
 * sources Markdown versionnées avec le projet. Il n'est donc pas échappé :
 * l'échappement est déjà appliqué au moment de la conversion, et la sources
 * n'est jamais une saisie utilisateur.
 *
 * @var string $cle        Registre affiché
 * @var array $document    Description du registre affiché
 * @var array $registres   Tous les registres, indexés par clé
 * @var string $contenu    Document converti en HTML
 */

use Core\Icon;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);
?>

<div class="row g-4">

    <!-- ============ Registres : un quart de la largeur ============ -->
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $icone('book', 'me-2') ?>Documentation</h3>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($registres as $cleRegistre => $definition): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-start py-3<?= $cleRegistre === $cle ? ' active' : '' ?>"
                       href="<?= $e(\Core\Url::to('/docs/' . $cleRegistre)) ?>"
                       <?= $cleRegistre === $cle ? 'aria-current="page"' : '' ?>>
                        <span class="me-3 mt-1"><?= $icone($definition['icone']) ?></span>
                        <span>
                            <strong><?= $e($definition['libelle']) ?></strong>
                            <small class="d-block text-secondary"><?= $e($definition['resume']) ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ============ Document courant : trois quarts ============ -->
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?= $icone($document['icone'], 'me-2') ?><?= $e($document['titre']) ?>
                </h3>
                <div class="card-actions">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= $e(\Core\Url::to('/dashboard')) ?>">
                        <?= $icone('arrow-right-arrow-left', 'me-1') ?>Retour à l'application
                    </a>
                </div>
            </div>

            <div class="card-body markdown">
<?= $contenu ?>
            </div>

            <div class="card-footer text-secondary small">
                Document affiché depuis <code><?= $e($document['fichier']) ?></code> et converti à la
                demande — la vue HTML autonome correspondante est produite par
                <code>php scripts/build_docs.php</code>.
            </div>
        </div>
    </div>

</div>