<?php
declare(strict_types=1);

/**
 * Section « Alertes » : activation, paliers d'anticipation, destinataire des
 * rappels, envoi manuel et tâche planifiée.
 *
 * L'interrupteur d'activation est placé dans l'en-tête de la carte par le
 * gabarit de la page ; il est ici seulement décrit. Le récapitulatif condensé
 * part vers une adresse unique, les véhicules étant regroupés par palier.
 *
 * @var array $valeurs, $paliers
 * @var string $base_url
 */

use Core\Csrf;
use Core\Icon;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);

$v = static fn (string $cle): string => (string) ($valeurs[$cle] ?? '');
$actif = $v('alerte_active') === '1';
?>

<form id="form-alertes-reglages" method="post" autocomplete="off"
      action="<?= $e($base_url . '/admin/parametres/alertes/enregistrer') ?>">
    <?= Csrf::field() ?>

    <div class="card-body">

        <div class="mb-4">
            <h4 class="mb-3"><?= $icone('bell', 'me-2') ?>Activation</h4>
            <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="alerte_active"
                       name="alerte_active" value="1" <?= $actif ? 'checked' : '' ?>>
                <span class="form-check-label">
                    <?= $actif ? 'Alertes actives' : 'Alertes suspendues' ?>
                </span>
            </label>
            <p class="form-hint mb-0">
                Suspendre les alertes n'efface aucun réglage : le récapitulatif et la
                tâche planifiée sont simplement interrompus.
            </p>
        </div>

        <div class="mb-4">
            <h4 class="mb-3"><?= $icone('calendar-days', 'me-2') ?>Paliers d'anticipation</h4>
            <div class="row g-3">
                <?php foreach ([1, 2, 3] as $rang): ?>
                    <?php $cle = 'alerte_palier_' . $rang; ?>
                    <div class="col-md-4">
                        <label class="form-label" for="<?= $e($cle) ?>">Palier <?= $rang ?></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="<?= $e($cle) ?>"
                                   name="<?= $e($cle) ?>" min="0" max="3650" step="1"
                                   value="<?= $e($v($cle)) ?>">
                            <span class="input-group-text">jours avant</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="form-hint mb-0">
                Délai avant la date de sortie prévue. Paliers actifs :
                <strong><?= $e(implode(' / ', $paliers)) ?> jours</strong>.
                Un palier à 0 est ignoré.
            </p>
        </div>

        <div class="mb-0">
            <label class="form-label required" for="email_gestionnaire">
                <?= $icone('envelope', 'me-1') ?>Destinataire des rappels
            </label>
            <input type="email" class="form-control" id="email_gestionnaire"
                   name="email_gestionnaire" form="form-alertes-reglages"
                   maxlength="190" placeholder="gestion@exemple.fr"
                   value="<?= $e($v('email_gestionnaire')) ?>">
            <p class="form-hint mb-0">
                Adresse unique qui reçoit le récapitulatif des véhicules concernés.
                Laisser vide suspend l'envoi.
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
    <h4 class="mb-3"><?= $icone('gears', 'me-2') ?>Exécution</h4>

    <div class="row g-3">
        <div class="col-md-6">
            <button type="submit" form="form-alertes-execution" class="btn btn-outline-primary"
                    <?= $actif ? '' : 'disabled' ?>>
                <?= $icone('envelope', 'me-2') ?>Envoyer le récapitulatif
            </button>
            <?php if (!$actif): ?>
                <p class="form-hint mb-0">
                    Le bouton est inactif : réactivez les alertes ci-dessus pour
                    pouvoir déclencher un envoi.
                </p>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="cron-flotteo">Tâche planifiée (cron)</label>
            <div class="input-group">
                <input type="text" class="form-control font-monospace text-break" id="cron-flotteo"
                       readonly value="5 7 * * 1 /usr/bin/php <?= $e(dirname(__DIR__, 2)) ?>/scripts/alert_cron.php">
                <button type="button" class="btn btn-outline-secondary" data-copier="cron-flotteo"
                        data-bs-toggle="tooltip" data-bs-title="Copier la commande"
                        aria-label="Copier la commande cron">
                    <?= $icone('file-lines') ?>
                </button>
            </div>
            <p class="form-hint mb-0">Chaque lundi à 07 h 05, si les alertes sont actives.</p>
        </div>
    </div>
</div>

<?php if (!$actif): ?>
    <div class="card-body border-top">
        <div class="alert alert-warning mb-0">
            <?= $icone('triangle-exclamation', 'me-1') ?>
            Aucun récapitulatif ne partira tant que les alertes sont suspendues.
        </div>
    </div>
<?php endif; ?>

<!-- Formulaire d'exécution, hors du formulaire de réglages pour éviter tout
     formulaire imbriqué. -->
<form id="form-alertes-execution" method="post" action="<?= $e($base_url . '/alertes/executer') ?>" hidden>
    <?= Csrf::field() ?>
</form>