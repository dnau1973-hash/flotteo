<?php
declare(strict_types=1);

/**
 * Modale d'importation en masse de véhicules par fichier CSV.
 *
 * @var string $base_url
 * @var array{modeles: array, entites: array, loueurs: array, lieux: array} $nomenclature
 */

use Core\Csrf;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="modal modal-blur fade" id="modal-import-csv" tabindex="-1" role="dialog" aria-hidden="true" aria-labelledby="titre-import-csv">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <form class="modal-content" method="post" action="<?= $e($base_url . '/vehicules/importer-csv') ?>"
              enctype="multipart/form-data" id="form-import-csv">
            <?= Csrf::field() ?>

            <div class="modal-header">
                <h5 class="modal-title" id="titre-import-csv">
                    <svg class="icon me-2 text-primary" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                        <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                        <path d="M12 11v6" /><path d="M9 14l3 3l3 -3" />
                    </svg>
                    Importation en masse de véhicules (CSV)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <div class="modal-body">
                <!-- Spécifications et exemple -->
                <div class="alert alert-info d-flex align-items-start mb-3" role="alert">
                    <svg class="icon me-2 mt-1 flex-shrink-0" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" /><line x1="12" y1="8" x2="12.01" y2="8" /><polyline points="11 12 12 12 12 16 13 16" />
                    </svg>
                    <div class="small">
                        <div class="fw-bold mb-1">Structure attendue des 4 colonnes du fichier CSV :</div>
                        <ol class="mb-2 ps-3">
                            <li><strong>Colonne 1 :</strong> Immatriculation (ex: <code>AA-123-AA</code>)</li>
                            <li><strong>Colonne 2 :</strong> Durée de contrat en mois (ex: <code>36</code>)</li>
                            <li><strong>Colonne 3 :</strong> Kilométrage max (ex: <code>120000</code>)</li>
                            <li><strong>Colonne 4 :</strong> Date d'entrée dans le parc (ex: <code>2024-01-15</code> ou <code>15/01/2024</code>)</li>
                        </ol>
                        <div class="text-secondary">
                            * La <em>date de sortie prévisionnelle</em> est automatiquement calculée à partir de la date d'entrée et de la durée du contrat.
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary small">Besoin d'un modèle prêt à l'emploi ?</span>
                    <a href="<?= $e($base_url . '/vehicules/modele-csv') ?>" class="btn btn-sm btn-outline-secondary">
                        <svg class="icon me-1" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><polyline points="7 11 12 16 17 11" /><line x1="12" y1="4" x2="12" y2="16" />
                        </svg>
                        Télécharger le modèle CSV exemple
                    </a>
                </div>

                <!-- Sélection du fichier -->
                <div class="mb-3">
                    <label class="form-label required" for="fichier_csv">Fichier CSV à importer</label>
                    <input type="file" class="form-control" id="fichier_csv" name="fichier_csv" accept=".csv,text/csv,text/plain" required>
                    <div class="form-text">Formats acceptés : fichier CSV délimité par des points-virgules (<code>;</code>) ou des virgules (<code>,</code>). UTF-8 recommandé.</div>
                </div>

                <!-- Mode de traitement -->
                <div class="mb-3">
                    <label class="form-label required" for="mode_import">Mode de traitement</label>
                    <select class="form-select" id="mode_import" name="mode_import" required>
                        <option value="upsert" selected>Créer les nouveaux véhicules et mettre à jour les existants</option>
                        <option value="create_only">Créer uniquement les nouveaux véhicules (ignorer les immatriculations existantes)</option>
                        <option value="update_only">Mettre à jour uniquement les véhicules existants (ignorer les nouveaux)</option>
                    </select>
                </div>

                <!-- Nomenclature par défaut pour les véhicules créés -->
                <div class="card bg-light-lt border">
                    <div class="card-body">
                        <h4 class="card-title text-muted mb-2">Nomenclature par défaut pour les nouveaux véhicules créés</h4>
                        <p class="text-secondary small mb-3">Ces valeurs seront affectées aux véhicules créés qui ne sont pas encore présents dans le parc.</p>

                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label small" for="csv_modele">Marque &amp; Modèle</label>
                                <select class="form-select form-select-sm" id="csv_modele" name="modele_id">
                                    <?php foreach ($nomenclature['modeles'] as $m): ?>
                                        <option value="<?= (int) $m['id'] ?>">
                                            <?= $e(($m['marque_libelle'] ?? '') !== '' ? $m['marque_libelle'] . ' ' . $m['nom'] : $m['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label small" for="csv_entite">Entité propriétaire</label>
                                <select class="form-select form-select-sm" id="csv_entite" name="entite_id">
                                    <?php foreach ($nomenclature['entites'] as $x): ?>
                                        <option value="<?= (int) $x['id'] ?>"><?= $e($x['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label small" for="csv_loueur">Organisme loueur</label>
                                <select class="form-select form-select-sm" id="csv_loueur" name="loueur_id">
                                    <?php foreach ($nomenclature['loueurs'] as $x): ?>
                                        <option value="<?= (int) $x['id'] ?>"><?= $e($x['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label small" for="csv_lieu">Lieu d'exploitation</label>
                                <select class="form-select form-select-sm" id="csv_lieu" name="lieu_id">
                                    <?php foreach ($nomenclature['lieux'] as $x): ?>
                                        <option value="<?= (int) $x['id'] ?>">
                                            <?= $e($x['nom'] . ($x['ville'] !== null ? ' (' . $x['ville'] . ')' : '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" id="btn-soumettre-import">
                    <svg class="icon me-1" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><polyline points="7 9 12 4 17 9" /><line x1="12" y1="4" x2="12" y2="16" />
                    </svg>
                    Lancer l'importation
                </button>
            </div>
        </form>
    </div>
</div>

