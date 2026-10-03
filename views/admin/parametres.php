<?php
declare(strict_types=1);

/**
 * Administration : paramètres système.
 *
 * Gabarit de la page, commun à toutes les sections : colonne de navigation à
 * gauche (un quart), contenu contextuel à droite (trois quarts). La section
 * demandée est portée par la route (`/admin/parametres/{section}`) et son rendu
 * est délégué à un fragment dédié.
 *
 * Chaque section conserve son propre envoi : aucun formulaire imbriqué, aucun
 * enchaînement d'onglets à l'état client.
 *
 * @var array $sections  Sections disponibles, indexées par identifiant
 * @var string $section  Section affichée
 * @var array $valeurs   Valeurs courantes des paramètres
 * @var array $paliers   Paliers d'anticipation actifs, en jours
 * @var string $base_url
 */

use Core\Csrf;
use Core\Icon;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);
$meta = $sections[$section];
?>

<div class="row g-4">

    <!-- ============ Sections : un quart de la largeur ============ -->
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $icone('sliders', 'me-2') ?>Paramètres</h3>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($sections as $cle => $definition): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-start py-3<?= $cle === $section ? ' active' : '' ?>"
                       href="<?= $e($base_url . '/admin/parametres/' . $cle) ?>"
                       <?= $cle === $section ? 'aria-current="page"' : '' ?>>
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

    <!-- ============ Contenu contextuel : trois quarts ============ -->
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?= $icone($meta['icone'], 'me-2') ?><?= $e($meta['libelle']) ?>
                </h3>
                <div class="card-actions">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= $e($base_url . '/admin/parametres') ?>">
                        <?= $icone('arrow-right-arrow-left', 'me-1') ?>Réinitialiser la vue
                    </a>
                </div>
            </div>

            <?php $this->partial('admin/_parametres_' . $section, [
                'base_url' => $base_url,
                'valeurs'  => $valeurs,
                'paliers'  => $paliers,
]); ?>
        </div>
    </div>

</div>