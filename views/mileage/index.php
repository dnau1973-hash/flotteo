<?php
declare(strict_types=1);

/**
 * Interface de saisie rapide et en masse des relevés d'odomètre mensuels.
 *
 * @var array $lignes
 * @var string $periode, $periodePrecedente, $periodeSuivante, $labelPeriode, $base_url
 * @var int $nbManquants
 */

use Core\Auth;
use Core\Icon;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);
$modifiable = Auth::can(Auth::ROLE_MODIFICATION);
$totalVehicules = count($lignes);
$totalSaisis = $totalVehicules - $nbManquants;
$pourcentage = $totalVehicules > 0 ? round(($totalSaisis / $totalVehicules) * 100) : 0;
?>

<div class="row g-3">
    <!-- Barre supérieure : navigation temporelle & synthèse d'avancement -->
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center g-3">
                    <div class="col-auto">
                        <div class="btn-group">
                            <a href="<?= $e($base_url . '/kilometrage?periode=' . $periodePrecedente) ?>" class="btn btn-outline-secondary" title="Mois précédent">
                                <?= $icone('arrow-up', 'rotate-270') ?> ‹
                            </a>
                            <span class="btn btn-outline-secondary disabled fw-bold text-dark px-3 fs-5">
                                <?= $e($labelPeriode) ?>
                            </span>
                            <a href="<?= $e($base_url . '/kilometrage?periode=' . $periodeSuivante) ?>" class="btn btn-outline-secondary" title="Mois suivant">
                                › <?= $icone('arrow-up', 'rotate-90') ?>
                            </a>
                        </div>
                    </div>

                    <div class="col-auto">
                        <div class="btn-group" role="tablist">
                            <button type="button" class="btn btn-sm btn-outline-primary active" id="btn-filtre-tous" onclick="filtrerTableau('tous')">
                                Tous (<?= $totalVehicules ?>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="btn-filtre-manquants" onclick="filtrerTableau('manquants')">
                                <?= $icone('triangle-exclamation', 'me-1') ?>En attente (<?= $nbManquants ?>)
                            </button>
                        </div>
                    </div>

                    <div class="col ms-auto">
                        <div class="d-flex justify-content-end align-items-center gap-3">
                            <div class="text-end">
                                <div class="text-secondary small">Progression du mois</div>
                                <strong><?= $totalSaisis ?> / <?= $totalVehicules ?> saisis (<?= $pourcentage ?>%)</strong>
                            </div>
                            <div class="progress progress-sm" style="width: 140px;">
                                <div class="progress-bar bg-success" style="width: <?= $pourcentage ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Matrice de saisie rapide type tableur -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">
                    <?= $icone('gauge-high', 'me-2') ?>Matrice mensuelle des compteurs
                </h3>
                <span class="text-secondary small d-none d-md-inline">
                    💡 <em>Utilisez la touche <kbd>Entrée</kbd> ou <kbd>↓</kbd> pour valider et sauter au véhicule suivant.</em>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover" id="matrice-kilometrage">
                    <thead>
                        <tr>
                            <th class="w-1 text-center">État</th>
                            <th>Véhicule</th>
                            <th>Entité</th>
                            <th>Index de référence</th>
                            <th style="width: 260px;">Index fin <?= $e($labelPeriode) ?></th>
                            <th>Distance calculée</th>
                            <th class="w-1 text-end">Verrou</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lignes === []): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    Aucun véhicule actif trouvé dans le parc.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lignes as $idx => $r): ?>
                                <tr class="ligne-vehicule" data-statut="<?= $r['est_saisi'] ? 'saisi' : 'manquant' ?>">
                                    <!-- État visuel -->
                                    <td class="text-center">
                                        <?php if ($r['est_saisi']): ?>
                                            <span class="badge bg-success badge-dot me-1" title="Relevé validé"></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger badge-dot me-1" title="En attente de relevé"></span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Véhicule -->
                                    <td>
                                        <a href="<?= $e($base_url . '/vehicules/voir?id=' . $r['vehicule_id']) ?>" class="fw-bold text-reset text-decoration-none">
                                            <?= $e($r['immatriculation']) ?>
                                        </a>
                                        <div class="text-secondary small"><?= $e($r['marque_modele']) ?></div>
                                    </td>

                                    <!-- Entité -->
                                    <td class="text-secondary small">
                                        <?= $e($r['entite']) ?>
                                    </td>

                                    <!-- Index M-1 -->
                                    <td>
                                        <span class="text-secondary fw-semibold km-prec-label" data-km="<?= (int) $r['km_precedent'] ?>">
                                            <?= number_format((int) $r['km_precedent'], 0, ',', ' ') ?> km
                                        </span>
                                    </td>

                                    <!-- Champ Inline Editing -->
                                    <td>
                                        <div class="input-group input-group-flat">
                                            <input type="number" 
                                                   class="form-control fw-bold input-km" 
                                                   tabindex="<?= $idx + 1 ?>"
                                                   data-id="<?= (int) $r['vehicule_id'] ?>"
                                                   data-km-prec="<?= (int) $r['km_precedent'] ?>"
                                                   value="<?= $r['index_actuel'] !== null ? (int) $r['index_actuel'] : '' ?>"
                                                   placeholder="Ex: <?= (int) $r['km_precedent'] + 500 ?>"
                                                   <?= $r['verrouille'] || !$modifiable ? 'disabled' : '' ?>>
                                            <span class="input-group-text text-secondary pe-2">km</span>
                                        </div>
                                    </td>

                                    <!-- Delta parcouru -->
                                    <td>
                                        <span class="badge badge-delta <?= $r['est_saisi'] ? 'bg-success-lt' : 'bg-secondary-lt' ?> fs-6">
                                            <?= $r['est_saisi'] ? '+' . number_format((int) $r['distance'], 0, ',', ' ') . ' km' : '—' ?>
                                        </span>
                                    </td>

                                    <!-- Verrouillage / Modification -->
                                    <td class="text-end">
                                        <?php if ($modifiable): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-icon btn-ghost-secondary btn-toggle-verrou"
                                                    data-id="<?= (int) $r['vehicule_id'] ?>"
                                                    title="<?= $r['verrouille'] ? 'Déverrouiller la saisie' : 'Verrouiller la saisie' ?>">
                                                <?= $r['verrouille'] ? $icone('lock', 'text-warning') : $icone('check', 'text-muted') ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = Array.from(document.querySelectorAll('.input-km'));

    inputs.forEach((input, idx) => {
        // Recalcul visuel en temps réel
        input.addEventListener('input', () => ajusterDeltaVisuel(input));

        // Navigation clavier ultra-rapide (Enter / Flèche Bas / Flèche Haut)
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === 'ArrowDown') {
                e.preventDefault();
                validerEtSauvegarder(input, () => {
                    if (inputs[idx + 1]) inputs[idx + 1].focus();
                });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (inputs[idx - 1]) inputs[idx - 1].focus();
            }
        });

        // Enregistrement lors de la perte de focus
        input.addEventListener('blur', () => validerEtSauvegarder(input));
    });

    // Gestion du clic de bascule de verrouillage
    document.querySelectorAll('.btn-toggle-verrou').forEach(btn => {
        btn.addEventListener('click', () => {
            const tr = btn.closest('tr');
            const input = tr.querySelector('.input-km');
            const vehiculeId = btn.dataset.id;

            fetch(`${window.FLOTTEO.base}/kilometrage/toggle-verrou`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': window.FLOTTEO.token
                },
                body: JSON.stringify({
                    vehicule_id: vehiculeId,
                    periode: '<?= $e($periode) ?>'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    input.disabled = data.verrouille;
                    btn.innerHTML = data.verrouille 
                        ? '<i class="fa-solid fa-lock text-warning"></i>' 
                        : '<i class="fa-solid fa-check text-muted"></i>';
                }
            });
        });
    });
});

