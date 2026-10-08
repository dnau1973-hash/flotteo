<?php
declare(strict_types=1);

/**
 * Agenda : calendrier des échéances de fin de contrat, des révisions et des
 * immobilisations.
 *
 * La vue ne fait aucun appel réseau : elle rend le premier mois, calculé par
 * `AgendaController::index()`, et `agenda.js` prend le relais pour les
 * navigations suivantes. Le calendrier ne rerend jamais la page.
 *
 * Les filtres de famille sont des liens, pas des cases à cocher pilotées par le
 * navigateur : le filtrage est refait côté serveur, sur la plage réellement
 * affichée, comme le reste de l'application.
 *
 * @var array $types
 * @var array<string, int> $compte
 * @var int $total
 * @var string $du, $au, $jour, $base_url
 */

use Core\Icon;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);

/** Teinte de pastille par famille, alignée sur `AgendaService::COULEURS`. */
$teintes = [
    'echeance'   => '#f76707',
    'revision'   => '#206bc4',
    'immobilise' => '#d63939',
];

$icones = [
    'echeance'   => 'calendar-days',
    'revision'   => 'screwdriver-wrench',
    'immobilise' => 'wrench',
];

$this->useStyle('fullcalendar/skeleton.css');
$this->useStyle('fullcalendar/themes/classic/theme.css');
$this->useStyle('fullcalendar/themes/classic/palette.css');
?>

<div class="row row-deck row-cards">

    <!-- ======================== Cartes de synthèse ======================== -->
    <div class="col-sm-6 col-lg-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="d-flex align-items-center text-primary">
                    <div class="subheader"><?= $icone('calendar-days', 'me-1') ?>Échéances</div>
                </div>
                <div class="h1 mb-0 mt-2 kpi-valeur text-primary" id="agenda-compte-echeance">
                    <?= (int) ($compte['echeance'] ?? 0) ?>
                </div>
                <div class="kpi-detail">fin de contrat prévue</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="d-flex align-items-center text-primary">
                    <div class="subheader"><?= $icone('screwdriver-wrench', 'me-1') ?>Révisions</div>
                </div>
                <div class="h1 mb-0 mt-2 kpi-valeur text-primary" id="agenda-compte-revision">
                    <?= (int) ($compte['revision'] ?? 0) ?>
                </div>
                <div class="kpi-detail">interventions réalisées</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="d-flex align-items-center text-danger">
                    <div class="subheader"><?= $icone('wrench', 'me-1') ?>Immobilisations</div>
                </div>
                <div class="h1 mb-0 mt-2 kpi-valeur text-danger" id="agenda-compte-immobilise">
                    <?= (int) ($compte['immobilise'] ?? 0) ?>
                </div>
                <div class="kpi-detail">en cours aujourd'hui</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="d-flex align-items-center text-secondary">
                    <div class="subheader"><?= $icone('list', 'me-1') ?>Total affiché</div>
                </div>
                <div class="h1 mb-0 mt-2 kpi-valeur" id="agenda-compte-total"><?= (int) $total ?></div>
                <div class="kpi-detail">
                    du <?= $e((string) str_replace('-', '/', $du)) ?>
                    au <?= $e((string) str_replace('-', '/', $au)) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================= Calendrier ============================= -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $icone('clock', 'me-2') ?>Agenda</h3>
                <div class="card-actions">
                    <div class="btn-group" role="group" aria-label="Familles affichées">
                        <?php foreach ($types as $cle => $libelle): ?>
                            <a class="btn btn-sm btn-primary agenda-filtre"
                               href="<?= $e($base_url . '/agenda') ?>"
                               data-type="<?= $e((string) $cle) ?>"
                               aria-pressed="true">
                                <span class="agenda-pastille"
                                      style="background-color: <?= $e($teintes[$cle] ?? '#646c7a') ?>"></span>
                                <?= $e((string) $libelle) ?>
                                <span class="badge bg-white-lt ms-1" data-compte="<?= $e((string) $cle) ?>">
                                    <?= (int) ($compte[$cle] ?? 0) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($total === 0): ?>
                <div class="empty-state agenda-vide">
                    <?= $icone('circle-info', 'icon mb-2') ?>
                    Aucun événement entre le <?= $e((string) str_replace('-', '/', $du)) ?>
                    et le <?= $e((string) str_replace('-', '/', $au)) ?>.
                    Utilisez les flèches pour parcourir les autres périodes.
                </div>
            <?php endif; ?>

            <div class="card-body<?= $total === 0 ? ' d-none' : '' ?>" id="agenda-conteneur">
                <div id="agenda-calendrier"></div>
            </div>

            <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="text-secondary small">
                    <?= $icone('circle-info', 'me-1') ?>
                    Les échéances et les révisions sont des dates réelles.
                    Les immobilisations ne sont pas datées dans Flotteo : elles
                    apparaissent le jour de la consultation.
                </span>
                <span class="text-secondary small">
                    <?= $icone('eye', 'me-1') ?>
                    Cliquez un événement pour ouvrir la fiche du véhicule.
                </span>
            </div>
        </div>
    </div>
</div>

<?php
// Paramètres repris par `agenda.js`. Ils sont calculés par le contrôleur et
// émis ici plutôt que par un second appel : le premier mois affiché est déjà
// connu, inutile de le redemander.
?>
<script>
window.FLOTTEO_AGENDA = <?= json_encode([
    'jour'    => $jour,
    'types'   => array_keys($types),
    'teintes' => $teintes,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<?php $this->useScript('fullcalendar/fullcalendar.global.js'); ?>
<?php $this->useScript('fullcalendar/themes/classic/global.js'); ?>
<?php $this->useScript('agenda.js'); ?>