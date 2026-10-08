<?php
    declare(strict_types=1);

    /**
     * Modale de saisie d'un véhicule (création et édition).
     *
     * @var ?array $vehicule
     * @var array $nomenclature, $statuts
     * @var string $base_url
     */

    use Core\Csrf;

    $e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $v = static fn (string $cle, mixed $defaut = ''): string => htmlspecialchars((string) ($vehicule[$cle] ?? $defaut), ENT_QUOTES, 'UTF-8');
    $id = (int) ($vehicule['id'] ?? 0);

    // Tri alphabétique des listes déroulantes par libellé
    $modeles = $nomenclature['modeles'] ?? [];
    usort($modeles, static function (array $a, array $b): int {
        $libA = ($a['marque_libelle'] ?? '') !== '' ? $a['marque_libelle'] . ' ' . $a['nom'] : ($a['nom'] ?? '');
        $libB = ($b['marque_libelle'] ?? '') !== '' ? $b['marque_libelle'] . ' ' . $b['nom'] : ($b['nom'] ?? '');
        return strnatcasecmp($libA, $libB);
    });

    $entites = $nomenclature['entites'] ?? [];
    usort($entites, static function (array $a, array $b): int {
        return strnatcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? ''));
    });

    $loueurs = $nomenclature['loueurs'] ?? [];
    usort($loueurs, static function (array $a, array $b): int {
        return strnatcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? ''));
    });

    $lieux = $nomenclature['lieux'] ?? [];
    usort($lieux, static function (array $a, array $b): int {
        $libA = ((string) ($a['nom'] ?? '')) . (!empty($a['ville']) ? ' (' . (string) $a['ville'] . ')' : '');
        $libB = ((string) ($b['nom'] ?? '')) . (!empty($b['ville']) ? ' (' . (string) $b['ville'] . ')' : '');
        return strnatcasecmp($libA, $libB);
    });