function ajusterDeltaVisuel(input) {
    const tr = input.closest('tr');
    const badge = tr.querySelector('.badge-delta');
    const kmPrec = parseInt(input.dataset.kmPrec, 10) || 0;
    const valeur = parseInt(input.value, 10);

    if (isNaN(valeur) || input.value.trim() === '') {
        badge.className = 'badge badge-delta bg-secondary-lt fs-6';
        badge.textContent = '—';
        input.classList.remove('is-invalid');
        return;
    }

    const delta = valeur - kmPrec;
    if (delta < 0) {
        input.classList.add('is-invalid');
        badge.className = 'badge badge-delta bg-danger text-white fs-6';
        badge.textContent = `Erreur (${delta} km)`;
    } else {
        input.classList.remove('is-invalid');
        badge.className = 'badge badge-delta bg-success-lt fs-6';
        badge.textContent = `+${delta.toLocaleString('fr-FR')} km`;
    }
}

function validerEtSauvegarder(input, onDone) {
    const tr = input.closest('tr');
    const kmPrec = parseInt(input.dataset.kmPrec, 10) || 0;
    const valeur = parseInt(input.value, 10);

    // Si vide ou incohérent, on n'enregistre pas
    if (isNaN(valeur) || valeur < kmPrec) {
        if (onDone) onDone();
        return;
    }

    fetch(`${window.FLOTTEO.base}/kilometrage/sauvegarder-rapide`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': window.FLOTTEO.token
        },
        body: JSON.stringify({
            vehicule_id: input.dataset.id,
            periode: '<?= $e($periode) ?>',
            index_km: valeur
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            tr.dataset.statut = 'saisi';
            const dot = tr.querySelector('.badge-dot');
            dot.className = 'badge bg-success badge-dot me-1';
            dot.title = 'Relevé validé';

            // Animation visuelle de confirmation
            input.classList.add('is-valid');
            setTimeout(() => input.classList.remove('is-valid'), 800);

            if (onDone) onDone();
        }
    })
    .catch(() => {
        if (onDone) onDone();
    });
}

function filtrerTableau(mode) {
    const btnTous = document.getElementById('btn-filtre-tous');
    const btnManquants = document.getElementById('btn-filtre-manquants');

    if (mode === 'manquants') {
        btnManquants.classList.add('active');
        btnTous.classList.remove('active');
    } else {
        btnTous.classList.add('active');
        btnManquants.classList.remove('active');
    }

    document.querySelectorAll('.ligne-vehicule').forEach(tr => {
        if (mode === 'manquants') {
            tr.style.display = tr.dataset.statut === 'manquant' ? '' : 'none';
        } else {
            tr.style.display = '';
        }
    });
}
</script>
