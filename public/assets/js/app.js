/**
 * Flotteo — JavaScript Vanilla (aucun framework).
 * Interdits stricts : alert(), confirm(), prompt().
 * Toute confirmation passe par la modale Tabler, tout retour par un toast.
 */
(function () {
    'use strict';

    window.bootstrap = window.bootstrap || window.tabler;
    var base = (window.FLOTTEO && window.FLOTTEO.base) || '';
    var TOKEN = (window.FLOTTEO && window.FLOTTEO.token) || '';

    // ---------------------------------------------------------------- Toasts
    // Icônes Font Awesome : le sous-ensemble embarqué ne contient que des noms,
    // aucun tracé SVG n'est produit côté client (règle « icônes FA obligatoires »).
    var ICONES = {
        success: 'fa-check',
        danger: 'fa-xmark',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    function toast(message, type) {
        var pile = document.getElementById('flotteo-toasts');
        if (!pile) { return; }
        type = type || 'info';

        var element = document.createElement('div');
        element.className = 'toast show border-0 mb-2 text-bg-' + type;
        element.setAttribute('role', 'alert');
        element.setAttribute('aria-live', 'assertive');
        element.innerHTML =
            '<div class="d-flex">' +
            '<div class="toast-body d-flex align-items-center">' +
            '<i class="fa-solid ' + (ICONES[type] || ICONES.info) + ' me-2" aria-hidden="true"></i>' +
            '<span></span>' +
            '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>' +
            '</div>';
        element.querySelector('span').textContent = message;
        pile.appendChild(element);

        setTimeout(function () {
            // Tabler est normally charged avant ce script ; la garde évite qu'un échec
            // de chargement ne laisse une exception non rattrapée dans la
            // console toutes les 5 secondes.
            if (typeof bootstrap === 'undefined') { return; }
            var instance = bootstrap.Toast.getInstance(element);
            if (instance) { instance.hide(); }
            setTimeout(function () { element.remove(); }, 400);
        }, 5200);
    }
    window.flotteoToast = toast;

    /** Reprise des messages flash serveur en toasts, puis retrait de l'alerte. */
    function hydraterFlash() {
        document.querySelectorAll('[data-flotteo-toast]').forEach(function (alerte) {
            var type = (alerte.className.match(/alert-([a-z]+)/) || [])[1] || 'info';
            toast(alerte.getAttribute('data-flotteo-toast'), type);
            var instance = bootstrap.Alert.getInstance(alerte);
            if (instance) { instance.close(); }
        });
    }

    // ------------------------------------------------- Confirmation modale
    var modale = null;
    var actionConfirmee = null;

    /**
     * Demande de confirmation. Remplace window.confirm.
     * @param {string} message
     * @param {Function} callback  exécuté uniquement après validation
     * @param {string} [titre]
     */
    function confirmer(message, callback, titre) {
        var element = document.getElementById('modal-confirmation');
        if (!element) { return; }

        document.getElementById('texte-confirmation').textContent = message;
        document.getElementById('titre-confirmation').textContent = titre || 'Confirmer la suppression';
        actionConfirmee = callback;

        modale = modale || new bootstrap.Modal(element);
        modale.show();
    }
    window.flotteoConfirmer = confirmer;

    function initConfirmation() {
        var element = document.getElementById('modal-confirmation');
        var bouton = document.getElementById('bouton-confirmation');
        if (!element || !bouton) { return; }

        bouton.addEventListener('click', async function () {
            if (bouton.dataset.confirmee === '1') {
                return;
            }
            bouton.dataset.confirmee = '1';
            var action = actionConfirmee;
            modale.hide();
            if (typeof action === 'function') { action(); }
        });

        element.addEventListener('hidden.bs.modal', function () {
            bouton.dataset.confirmee = '0';
            actionConfirmee = null;
        });
    }

    /**
     * Soumission d'un formulaire avec confirmation préalable.
     * Usage : <form data-confirmer="Message ?">
     */
    function initFormulairesConfirmes() {
        document.querySelectorAll('form[data-confirmer]').forEach(function (form) {
            form.addEventListener('submit', function (evenement) {
                if (form.dataset.valide === '1') { return; }
                evenement.preventDefault();
                confirmer(form.getAttribute('data-confirmer'), function () {
                    form.dataset.valide = '1';
                    form.submit();
                });
            });
        });
    }

    /** Suppression d'un enregistrement via un mini-formulaire POST. */
    function initBoutonsSuppression() {
        document.querySelectorAll('[data-supprimer]').forEach(function (bouton) {
            bouton.addEventListener('click', async function () {
                var formulaire = document.getElementById(bouton.getAttribute('data-supprimer'));
                if (!formulaire) { return; }
                formulaire.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            });
        });
    }

    // ------------------------------------------------------------ Utilitaires
    async function post(url, donnees) {
        const corps = new FormData();
        Object.keys(donnees || {}).forEach(cle => corps.append(cle, donnees[cle]));
        corps.append('_token', TOKEN);

        try {
            const reponse = await fetch(base + url, {
                method: 'POST',
                body: corps,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const json = await reponse.json();
            return { ok: reponse.ok, json: json };
        } catch (error) {
            return { ok: false, json: { message: "Erreur de connexion" } };
        }
    }
    window.flotteoPost = post;

    function get(url) {
        return fetch(base + url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }
    window.flotteoGet = get;

    /** Remplit un <select> à partir d'un endpoint JSON. */
    async function remplirSelect(selecteur, url, valeurVide) {
        const select = document.querySelector(selecteur);
        if (!select) { return; }
        const valeurInitiale = select.dataset.valeur || '';

        try {
            const payload = await get(url);
            const donnees = (payload && payload.data) || [];
            select.innerHTML = '';
            if (valeurVide) {
                select.appendChild(new Option(valeurVide, ''));
            }
            donnees.forEach(item => {
                select.appendChild(new Option(item.libelle, item.id));
            });
            if (valeurInitiale) { select.value = valeurInitiale; }
        } catch (error) {
            // Ignorer silencieusement
        }
    }
    window.flotteoRemplirSelect = remplirSelect;

    /** Normalise un montant pour l'affichage monétaire. */
    function euro(valeur) {
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(valeur || 0);
    }
    window.flotteoEuro = euro;

    // ------------------------------------------------- Copie dans le presse-papiers
    /**
     * Copie le contenu d'un champ cible dans le presse-papiers.
     *
     * Aucune alerte native n'est employée : le retour passe par la pile de
     * toasts. L'API Clipboard n'est exposée qu'en contexte sécurisé ; ailleurs
     * (HTTP simple), le texte est simplement sélectionné pour une copie manuelle.
     */
    function initBoutonsCopie() {
        document.querySelectorAll('[data-copier]').forEach(function (bouton) {
            bouton.addEventListener('click', async function () {
                var cible = document.getElementById(bouton.getAttribute('data-copier'));
                if (!cible) { return; }
                var texte = cible.value || cible.textContent || '';

                try {
                    if (!navigator.clipboard) throw new Error();
                    await navigator.clipboard.writeText(texte);
                    toast('Commande copiée dans le presse-papiers.', 'success');
                } catch (e) {
                    cible.focus();
                    cible.select();
                    toast('Copie impossible : sélectionnez le texte et copiez-le.', 'warning');
                }
            });
        });
    }

    // ------------------------------------------------- Retour en haut de page
    /**
     * Affiche le bouton de retour en haut une fois la page descendue et
     * ramène le défilement à zéro au clic.
     *
     * Le bouton est masqué en CSS (`visibility`), ce qui le sort aussi du ordre
     * de tabulation : il ne reste atteignable au clavier que lorsqu'il est
     * réellement visible.
     */
    function initBoutonHautPage() {
        var bouton = document.getElementById('flotteo-retour-haut');
        if (!bouton) { return; }

        var sansAnimation = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function defiler() {
            if (sansAnimation) {
                window.scrollTo(0, 0);
                return;
            }
            try {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (erreur) {
                window.scrollTo(0, 0);
            }
        }

        function basculer() {
            bouton.classList.toggle('visible', (window.scrollY || window.pageYOffset || 0) > 400);
        }

        bouton.addEventListener('click', defiler);
        window.addEventListener('scroll', basculer, { passive: true });
        basculer();
    }

    // ---------------------------------------------------------------- Init
    document.addEventListener('DOMContentLoaded', function () {
        initConfirmation();
        initFormulairesConfirmes();
        initBoutonsSuppression();
        initBoutonsCopie();
        initBoutonHautPage();
        hydraterFlash();
    });
})();