?>
<div class="modal modal-blur fade" id="modal-vehicule" tabindex="-1" role="dialog" aria-hidden="true" aria-labelledby="titre-vehicule">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <form class="modal-content" method="post" action="<?= $e($base_url . '/vehicules/enregistrer') ?>"
              data-valide-vehicule="0" id="formulaire-vehicule">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="modal-header">
                <h5 class="modal-title" id="titre-vehicule"><?= $id > 0 ? 'Modifier le véhicule' : 'Ajouter un véhicule' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <!-- Colonne 1 : Immatriculation -->
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="v_immat">Immatriculation</label>
                        <input type="text" class="form-control" id="v_immat" name="immatriculation" required
                               maxlength="20" value="<?= $v('immatriculation') ?>" placeholder="AB-123-CD">
                    </div>

                    <!-- Colonne 2 : Marque & modèle -->
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="v_modele">Marque &amp; modèle</label>
                        <select class="form-select" id="v_modele" name="modele_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($modeles as $m): ?>
                                <option value="<?= (int) $m['id'] ?>" <?= (int) ($vehicule['modele_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                                    <?= $e(($m['marque_libelle'] ?? '') !== '' ? $m['marque_libelle'] . ' ' . $m['nom'] : $m['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ligne complète : Couleur et palette (aucun champ à côté) -->
                    <div class="col-12">
                        <label class="form-label" for="v_couleur">Couleur</label>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <input type="text" class="form-control" id="v_couleur" name="couleur" style="max-width: 180px;"
                                   maxlength="50" value="<?= $v('couleur') ?>" placeholder="ex. Blanc Glacier...">
                            <div class="d-flex align-items-center gap-1 flex-wrap py-1">
                                <span class="text-secondary small me-1">Nuances de base :</span>
                                <?php
                                $paletteCouleurs = [
                                    ['nom' => 'Blanc',  'code' => '#ffffff', 'border' => true],
                                    ['nom' => 'Noir',   'code' => '#1f2937', 'border' => false],
                                    ['nom' => 'Gris',   'code' => '#9ca3af', 'border' => false],
                                    ['nom' => 'Argent', 'code' => '#d1d5db', 'border' => true],
                                    ['nom' => 'Bleu',   'code' => '#2563eb', 'border' => false],
                                    ['nom' => 'Rouge',  'code' => '#dc2626', 'border' => false],
                                    ['nom' => 'Vert',   'code' => '#16a34a', 'border' => false],
                                    ['nom' => 'Jaune',  'code' => '#eab308', 'border' => false],
                                    ['nom' => 'Marron', 'code' => '#78350f', 'border' => false],
                                    ['nom' => 'Orange', 'code' => '#ea580c', 'border' => false],
                                ];
                                foreach ($paletteCouleurs as $c):
                                ?>
                                    <button type="button" class="btn btn-sm p-0 rounded-circle border shadow-xs pastille-couleur-item flex-shrink-0"
                                            style="width: 24px; height: 24px; background-color: <?= $c['code'] ?>; <?= $c['border'] ? 'border-color: #cbd5e1 !important;' : 'border-color: rgba(0,0,0,0.15) !important;' ?>"
                                            title="<?= $e($c['nom']) ?>"
                                            data-nom-couleur="<?= $e($c['nom']) ?>"
                                            onclick="choisirCouleur('<?= $e($c['nom']) ?>')">
                                        <span class="visually-hidden"><?= $e($c['nom']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne 1 : Motorisation -->
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="v_motorisation">Motorisation</label>
                        <select class="form-select" id="v_motorisation" name="motorisation">
                            <option value="">— Non renseignée —</option>
                            <?php foreach (\Models\Vehicle::MOTORISATIONS as $cle => $libelle): ?>
                                <option value="<?= $e($cle) ?>" <?= ($vehicule['motorisation'] ?? '') === $cle ? 'selected' : '' ?>>
                                    <?= $e($libelle) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Colonne 1 : Type de boîte -->
                    <div class="col-12 col-md-6">
                        <label class="form-label">Type de boîte</label>
                        <div class="form-selectgroup">
                            <label class="form-selectgroup-item">
                                <input type="radio" name="type_boite" value="mecanique" class="form-selectgroup-input"
                                       <?= ($vehicule['type_boite'] ?? 'mecanique') === 'mecanique' ? 'checked' : '' ?>>
                                <span class="form-selectgroup-label">
                                    <svg class="icon me-1" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 10h16M12 4v16M8 4h8M8 20h8"/>
                                    </svg>
                                    Mécanique
                                </span>
                            </label>
                            <label class="form-selectgroup-item">
                                <input type="radio" name="type_boite" value="automatique" class="form-selectgroup-input"
                                       <?= ($vehicule['type_boite'] ?? '') === 'automatique' ? 'checked' : '' ?>>
                                <span class="form-selectgroup-label">
                                    <svg class="icon me-1" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="M10 8h4a2 2 0 1 1 0 4h-4zM10 12h4a2 2 0 1 1 0 4h-4z"/>
                                    </svg>
                                    Automatique
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Colonne 2 : Statut d'exploitation -->
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="v_statut">Statut d'exploitation</label>
                        <select class="form-select" id="v_statut" name="statut">
                            <?php foreach ($statuts as $cle => $libelle): ?>
                                <option value="<?= $e($cle) ?>" <?= ($vehicule['statut'] ?? 'actif') === $cle ? 'selected' : '' ?>>
                                    <?= $e($libelle) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Colonne 1 : Entité propriétaire -->
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="v_entite">Entité propriétaire</label>
                        <select class="form-select" id="v_entite" name="entite_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($entites as $x): ?>
                                <option value="<?= (int) $x['id'] ?>" <?= (int) ($vehicule['entite_id'] ?? 0) === (int) $x['id'] ? 'selected' : '' ?>>
                                    <?= $e($x['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Colonne 2 : Organisme loueur -->
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="v_loueur">Organisme loueur</label>
                        <select class="form-select" id="v_loueur" name="loueur_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($loueurs as $x): ?>
                                <option value="<?= (int) $x['id'] ?>" <?= (int) ($vehicule['loueur_id'] ?? 0) === (int) $x['id'] ? 'selected' : '' ?>>
                                    <?= $e($x['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Colonne 1 : Lieu d'exploitation -->
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="v_lieu">Lieu d'exploitation</label>
                        <select class="form-select" id="v_lieu" name="lieu_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($lieux as $x): ?>
                                <option value="<?= (int) $x['id'] ?>" <?= (int) ($vehicule['lieu_id'] ?? 0) === (int) $x['id'] ? 'selected' : '' ?>>
                                    <?= $e($x['nom'] . ($x['ville'] !== null ? ' (' . $x['ville'] . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Colonne 2 : Date d'entrée en flotte -->
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="v_entree">Date d'entrée en flotte</label>
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true">
                                    <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" />
                                    <path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" />
                                </svg>
                            </span>
                            <input type="text" class="form-control" id="v_entree" name="date_entree" required
                                   value="<?= $v('date_entree') ?>" placeholder="YYYY-MM-DD" autocomplete="off" data-bs-toggle="datepicker">
                        </div>
                    </div>

                    <!-- Colonne 1 : Durée du contrat -->
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="v_duree_contrat">Durée du contrat (mois)</label>
                        <input type="number" class="form-control" id="v_duree_contrat" name="duree_contrat" min="0" max="240"
                               value="<?= $v('duree_contrat') ?>"
                               onchange="calculerDateSortiePrevue()">
                    </div>

                    <!-- Colonne 2 : Date de sortie prévisionnelle -->
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="v_sortie_prevue">Date de sortie prévisionnelle</label>
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true">
                                    <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" />
                                    <path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" />
                                </svg>
                            </span>
                            <input type="text" class="form-control" id="v_sortie_prevue" name="date_sortie_prevue" required
                                   value="<?= $v('date_sortie_prevue') ?>" placeholder="YYYY-MM-DD" autocomplete="off" data-bs-toggle="datepicker">
                        </div>
                        <div class="form-text">Calculée automatiquement à partir de la durée du contrat et de la date d'entrée.</div>
                    </div>

                    <!-- Colonne 1 : Kilométrage maximum -->
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="v_km_maxi">Kilométrage maximum</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="v_km_maxi" name="km_maxi"
                                   min="0" max="999999" step="1" inputmode="numeric"
                                   value="<?= $v('km_maxi') ?>">
                            <span class="input-group-text">km</span>
                        </div>
                    </div>

                    <!-- Colonne 2 : Kilométrage de référence -->
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="v_kilometrage">Kilométrage de référence</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="v_kilometrage" name="kilometrage"
                                   min="0" max="9999999" step="1" inputmode="numeric"
                                   value="<?= $v('kilometrage', '0') ?>">
                            <span class="input-group-text">km</span>
                        </div>
                    </div>

                    <!-- Colonne 1 : Date de sortie Angelus -->
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="v_sortie_angelus">Date de sortie Angelus</label>
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true">
                                    <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" />
                                    <path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" />
                                </svg>
                            </span>
                            <input type="text" class="form-control" id="v_sortie_angelus" name="sortie_angelus"
                                   value="<?= $v('sortie_angelus') ?>" placeholder="YYYY-MM-DD" autocomplete="off" data-bs-toggle="datepicker">
                        </div>
                    </div>

                    <!-- Colonne 2 : Équipé d'un hayon -->
                    <div class="col-12 col-md-6">
                        <label class="form-label">Équipement hayon</label>
                        <div class="pt-2">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="hayon" value="1" id="v_hayon"
                                       <?= (int) ($vehicule['hayon'] ?? 0) === 1 ? 'checked' : '' ?>
                                       onchange="toggleTempsControleHayon()">
                                <span class="form-check-label">Véhicule équipé d'un hayon</span>
                            </label>
                        </div>
                    </div>

                    <!-- Champs hayon (visibles si hayon coché) -->
                    <div class="col-12 col-md-6" id="bloc_temps_controle_hayon">
                        <label class="form-label" for="v_temps_controle_hayon">Temps contrôle hayon</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="v_temps_controle_hayon" name="temps_controle_hayon"
                                   min="0" max="120" step="1" inputmode="numeric" placeholder="6"
                                   value="<?= $v('temps_controle_hayon') ?>">
                            <span class="input-group-text">mois</span>
                        </div>
                        <div class="form-text">Périodicité VGP (ex. 6 mois).</div>
                    </div>

                    <div class="col-12 col-md-6" id="bloc_date_dernier_controle_hayon">
                        <label class="form-label" for="v_date_dernier_controle_hayon">Date dernier contrôle hayon</label>
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true">
                                    <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" />
                                    <path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" />
                                </svg>
                            </span>
                            <input type="text" class="form-control" id="v_date_dernier_controle_hayon" name="date_dernier_controle_hayon"
                                   value="<?= $v('date_dernier_controle_hayon') ?>" placeholder="YYYY-MM-DD" autocomplete="off" data-bs-toggle="datepicker">
                        </div>
                        <div class="form-text">Dernière VGP effectuée.</div>
                    </div>

                    <div class="col-12 col-md-6" id="bloc_date_prochain_controle_hayon">
                        <label class="form-label" for="v_date_prochain_controle_hayon">Prochain contrôle hayon</label>
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true">
                                    <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" />
                                    <path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" />
                                </svg>
                            </span>
                            <input type="text" class="form-control" id="v_date_prochain_controle_hayon" name="date_prochain_controle_hayon"
                                   value="<?= $v('date_prochain_controle_hayon') ?>" placeholder="YYYY-MM-DD" autocomplete="off" data-bs-toggle="datepicker">
                        </div>
                        <div class="form-text">Calculé auto si dernier contrôle saisi.</div>
                    </div>

                    <!-- Commentaire : sur toute la largeur (col-12) en bas -->
                    <div class="col-12">
                        <label class="form-label" for="v_commentaire">Commentaire</label>
                        <textarea class="form-control" id="v_commentaire" name="commentaire" rows="3"
                                   maxlength="2000"><?= $v('commentaire') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" id="valider-vehicule">
                    <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12l5 5l10 -10"/>
                    </svg>
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function choisirCouleur(nom) {
    const inputCouleur = document.getElementById('v_couleur');
    if (inputCouleur) {
        inputCouleur.value = nom;
        inputCouleur.focus();
        inputCouleur.dispatchEvent(new Event('input', { bubbles: true }));
        inputCouleur.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function calculerDateSortiePrevue() {
    const dureeContrat = document.getElementById('v_duree_contrat');
    const dateEntree = document.getElementById('v_entree');
    const dateSortiePrevue = document.getElementById('v_sortie_prevue');
    
    if (!dureeContrat || !dateEntree || !dateSortiePrevue) return;
    
    const duree = parseInt(dureeContrat.value, 10);
    const dateEntreeVal = dateEntree.value; // Format: YYYY-MM-DD
    
    if (!isNaN(duree) && duree > 0 && /^\d{4}-\d{2}-\d{2}$/.test(dateEntreeVal)) {
        const [anneeStr, moisStr, jourStr] = dateEntreeVal.split('-');
        let annee = parseInt(anneeStr, 10);
        let mois = parseInt(moisStr, 10); // 1 - 12
        let jour = parseInt(jourStr, 10);

        // Ajout des mois
        const totalMois = mois + duree;
        annee += Math.floor((totalMois - 1) / 12);
        mois = ((totalMois - 1) % 12) + 1;

        // Nombre de jours dans le mois cible (gestion bissextile et fins de mois)
        const maxJours = new Date(annee, mois, 0).getDate();
        if (jour > maxJours) {
            jour = maxJours;
        }

        const moisFormate = String(mois).padStart(2, '0');
        const jourFormate = String(jour).padStart(2, '0');
        dateSortiePrevue.value = `${annee}-${moisFormate}-${jourFormate}`;
    }
}

function calculerDateProchainControleHayon() {
    const tempsControle = document.getElementById('v_temps_controle_hayon');
    const dateDernier = document.getElementById('v_date_dernier_controle_hayon');
    const dateProchain = document.getElementById('v_date_prochain_controle_hayon');

    if (!tempsControle || !dateDernier || !dateProchain) return;

    const moisAjout = parseInt(tempsControle.value, 10);
    const dateDernierVal = dateDernier.value; // Format: YYYY-MM-DD

    if (!isNaN(moisAjout) && moisAjout > 0 && /^\d{4}-\d{2}-\d{2}$/.test(dateDernierVal)) {
        const [anneeStr, moisStr, jourStr] = dateDernierVal.split('-');
        let annee = parseInt(anneeStr, 10);
        let mois = parseInt(moisStr, 10);
        let jour = parseInt(jourStr, 10);

        const totalMois = mois + moisAjout;
        annee += Math.floor((totalMois - 1) / 12);
        mois = ((totalMois - 1) % 12) + 1;

        const maxJours = new Date(annee, mois, 0).getDate();
        if (jour > maxJours) {
            jour = maxJours;
        }

        const moisFormate = String(mois).padStart(2, '0');
        const jourFormate = String(jour).padStart(2, '0');
        dateProchain.value = `${annee}-${moisFormate}-${jourFormate}`;
    }
}

function toggleTempsControleHayon(preRemplirDefaut = true) {
    const hayonCheckbox = document.getElementById('v_hayon');
    const blocTemps = document.getElementById('bloc_temps_controle_hayon');
    const inputTemps = document.getElementById('v_temps_controle_hayon');
    const blocDate = document.getElementById('bloc_date_dernier_controle_hayon');
    const inputDate = document.getElementById('v_date_dernier_controle_hayon');
    const blocProchain = document.getElementById('bloc_date_prochain_controle_hayon');
    const inputProchain = document.getElementById('v_date_prochain_controle_hayon');
    if (!hayonCheckbox) return;

    if (hayonCheckbox.checked) {
        if (blocTemps) blocTemps.style.display = '';
        if (blocDate) blocDate.style.display = '';
        if (blocProchain) blocProchain.style.display = '';
        if (preRemplirDefaut && inputTemps && !inputTemps.value) {
            inputTemps.value = '6';
        }
        if (inputDate && inputDate.value && (!inputProchain || !inputProchain.value)) {
            calculerDateProchainControleHayon();
        }
    } else {
        if (blocTemps) blocTemps.style.display = 'none';
        if (blocDate) blocDate.style.display = 'none';
        if (blocProchain) blocProchain.style.display = 'none';
        if (inputTemps) inputTemps.value = '';
        if (inputDate) inputDate.value = '';
        if (inputProchain) inputProchain.value = '';
    }
}

// Initialiser les écouteurs lorsque le DOM est prêt
document.addEventListener('DOMContentLoaded', function() {
    const dureeContrat = document.getElementById('v_duree_contrat');
    const dateEntree = document.getElementById('v_entree');
    const dateSortiePrevue = document.getElementById('v_sortie_prevue');
    const hayonCheckbox = document.getElementById('v_hayon');
    const modalVehicule = document.getElementById('modal-vehicule');
    const tempsControle = document.getElementById('v_temps_controle_hayon');
    const dateDernier = document.getElementById('v_date_dernier_controle_hayon');
    const dateProchain = document.getElementById('v_date_prochain_controle_hayon');

    toggleTempsControleHayon(false);

    if (hayonCheckbox) {
        hayonCheckbox.addEventListener('change', function() {
            toggleTempsControleHayon(true);
        });
    }

    if (modalVehicule) {
        modalVehicule.addEventListener('shown.bs.modal', function() {
            toggleTempsControleHayon(false);
        });
    }

    if (tempsControle) {
        tempsControle.addEventListener('input', calculerDateProchainControleHayon);
        tempsControle.addEventListener('change', calculerDateProchainControleHayon);
    }

    if (dateDernier) {
        dateDernier.addEventListener('input', calculerDateProchainControleHayon);
        dateDernier.addEventListener('change', calculerDateProchainControleHayon);
        dateDernier.addEventListener('change.bs.datepicker', calculerDateProchainControleHayon);
    }
    
    // Si le prochain contrôle est vide mais le dernier contrôle est renseigné, on le calcule
    if (dateProchain && !dateProchain.value) {
        calculerDateProchainControleHayon();
    }
    
    // Si la date de sortie prévisionnelle est vide mais que les autres champs sont saisis, on la calcule
    if (dateSortiePrevue && !dateSortiePrevue.value) {
        calculerDateSortiePrevue();
    }
    
    if (dureeContrat) {
        dureeContrat.addEventListener('input', calculerDateSortiePrevue);
        dureeContrat.addEventListener('change', calculerDateSortiePrevue);
    }
    
    if (dateEntree) {
        dateEntree.addEventListener('input', calculerDateSortiePrevue);
        dateEntree.addEventListener('change', calculerDateSortiePrevue);
        dateEntree.addEventListener('change.bs.datepicker', calculerDateSortiePrevue);
    }
});
</script>
</div>
