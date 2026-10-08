/**
 * Flotteo — page Utilisateurs (JavaScript Vanilla).
 *
 * Deux responsabilités, aucune dépendance :
 *   1. préremplir la modale d'édition depuis le bouton « Éditer » de la carte ;
 *   2. prévisualiser l'image choisie avant l'enregistrement, et masquer la
 *      suppression tant qu'un fichier est sélectionné — un dépôt l'emporte
 *      toujours sur la case à cocher, côté serveur comme ici.
 *
 * L'ouverture du sélecteur de fichier ne relève pas de ce script : la vue pose
 * une étiquette `for` sur le champ, si bien que le clic ouvre la boîte native.
 * Une activation programmatique (`input.click()`) n'est pas fiable sur un champ
 * masqué — selon le navigateur, le sélecteur ne s'ouvre pas, sans erreur.
 *
 * Aucune alerte native : un format refusé est signalé dans l'aide affichée sous
 * le bouton de choix.
 *
 * Le script ne connaît aucune URL de base : la vue fournit sur chaque bouton
 * d'édition le nom du fichier enregistré et son URL publique, via `data-avatar`
 * et `data-avatar-url`. Aucune constante n'est donc figée ici.
 */
(function () {
    'use strict';

    var modale = document.getElementById('modal-utilisateur');
    if (!modale) { return; }

    var formulaire    = document.getElementById('formulaire-utilisateur');
    var champNom      = document.getElementById('u_nom');
    var champEmail    = document.getElementById('u_email');
    var champRole     = document.getElementById('u_role');
    var champActif    = document.getElementById('u_actif');
    var champMdp      = document.getElementById('u_password');
    var aideMdp       = document.getElementById('aide-mdp');
    var champId       = document.getElementById('utilisateur-id');
    var titreModale   = document.getElementById('titre-utilisateur');

    var apercu        = document.getElementById('u_avatar_apercu');
    var fichier       = document.getElementById('u_avatar_fichier');
    var aideAvatar    = document.getElementById('u_avatar_aide');
    var blocSupprimer = document.getElementById('u_avatar_supprimer_bloc');
    var caseSupprimer = document.getElementById('u_avatar_supprimer');

    var TYPES_ACCEPTES = ['image/jpeg', 'image/png', 'image/webp'];
    var AIDE_DEFAUT = 'JPG, PNG ou WEBP, 8 Mo maximum. Sans image, les initiales du nom sont affichées.';

    /** Rétablit l'aperçu sur l'image fournie, ou sur les initiales. */
    function afficherApercu(image, initiales) {
        if (!apercu) { return; }
        apercu.style.backgroundImage = image ? "url('" + image + "')" : '';
        apercu.classList.toggle('bg-cover', Boolean(image));
        apercu.textContent = image ? '' : initiales;
    }

    /** Aperçu de repli, celui de l'utilisateur en cours d'édition. */
    function apercuEnregistre() {
        afficherApercu(
            fichier ? (fichier.getAttribute('data-avatar-url') || '') : '',
            fichier ? (fichier.getAttribute('data-initiales') || '?') : '?'
        );
    }

    /**
     * Initiales de repli, dans la même règle que `Models\User::initiales()` :
     * coupure sur espaces, tirets et apostrophes ; premier et dernier mot ; un
     * seul mot donne sa première lettre.
     */
    function initialesDuNom(nom) {
        var mots = (nom || '').trim().split(/[\s\-'’]+/).filter(Boolean);
        if (mots.length === 0) { return '?'; }
        if (mots.length === 1) { return mots[0].charAt(0).toUpperCase(); }
        return (mots[0].charAt(0) + mots[mots.length - 1].charAt(0)).toUpperCase();
    }

    /** Réarme le formulaire pour un nouvel utilisateur. */
    function modeCreation() {
        formulaire.reset();
        champId.value = '0';
        titreModale.textContent = 'Nouvel utilisateur';
        champMdp.required = true;
        aideMdp.textContent = 'Obligatoire à la création (8 caractères minimum).';

        if (caseSupprimer) { caseSupprimer.checked = false; }
        if (blocSupprimer) { blocSupprimer.hidden = true; }
        // Les marqueurs de l'utilisateur précédent sont effacés : sans cela,
        // un refus de format après une édition réafficherait son avatar dans le
        // formulaire de création.
        if (fichier) {
            fichier.setAttribute('data-avatar', '');
            fichier.setAttribute('data-avatar-url', '');
            fichier.setAttribute('data-initiales', '?');
        }
        afficherApercu('', '?');
        if (aideAvatar) { aideAvatar.textContent = AIDE_DEFAUT; }
    }

    if (fichier) {
        fichier.addEventListener('change', function () {
            var choisi = (fichier.files && fichier.files[0]) || null;

            if (!choisi) {
                // Annulation de la boîte de dialogue : l'aperçu revient à l'état
                // précédent au lieu de rester vide.
                apercuEnregistre();
                if (aideAvatar) { aideAvatar.textContent = AIDE_DEFAUT; }
                return;
            }

            // Contrôle de confort : le serveur refuse de toute façon le fichier,
            // mais l'utilisateur voit immédiatement qu'il s'est trompé.
            if (TYPES_ACCEPTES.indexOf(choisi.type) === -1) {
                fichier.value = '';
                apercuEnregistre();
                if (aideAvatar) {
                    aideAvatar.textContent = 'Format refusé : choisissez un fichier JPG, PNG ou WEBP.';
                }
                return;
            }

            var lecteur = new FileReader();
            lecteur.onload = function (evenement) {
                afficherApercu(evenement.target.result, '');
                if (blocSupprimer) { blocSupprimer.hidden = true; }
                if (caseSupprimer) { caseSupprimer.checked = false; }
                if (aideAvatar) { aideAvatar.textContent = choisi.name; }
            };
            lecteur.readAsDataURL(choisi);
        });
    }

    modale.addEventListener('show.bs.modal', function (evenement) {
        var declencheur = evenement.relatedTarget;

        // Seul le bouton « Éditer » porte ce marqueur : les deux autres
        // déclencheurs (bouton d'ajout, affichage programmatique) rouvrent le
        // formulaire en mode création.
        var edition = declencheur
            && declencheur.hasAttribute
            && declencheur.hasAttribute('data-edition-utilisateur');

        modeCreation();

        if (!edition) { return; }

        var nom       = declencheur.getAttribute('data-nom') || '';
        var avatar    = declencheur.getAttribute('data-avatar') || '';
        var initiales = declencheur.getAttribute('data-initiales') || initialesDuNom(nom);

        if (fichier) {
            fichier.setAttribute('data-avatar', avatar);
            fichier.setAttribute('data-avatar-url', declencheur.getAttribute('data-avatar-url') || '');
            fichier.setAttribute('data-initiales', initiales);
        }

        champId.value = declencheur.getAttribute('data-id');
        titreModale.textContent = 'Modifier l\'utilisateur';
        champNom.value = nom;
        champEmail.value = declencheur.getAttribute('data-email') || '';
        champRole.value = declencheur.getAttribute('data-role') || '';
        champActif.checked = declencheur.getAttribute('data-actif') === '1';
        champMdp.required = false;
        aideMdp.textContent = 'Laisser vide pour conserver le mot de passe actuel.';

        afficherApercu(
            declencheur.getAttribute('data-avatar-url') || '',
            initiales
        );
        // La suppression n'a de sens que si une image existe déjà.
        if (blocSupprimer) { blocSupprimer.hidden = avatar === ''; }
    });
})();