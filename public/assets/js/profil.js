/**
 * Flotteo — Page de profil : aperçu de l'avatar choisi avant enregistrement.
 *
 * Le dépôt et la validation relèvent du serveur ; ce script se limite à
 * montrer l'image et à annoncer immédiatement un format refusé, qu'il refuse
 * de toute façon.
 */
(function () {
    'use strict';

    var TYPES_ACCEPTES = ['image/jpeg', 'image/png', 'image/webp'];

    function initialiser() {
        var apercu = document.getElementById('p_avatar_apercu');
        var fichier = document.getElementById('p_avatar_fichier');
        if (!apercu || !fichier) { return; }

        var aide = document.getElementById('p_avatar_aide');
        var blocSupprimer = document.getElementById('p_avatar_supprimer_bloc');
        var caseSupprimer = document.getElementById('p_avatar_supprimer');
        var aideInitiale = aide ? aide.textContent : '';

        /** Image enregistrée, lue sur l'élément rendu par la vue. */
        function apercuEnregistre() {
            return apercu.getAttribute('data-avatar-url') || '';
        }

        /** Rétablit l'aperçu sur l'image fournie, ou sur les initiales. */
        function afficherApercu(image) {
            apercu.style.backgroundImage = image ? "url('" + image + "')" : '';
            apercu.classList.toggle('bg-cover', Boolean(image));
            apercu.textContent = image ? '' : (apercu.getAttribute('data-initiales') || '?');
        }

        fichier.addEventListener('change', function () {
            var choisi = (fichier.files && fichier.files[0]) || null;

            if (!choisi) {
                // Annulation de la boîte de dialogue : l'aperçu revient à l'état
                // précédent au lieu de rester vide.
                afficherApercu(apercuEnregistre());
                if (aide) { aide.textContent = aideInitiale; }
                return;
            }

            // Contrôle de confort : le serveur refuse de toute façon le fichier,
            // mais l'utilisateur voit immédiatement qu'il s'est trompé.
            if (TYPES_ACCEPTES.indexOf(choisi.type) === -1) {
                fichier.value = '';
                afficherApercu(apercuEnregistre());
                if (aide) {
                    aide.textContent = 'Format refusé : choisissez un fichier JPG, PNG ou WEBP.';
                }
                return;
            }

            var lecteur = new FileReader();
            lecteur.onload = function (evenement) {
                afficherApercu(evenement.target.result);
                // Un nouveau fichier l'emporte sur la suppression : l'option
                // disparaît, pour que la demande ne soit jamais contradictoire.
                if (blocSupprimer) { blocSupprimer.hidden = true; }
                if (caseSupprimer) { caseSupprimer.checked = false; }
                if (aide) { aide.textContent = choisi.name; }
            };
            lecteur.readAsDataURL(choisi);
        });

        /*
         * Décocher « supprimer » ne remet pas l'image en place : il faut
         * d'abord décocher la case. Sans ce retour en arrière, décocher la
         * case laisserait un aperçu vide alors que l'image enregistrée est
         * toujours en base — le formulaire contredirait alors l'écran.
         */
        if (caseSupprimer) {
            caseSupprimer.addEventListener('change', function () {
                if (!caseSupprimer.checked) {
                    afficherApercu(apercuEnregistre());
                    if (aide) { aide.textContent = aideInitiale; }
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initialiser);
})();