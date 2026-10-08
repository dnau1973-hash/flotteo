/**
 * Flotteo — Modale d'entretien : ouverture en création ou pré-remplissage en édition.
 */
(function () {
    'use strict';

    function initialiser() {
        var modaleElement = document.getElementById('modal-entretien');
        if (!modaleElement) { return; }

        var formulaire = document.getElementById('formulaire-entretien');
        var titre = document.getElementById('titre-entretien');
        var champId = document.getElementById('entretien-id');
        var ht = document.getElementById('e_ht');
        var ttc = document.getElementById('e_ttc');

        /*
         * Le pré-remplissage se fait sur `show.bs.modal`, jamais sur `click`.
         *
         * Bootstrapdelegue les déclencheurs de modale sur `document` : la modale
         * s'ouvre donc *après* l'exécution d'un écouteur posé directement sur le
         * bouton. Le pré-remplissage placé sur `click` était donc effacé un
         * instant plus tard par la réinitialisation ci-dessous, et « Modifier »
         * envoyait une création vide — le bouton semblait inerte, sans la moindre
         * erreur pour l'expliquer. Traiter les deux cas au même endroit supprime
         * la course plutôt que de compter sur l'ordre des écouteurs.
         */
        modaleElement.addEventListener('show.bs.modal', function (evenement) {
            var declencheur = evenement.relatedTarget;
            var edition = declencheur && declencheur.hasAttribute('data-edition-entretien');

            if (!edition) {
                formulaire.reset();
                champId.value = '0';
                titre.textContent = 'Nouvelle prestation d\'entretien';
                ht.value = '0';
                ttc.value = '0';
                return;
            }

            champId.value = declencheur.getAttribute('data-id');
            titre.textContent = 'Modifier la prestation';
            document.getElementById('e_vehicule').value = declencheur.getAttribute('data-vehicule');
            document.getElementById('e_type').value = declencheur.getAttribute('data-type');
            document.getElementById('e_date').value = declencheur.getAttribute('data-date');
            document.getElementById('e_km').value = declencheur.getAttribute('data-km');
            ht.value = declencheur.getAttribute('data-ht');
            ttc.value = declencheur.getAttribute('data-ttc');
            document.getElementById('e_commentaire').value = declencheur.getAttribute('data-commentaire');
        });

        // Le TTC est pré-calculé à partir du HT (TVA 20 %) s'il reste vide.
        ht.addEventListener('input', function () {
            var valeurHT = parseFloat(ht.value) || 0;
            if (parseFloat(ttc.value) === 0 || parseFloat(ttc.value) < valeurHT) {
                ttc.value = (valeurHT * 1.2).toFixed(2);
            }
        });
    }

    /*
     * Visionneuse de document.
     *
     * Le clic sur une pièce jointe est repris pour ouvrir une modale plutôt
     * qu'un nouvel onglet : consulter une facture ne doit pas remplacer la
     * fiche, et le retour doit être immédiat. Le lien reste une URL réelle —
     * sans JavaScript, ou si le script échoue, le clic ouvre le document en
     * ligne. `preventDefault()` n'est donc appelé qu'une fois la modale
     * correctement armée.
     *
     * L'URL du téléchargement est déduite de celle de l'aperçu : les deux
     * points de terminaison partagent l'identifiant du fichier, et le
     * télécharger ne peut ainsi pas être divergent de ce qui est affiché.
     */
    function initialiserVisionneuse() {
        var modale = document.getElementById('modal-document');
        if (!modale) { return; }

        var image = document.getElementById('visionneuse-image');
        var cadre = document.getElementById('visionneuse-pdf');
        var titre = document.getElementById('titre-document');
        var meta = document.getElementById('document-meta');
        var lien = document.getElementById('document-telecharger');

        modale.addEventListener('show.bs.modal', function (evenement) {
            var declencheur = evenement.relatedTarget;
            if (!declencheur || !declencheur.hasAttribute('data-document')) { return; }

            var url = declencheur.getAttribute('data-document');
            var type = declencheur.getAttribute('data-type');

            titre.textContent = declencheur.getAttribute('data-nom') || 'Document';

            var parties = [];
            if (declencheur.getAttribute('data-mime')) {
                parties.push(declencheur.getAttribute('data-mime'));
            }
            if (declencheur.getAttribute('data-taille')) {
                parties.push(declencheur.getAttribute('data-taille'));
            }
            meta.textContent = parties.join(' · ');

            lien.setAttribute('href', url.replace('/apercu?', '/telecharger?'));

            if (type === 'pdf') {
                image.hidden = true;
                image.removeAttribute('src');
                cadre.hidden = false;
                cadre.setAttribute('src', url);
                return;
            }

            cadre.hidden = true;
            cadre.setAttribute('src', 'about:blank');
            image.hidden = false;
            image.setAttribute('src', url);
            image.setAttribute('alt', titre.textContent);
        });

        /*
         * À la fermeture, les deux sources sont vidées. Un `iframe` laissé
         * chargé garderait en mémoire le document — une facture en clair — et
         * afficherait encore son contenu à la réouverture si le même fichier
         * était demandé, la modale paraissant ne pas s'être fermée.
         */
        modale.addEventListener('hidden.bs.modal', function () {
            image.hidden = true;
            image.removeAttribute('src');
            cadre.hidden = true;
            cadre.setAttribute('src', 'about:blank');
        });

        document.querySelectorAll('[data-document]').forEach(function (element) {
            element.addEventListener('click', function (evenement) {
                // ctrl-clic et clic du milieu ouvrent le document dans un autre
                // onglet, comme pour n'importe quel lien : ce comportement
                // attendu ne doit pas être détourné vers la modale.
                if (evenement.ctrlKey || evenement.metaKey || evenement.shiftKey
                    || evenement.button !== 0) {
                    return;
                }
                evenement.preventDefault();
                bootstrap.Modal.getOrCreateInstance(modale).show(evenement.currentTarget);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initialiser();
        initialiserVisionneuse();
    });
})();