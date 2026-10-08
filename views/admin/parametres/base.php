<?php
    declare(strict_types=1);
    use Core\Icon;

    /**
     * Section « Base de données » : informations sur les tables et opérations de nettoyage.
     *
     * @var array $user
     * @var string $base_url
     */
    $e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);
    ?>

    <div class="card-body">
        <h4 class="mb-4"><?= $icone('database', 'me-2') ?>Informations et opérations sur la base de données</h4>

        <div class="alert alert-info">
            <h5 class="alert-title">À propos</h5>
            <p class="mb-0">Cette section permet de visualiser les informations sur les tables et d'effectuer des opérations de nettoyage en toute sécurité. Toutes les opérations sont irréversibles.</p>
        </div>

        <div class="row g-4">
            <?php
            $tables = [
                'utilisateurs'        => 'Utilisateurs',
                'parametres'         => 'Paramètres système',
                'marques'            => 'Marques',
                'modeles'            => 'Modèles',
                'entites'            => 'Entités',
                'loueurs'            => 'Loueurs',
                'lieux'              => 'Lieux',
                'types_intervention'  => 'Types d\'intervention',
                'vehicules'          => 'Véhicules',
                'maintenances'       => 'Révisions & entretien',
                'maintenances_fichiers' => 'Pièces jointes des révisions',
                'incidents'          => 'Incidents & sinistres',
                'incidents_fichiers' => 'Pièces jointes des incidents',
                'sauvegardes'        => 'Sauvegardes',
                'releves_odometre'   => 'Relevés d\'odomètre (kilométrage)',
            ];

            foreach ($tables as $table => $label): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><?= $e($label) ?></h5>
                            <span class="badge bg-primary-lt">Table : <?= $e($table) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Lignes :</span>
                                <span class="badge bg-green-lt" id="row-count-<?= $e($table) ?>">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Charge...
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Taille approximative :</span>
                                <span class="text-muted" id="size-<?= $e($table) ?>">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Charge...
                                </span>
                            </div>
                            <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            onclick="afficherInfoTable('<?= $e($table) ?>')"
                                            data-bs-toggle="modal" data-bs-target="#table-info-modal">
                                        <?= $icone('circle-info', 'me-1') ?>Voir les informations
                                    </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="confirmerClearTable('<?= $e($table) ?>')"
                                        data-bs-toggle="modal" data-bs-target="#clear-table-modal">
                                    <?= $icone('trash-can', 'me-1') ?>Vider la table
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal d'information sur la table -->
    <div class="modal modal-blur fade" id="table-info-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Informations sur la table</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body" id="table-info-content">
                    <div class="text-center py-4">
                        <span class="spinner-border spinner-border-lg" role="status"></span>
                        <p class="mt-2">Chargement des informations...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de vidange -->
    <div class="modal modal-blur fade" id="clear-table-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Confirmer la vidange</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir vider la table <strong id="table-to-clear"></strong> ?</p>
                    <p class="text-danger mb-0"><strong>Cette opération est irréversible !</strong></p>
                    <p class="text-muted small">Toutes les données seront supprimées définitivement.</p>

                    <div class="mt-3">
                        <label class="form-label">Tapez <code>SUPPRIMER</code> pour confirmer :</label>
                        <input type="text" class="form-control" id="confirmation-input" placeholder="SUPPRIMER">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirm-clear-btn" disabled>
                        <?= $icone('trash-can', 'me-1') ?> Vider la table
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-between">
        <span class="text-muted small">Seuls les administrateurs peuvent effectuer des opérations sur la base de données.</span>
        <button type="submit" class="btn btn-primary">
            <?= $icone('check', 'me-2') ?>Enregistrer les paramètres
        </button>
    </div>

    <script>
        // Charger les informations sur les tables au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            const tables = [
                <?php foreach (array_keys($tables) as $i => $table): ?>
                    '<?= $e($table) ?>'<?= $i < count(array_keys($tables)) - 1 ? ', ' : '' ?>
                <?php endforeach; ?>
            ];

            tables.forEach(table => {
                chargerInfosTable(table);
            });
        });

        /**
         * Interroge /admin/parametres/table-info (POST + jeton CSRF).
         * La réponse JSON est rendue même en cas de code d'erreur HTTP : elle
         * porte le message explicatif (table absente, jeton expiré…).
         */
        function requeteInfosTable(table) {
            const corps = new FormData();
            corps.append('table', table);
            corps.append('_token', window.FLOTTEO.token);

            return fetch(`${window.FLOTTEO.base}/admin/parametres/table-info`, {
                method: 'POST',
                body: corps,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(response => response.json().catch(() => ({
                success: false,
                message: `Réponse inattendue du serveur (HTTP ${response.status}).`,
            })));
        }

        function formaterTaille(octets) {
            if (octets < 1024) return `${octets} o`;
            if (octets < 1024 * 1024) return `${(octets / 1024).toFixed(1)} Ko`;
            return `${(octets / (1024 * 1024)).toFixed(2)} Mo`;
        }

        function chargerInfosTable(table) {
            const lignes = document.getElementById(`row-count-${table}`);
            const taille = document.getElementById(`size-${table}`);

            const indisponible = (message, absente) => {
                lignes.className = absente ? 'badge bg-yellow-lt' : 'badge bg-red-lt';
                lignes.textContent = absente ? 'Table absente' : 'Indisponible';
                lignes.title = message || '';
                taille.textContent = '—';
            };

            requeteInfosTable(table)
                .then(data => {
                    if (data.success) {
                        lignes.textContent = `${data.rowCount.toLocaleString('fr-FR')} ligne${data.rowCount > 1 ? 's' : ''}`;
                        taille.textContent = formaterTaille(data.sizeBytes);
                    } else {
                        indisponible(data.message, data.missing === true);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    indisponible(error.message, false);
                });
        }

        function afficherInfoTable(table) {
            // Afficher le contenu du modal pendant le chargement
            const contentElement = document.getElementById('table-info-content');
            contentElement.innerHTML = 
                '<div class="text-center py-4">' +
                '<span class="spinner-border spinner-border-lg" role="status"></span>' +
                '<p class="mt-2">Chargement des informations...</p>' +
                '</div>';

            requeteInfosTable(table)
                .then(data => {
                    if (data.success) {
                        let html = '<div class="table-responsive">';
                        html += '<h6>Structure de la table</h6>';
                        html += '<table class="table table-sm table-bordered">';
                        html += '<thead><tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr></thead>';
                        html += '<tbody>';
                        data.columns.forEach(column => {
                            html += '<tr>';
                            html += `<td>${column.Field}</td>`;
                            html += `<td>${column.Type}</td>`;
                            html += `<td>${column.Null === 'YES' ? 'Oui' : 'Non'}</td>`;
                            html += `<td>${column.Key}</td>`;
                            html += `<td>${column.Default !== null ? column.Default : ''}</td>`;
                            html += `<td>${column.Extra}</td>`;
                            html += '</tr>';
                        });
                        html += '</tbody></table>';

                        if (data.foreignKeys && data.foreignKeys.length > 0) {
                            html += '<h6 class="mt-3">Clés étrangères</h6>';
                            html += '<table class="table table-sm table-bordered">';
                            html += '<thead><tr><th>Table</th><th>Colonne</th><th>Table référencée</th><th>Colonne référencée</th></tr></thead>';
                            html += '<tbody>';
                            data.foreignKeys.forEach(fk => {
                                html += '<tr>';
                                html += `<td>${fk.TABLE_NAME}</td>`;
                                html += `<td>${fk.COLUMN_NAME}</td>`;
                                html += `<td>${fk.REFERENCED_TABLE_NAME}</td>`;
                                html += `<td>${fk.REFERENCED_COLUMN_NAME}</td>`;
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                        }

                        html += `</div>`;
                        contentElement.innerHTML = html;
                    } else {
                        contentElement.innerHTML = 
                            '<div class="alert alert-danger">' +
                            'Erreur lors du chargement des informations: ' + data.message +
                            '</div>';
                    }
                })
                .catch(error => {
                    contentElement.innerHTML = 
                        '<div class="alert alert-danger">' +
                        'Erreur: ' + error.message +
                        '</div>';
                });
        }

        let tableToClear = null;

        function confirmerClearTable(table) {
            tableToClear = table;
            document.getElementById('table-to-clear').textContent = table;
            document.getElementById('confirmation-input').value = '';
            document.getElementById('confirm-clear-btn').disabled = true;
        }

        document.getElementById('confirmation-input').addEventListener('input', function() {
            const btn = document.getElementById('confirm-clear-btn');
            btn.disabled = this.value !== 'SUPPRIMER';
        });

        document.getElementById('confirm-clear-btn').addEventListener('click', function() {
            if (tableToClear && document.getElementById('confirmation-input').value === 'SUPPRIMER') {
                const form = document.createElement('form');
                form.method = 'post';
                form.action = `${window.FLOTTEO.base}/admin/parametres/clear-table`;

                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = window.FLOTTEO.token;
                form.appendChild(tokenInput);

                const tableInput = document.createElement('input');
                tableInput.type = 'hidden';
                tableInput.name = 'table';
                tableInput.value = tableToClear;
                form.appendChild(tableInput);

                const confirmationInput = document.createElement('input');
                confirmationInput.type = 'hidden';
                confirmationInput.name = 'confirmation';
                confirmationInput.value = 'SUPPRIMER';
                form.appendChild(confirmationInput);

                document.body.appendChild(form);
                form.submit();
            }
        });
    </script>
