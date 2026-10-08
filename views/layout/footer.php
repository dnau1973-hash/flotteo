<?php
declare(strict_types=1);

/**
 * Pied de page commun : modales globales, scripts, pile de toasts.
 * Les URL sont toutes produites par Core\Url (voir core/Url.php).
 */

use Core\Csrf;
use Core\Url;

$base = Url::base();
?>
            </div><!-- /.container-fluid -->
        </div><!-- /.page-body -->

        <footer class="footer footer-transparent d-print-none">
            <!-- Pied de page : même largeur et même padding latéral que le corps
                 de page, pour que les liens restent alignés sur les cartes. -->
            <div class="container-fluid px-4 px-lg-5">
                <div class="row text-secondary small">
                    <div class="col">
                        <?= htmlspecialchars($app['nom'] ?? 'Flotteo', ENT_QUOTES, 'UTF-8') ?>
                        &middot; v<?= htmlspecialchars($app['version'] ?? '1.0.0', ENT_QUOTES, 'UTF-8') ?>
                        &middot; Pilotage de parc automobile
                    </div>
                    <div class="col-auto">
                        <a href="<?= htmlspecialchars(Url::to('/docs/user_guide'), ENT_QUOTES, 'UTF-8') ?>" class="link-secondary">Guide utilisateur</a>
                        &middot;
                        <a href="<?= htmlspecialchars(Url::to('/docs/changelog'), ENT_QUOTES, 'UTF-8') ?>" class="link-secondary">Journal des modifications</a>
                        &middot;
                        <a href="<?= htmlspecialchars(Url::to('/docs/features'), ENT_QUOTES, 'UTF-8') ?>" class="link-secondary">Fonctionnalités</a>
                        &middot;
                        <a href="<?= htmlspecialchars(Url::to('/docs/qa_recette'), ENT_QUOTES, 'UTF-8') ?>" class="link-secondary">Recette QA</a>
                    </div>
                </div>
            </div>
        </footer>
    </div><!-- /.page-wrapper -->
</div><!-- /.page -->

<button type="button" id="flotteo-retour-haut" class="btn btn-primary retour-haut d-print-none"
        title="Revenir en haut de la page" aria-label="Revenir en haut de la page">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
</button>

<?php require __DIR__ . '/modals.php'; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="flotteo-toasts" aria-live="polite" aria-atomic="true"></div>

<script src="<?= htmlspecialchars(Url::asset('tabler/libs/vanilla-calendar-pro/index.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(Url::asset('tabler/js/tabler.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(Url::asset('tabler/js/apexcharts.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>window.FLOTTEO = { base: <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>, token: <?= json_encode(Csrf::token(), JSON_UNESCAPED_SLASHES) ?> };</script>
<script src="<?= htmlspecialchars(Url::asset('js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php foreach (($scripts ?? []) as $script): ?>
    <?php
    // Un nom sans repertoire est un script de page (`agenda.js`) et provient de
    // `js/` ; un chemin explicite (`fullcalendar/fullcalendar.global.js`) est
    // une bibliotheque embarquee et est resolu tel quel depuis `public/assets`.
    $chemin = str_contains($script, '/') ? $script : 'js/' . $script;
    ?>
    <script src="<?= htmlspecialchars(Url::asset($chemin), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
</body>
</html>
