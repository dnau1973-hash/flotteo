<?php
declare(strict_types=1);

/**
 * Section « Relevés kilométriques » : fréquence de saisie, destinataire des relances
 * et délai de grâce avant relance automatique.
 *
 * @var array $valeurs
 * @var string $base_url
 */

use Core\Csrf;
use Core\Icon;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);

$v = static fn (string $cle, string $defaut = ''): string => (string) ($valeurs[$cle] ?? $defaut);
$actif = $v('km_relance_active', '1') === '1';
$recurrence = $v('km_recurrence', 'mensuelle');
$destinataireType = $v('km_destinataire_type', 'gestionnaire');
?>

<form id="form-kilometrage-reglages" method="post" autocomplete="off"
      action="<?= $e($base_url . '/admin/parametres/kilometrage/enregistrer') ?>">
    <?= Csrf::field() ?>

    <div class="card-body">

        <div class="mb-4">
            <h4 class="mb-3"><?= $icone('bell', 'me-2') ?>Relances automatiques</h4>
            <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="km_relance_active"
                       name="km_relance_active" value="1" <?= $actif ? 'checked' : '' ?>>
                <span class="form-check-label">
                    <?= $actif ? 'Système de relance actif' : 'Relances automatiques suspendues' ?>
                </span>
            </label>
            <p class="form-hint mb-0">
                Si suspendu, aucune relance par email ne sera envoyée en cas de retard ou d'incomplétude.
            </p>
        </div>

        <div class="mb-4">
            <h4 class="mb-3"><?= $icone('calendar-days', 'me-2') ?>Fréquence & Délai de grâce</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="km_recurrence">Récurrence attendue</label>
                    <select class="form-select" id="km_recurrence" name="km_recurrence">
                        <option value="mensuelle" <?= $recurrence === 'mensuelle' ? 'selected' : '' ?>>Mensuelle (fin de mois)</option>
                        <option value="hebdomadaire" <?= $recurrence === 'hebdomadaire' ? 'selected' : '' ?>>Hebdomadaire (fin de semaine)</option>
                    </select>
                    <p class="form-hint mb-0">Cadence de soumission obligatoire des relevés.</p>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="km_delai_jours_relance">Délai de grâce avant relance</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="km_delai_jours_relance"
                               name="km_delai_jours_relance" min="1" max="31" step="1"
                               value="<?= $e($v('km_delai_jours_relance', '3')) ?>">
                        <span class="input-group-text">jours après l'échéance</span>
                    </div>
                    <p class="form-hint mb-0">Exemple : 3 jours après la fin du mois écoulé.</p>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <h4 class="mb-3"><?= $icone('envelope', 'me-2') ?>Destinataire des notifications</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="km_destinataire_type">Type de destinataire</label>
                    <select class="form-select" id="km_destinataire_type" name="km_destinataire_type" onchange="basculerDestinataireKm()">
                        <option value="gestionnaire" <?= $destinataireType === 'gestionnaire' ? 'selected' : '' ?>>Gestionnaire de flotte (email global)</option>
                        <option value="fixe" <?= $destinataireType === 'fixe' ? 'selected' : '' ?>>Adresse dédiée (RH / Comptabilité / Manager)</option>
                    </select>
                </div>
                <div class="col-md-6" id="bloc-km-email-fixe" <?= $destinataireType !== 'fixe' ? 'style="display:none;"' : '' ?>>
                    <label class="form-label required" for="km_email_fixe">Adresse email dédiée</label>
                    <input type="email" class="form-control" id="km_email_fixe" name="km_email_fixe"
                           placeholder="rh-flotte@exemple.fr" value="<?= $e($v('km_email_fixe')) ?>">
                    <p class="form-hint mb-0">Cette adresse recevra toutes les alertes de non-déclaration.</p>
                </div>
            </div>
        </div>

    </div>

    <div class="card-footer d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <?= $icone('check', 'me-2') ?>Enregistrer les paramètres
        </button>
    </div>
</form>

<div class="card-body border-top">
    <h4 class="mb-3"><?= $icone('gears', 'me-2') ?>Tâche planifiée (CRON)</h4>
    <div class="row g-3">
        <div class="col-md-12">
            <label class="form-label" for="cron-kilometrage">Commande à planifier dans crontab</label>
            <div class="input-group">
                <input type="text" class="form-control font-monospace text-break" id="cron-kilometrage"
                       readonly value="0 7 * * * /usr/bin/php <?= $e(dirname(__DIR__, 2)) ?>/scripts/mileage_cron.php">
                <button type="button" class="btn btn-outline-secondary" data-copier="cron-kilometrage"
                        data-bs-toggle="tooltip" data-bs-title="Copier la commande"
                        aria-label="Copier la commande cron">
                    <?= $icone('file-lines') ?>
                </button>
            </div>
            <p class="form-hint mb-0">Exécution quotidienne suggérée (ex. tous les matins à 07h00).</p>
        </div>
    </div>
</div>

<script>
function basculerDestinataireKm() {
    const sel = document.getElementById('km_destinataire_type');
    const bloc = document.getElementById('bloc-km-email-fixe');
    if (sel && bloc) {
        bloc.style.display = sel.value === 'fixe' ? 'block' : 'none';
    }
}
</script>

