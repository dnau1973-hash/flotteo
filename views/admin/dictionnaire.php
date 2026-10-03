<?php
declare(strict_types=1);

/**
 * Administration : table de paramétrage générique.
 *
 * @var string $type
 * @var array $def, $types, $entrees, $sources
 * @var string $base_url
 */

use Core\Csrf;
use Core\Icon;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$formater = static function (string $champ, mixed $valeur) use ($def, $e): string {
    if ($champ === 'categorie') {
        return '<span class="badge bg-blue-lt">' . $e(ucfirst((string) $valeur)) . '</span>';
    }
    return $e((string) $valeur);
};
?>

<div class="card mb-3">
    <div class="card-body">
        <ul class="nav nav-underline">
            <?php foreach ($types as $cle => $desc): ?>
                <li class="nav-item<?= $cle === $type ? ' active' : '' ?>">
                    <a class="nav-link<?= $cle === $type ? ' active' : '' ?>"
                       <?= $cle === $type ? 'aria-current="page"' : '' ?>
                       href="<?= $e($base_url . '/admin/dictionnaires/' . $cle) ?>"><?= $e($desc['libelle']) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $e($def['libelle']) ?></h3>
        <div class="card-actions">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-dictionnaire">
                <?= Icon::solid('plus', 'me-2') ?>Ajouter
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <?php if ($entrees === []): ?>
            <div class="empty-state">Cette table de paramétrage est vide.</div>
        <?php else: ?>
            <table class="table table-vcenter card-table table-hover">
                <thead>
                <tr>
                    <th class="w-1">#</th>
                    <?php foreach ($def['champs'] as $champ): ?>
                        <th><?= $e($def['etiquette'][$champ] ?? $champ) ?></th>
                    <?php endforeach; ?>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entrees as $entree): ?>
                    <tr>
                        <td class="text-secondary"><?= (int) $entree['id'] ?></td>
                        <?php foreach ($def['champs'] as $champ): ?>
                            <?php
                            $valeur = $entree[$champ] ?? '';
                            $libelleSource = $champ === 'marque_id' ? ($entree['marque_libelle'] ?? null) : null;
                            ?>
                            <td><?= $libelleSource !== null ? $e($libelleSource) : $formater($champ, $valeur) ?></td>
                        <?php endforeach; ?>
                        <td class="cell-actions text-end">
                            <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#modal-dictionnaire"
                                    data-edition-dictionnaire
                                    data-id="<?= (int) $entree['id'] ?>"
                                    <?php foreach ($def['champs'] as $champ): ?>
                                        data-<?= $e($champ) ?>="<?= $e((string) ($entree[$champ] ?? '')) ?>"
                                    <?php endforeach; ?>
                                    title="Modifier"
                                    aria-label="Modifier l'entrée n° <?= (int) $entree['id'] ?>">
                                <?= Icon::solid('pen') ?>
                            </button>
                            <form method="post" action="<?= $e($base_url . '/admin/dictionnaires/' . $type . '/supprimer') ?>"
                                  class="d-inline" data-confirmer="Supprimer cette entrée de la table « <?= $e($def['libelle']) ?> » ?">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $entree['id'] ?>">
                                <button type="submit" class="btn btn-sm text-danger" title="Supprimer"
                                        aria-label="Supprimer l'entrée n° <?= (int) $entree['id'] ?>">
                                    <?= Icon::solid('trash-can') ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="modal modal-blur fade" id="modal-dictionnaire" tabindex="-1" role="dialog" aria-hidden="true"
     aria-labelledby="titre-dictionnaire">
    <div class="modal-dialog" role="document">
        <form class="modal-content" method="post" action="<?= $e($base_url . '/admin/dictionnaires/' . $type . '/enregistrer') ?>"
              id="formulaire-dictionnaire">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="0" id="dictionnaire-id">

            <div class="modal-header">
                <h5 class="modal-title" id="titre-dictionnaire">Ajouter — <?= $e($def['libelle']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <div class="modal-body">
                <?php foreach ($def['champs'] as $champ): ?>
                    <div class="mb-3">
                        <label class="form-label<?= in_array($champ, $def['requis'], true) ? ' required' : '' ?>"
                               for="d_<?= $e($champ) ?>"><?= $e($def['etiquette'][$champ] ?? $champ) ?></label>

                        <?php if ($champ === 'marque_id'): ?>
                            <select class="form-select" id="d_<?= $e($champ) ?>" name="<?= $e($champ) ?>" required>
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($sources['marques'] as $marque): ?>
                                    <option value="<?= (int) $marque['id'] ?>"><?= $e($marque['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($champ === 'categorie'): ?>
                            <select class="form-select" id="d_categorie" name="categorie">
                                <?php foreach ($def['categories'] as $c): ?>
                                    <option value="<?= $e($c) ?>"><?= $e(ucfirst($c)) ?></option>
                                <?php endforeach; ?>
                            </select>

                        <?php else: ?>
                            <input type="<?= $champ === 'contact_email' ? 'email' : 'text' ?>" class="form-control"
                                   id="d_<?= $e($champ) ?>" name="<?= $e($champ) ?>" maxlength="190"
                                   <?= in_array($champ, $def['requis'], true) ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modale = document.getElementById('modal-dictionnaire');
        if (!modale) { return; }
        var formulaire = document.getElementById('formulaire-dictionnaire');
        <?php
        // HEX_TAG neutralise une éventuelle « </script> » dans un libellé ; par
        // défaut json_encode se contente d'échapper le « / », ce qui suffit mais
        // repose sur un comportement implicite.
        $optionsJson = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
        ?>
        var titreCreation = <?= json_encode('Ajouter — ' . $def['libelle'], $optionsJson) ?>;
        var titreEdition = <?= json_encode('Modifier — ' . $def['libelle'], $optionsJson) ?>;

        modale.addEventListener('show.bs.modal', function (evenement) {
            var declencheur = evenement.relatedTarget;
            formulaire.reset();

            // Le formulaire est réarmé par reset(), puis les valeurs de la ligne
            // cliquée sont recopiées. Le marqueur `data-edition-dictionnaire`
            // distingue le bouton « Éditer » du bouton « Ajouter » : les deux
            // déclenchent la même modale, seul le premier préremplit les champs.
            var edition = declencheur
                && declencheur.hasAttribute
                && declencheur.hasAttribute('data-edition-dictionnaire');

            // Un affichage programmatique n'a pas de déclencheur : on reste sur l'ajout.
            if (!edition) {
                document.getElementById('dictionnaire-id').value = '0';
                document.getElementById('titre-dictionnaire').textContent = titreCreation;
                return;
            }

            document.getElementById('dictionnaire-id').value = declencheur.getAttribute('data-id');
            document.getElementById('titre-dictionnaire').textContent = titreEdition;
            <?php foreach ($def['champs'] as $champ): ?>
                var champ_<?= $e($champ) ?> = document.getElementById('d_<?= $e($champ) ?>');
                if (champ_<?= $e($champ) ?>) { champ_<?= $e($champ) ?>.value = declencheur.getAttribute('data-<?= $e($champ) ?>'); }
            <?php endforeach; ?>
        });
    });
</script>
