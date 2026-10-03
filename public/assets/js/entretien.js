/**
 * Flotteo — Comportement de la modale d'entretien (pré-remplissage en édition).
 */
(function () {
    'use strict';

    function initialiser() {
        var modaleElement = document.getElementById('modal-entretien');
        if (!modaleElement) { return; }

        var formulaire = document.getElementById('formulaire-entretien');
        var titre = document.getElementById('titre-entretien');
        var champId = document.getElementById('entretien-id');

        // Réinitialisation du formulaire à chaque ouverture en mode création.
        modaleElement.addEventListener('show.bs.modal', function (evenement) {
            if (evenement.relatedTarget && evenement.relatedTarget.hasAttribute('data-bs-target')) {
                formulaire.reset();
                champId.value = '0';
                titre.textContent = 'Nouvelle prestation d\'entretien';
                document.getElementById('e_ht').value = '0';
                document.getElementById('e_ttc').value = '0';
            }
        });

        // Pré-remplissage depuis les attributs data-* du bouton d'édition.
        document.querySelectorAll('[data-edition-entretien]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                champId.value = bouton.getAttribute('data-id');
                titre.textContent = 'Modifier la prestation';
                document.getElementById('e_vehicule').value = bouton.getAttribute('data-vehicule');
                document.getElementById('e_type').value = bouton.getAttribute('data-type');
                document.getElementById('e_date').value = bouton.getAttribute('data-date');
                document.getElementById('e_km').value = bouton.getAttribute('data-km');
                document.getElementById('e_ht').value = bouton.getAttribute('data-ht');
                document.getElementById('e_ttc').value = bouton.getAttribute('data-ttc');
                document.getElementById('e_commentaire').value = bouton.getAttribute('data-commentaire');
            });
        });

        // Le TTC est pré-calculé à partir du HT (TVA 20 %) s'il reste vide.
        var ht = document.getElementById('e_ht');
        var ttc = document.getElementById('e_ttc');
        ht.addEventListener('input', function () {
            var valeurHT = parseFloat(ht.value) || 0;
            if (parseFloat(ttc.value) === 0 || parseFloat(ttc.value) < valeurHT) {
                ttc.value = (valeurHT * 1.2).toFixed(2);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initialiser);
})();
