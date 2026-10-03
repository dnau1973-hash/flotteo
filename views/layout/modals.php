<?php
declare(strict_types=1);

/**
 * Modales globales : confirmation de suppression (aucune alerte native).
 *
 * Icônes Font Awesome via Core\Icon, comme la barre supérieure.
 */
?>
<div class="modal modal-blur fade" id="modal-confirmation" tabindex="-1" role="dialog" aria-hidden="true"
     aria-labelledby="titre-confirmation">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <span class="badge bg-red-lt badge-lg d-inline-flex align-items-center justify-content-center"
                          style="width:3rem;height:3rem;border-radius:50%">
                        <?= \Core\Icon::solid('triangle-exclamation', 'fs-1') ?>
                    </span>
                </div>
                <h3 id="titre-confirmation" class="mb-2">Confirmer la suppression</h3>
                <p class="text-secondary mb-0" id="texte-confirmation">Cette action est définitive.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="bouton-confirmation" data-confirmee="0">
                    <?= \Core\Icon::solid('trash-can') ?>
                    Supprimer
                </button>
            </div>
        </div>
    </div>
</div>
