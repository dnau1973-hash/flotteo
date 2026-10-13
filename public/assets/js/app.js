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

    // ------------------------------------------------- Datepicker en français
    /**
     * Configure Tabler Datepicker en français :
     * - Locale FR (mois et jours traduits)
     * - Premier jour de la semaine = Lundi
     * - Formatage français (DD-MM-YYYY)
     * - Protection contre le FocusTrap des modales Bootstrap pour garantir les clics sur les dates
     */
    function initDatepickerFr() {
        if (!window.tabler || !window.tabler.Datepicker) {
            return;
        }

        var proto = window.tabler.Datepicker.prototype;

        // Analyse flexible des dates (ISO YYYY-MM-DD ou FR DD-MM-YYYY / DD/MM/YYYY)
        proto._parseDate = function (val) {
            if (!val || typeof val !== 'string') return new Date();
            val = val.trim();
            var mIso = val.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
            if (mIso) {
                return new Date(parseInt(mIso[1], 10), parseInt(mIso[2], 10) - 1, parseInt(mIso[3], 10));
            }
            var mFr = val.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/);
            if (mFr) {
                return new Date(parseInt(mFr[3], 10), parseInt(mFr[2], 10) - 1, parseInt(mFr[1], 10));
            }
            var d = new Date(val);
            return isNaN(d.getTime()) ? new Date() : d;
        };

        // Conversion en ISO standard YYYY-MM-DD pour VanillaCalendarPro
        proto._toIsoDate = function (val) {
            if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
            var d = val instanceof Date ? val : this._parseDate(val);
            if (!d || isNaN(d.getTime())) return String(val);
            var y = d.getFullYear();
            var m = String(d.getMonth() + 1).padStart(2, '0');
            var j = String(d.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + j;
        };

        // Lecture de la valeur de l'input et synchronisation du calendrier
        proto._parseInputValue = function () {
            var val = this._element && this._element.value ? this._element.value.trim() : '';
            if (!val) return;
            var parsed = this._parseDate(val);
            if (!parsed || isNaN(parsed.getTime())) return;
            var iso = this._toIsoDate(parsed);
            this._selectedDates = [iso];
            if (this._calendar) {
                this._calendar.set({
                    selectedDates: this._selectedDates,
                    selectedMonth: parsed.getMonth(),
                    selectedYear: parsed.getFullYear()
                });
            }
        };

        // Écriture de la sélection dans l'input avec déclenchement des événements natifs
        proto._writeSelection = function (t) {
            var formatted = this._formatDateForInput(t);
            if (this._isInput) {
                this._element.value = formatted;
                this._element.dispatchEvent(new Event('input', { bubbles: true }));
                this._element.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (this._boundInput) {
                this._boundInput.value = t.join(',');
            }
            if (this._displayElement) {
                this._displayElement.textContent = formatted;
            }
        };

        // Format DD-MM-YYYY pour les formulaires français
        var formatFr = function (date) {
            var y = date.getFullYear();
            var m = String(date.getMonth() + 1).padStart(2, '0');
            var d = String(date.getDate()).padStart(2, '0');
            return d + '-' + m + '-' + y;
        };

        var defauts = window.tabler.Datepicker.Default;
        if (defauts) {
            defauts.locale = 'fr';
            defauts.firstWeekday = 1;
            defauts.dateFormat = formatFr;
        }

        // Met à jour les instances déjà créées sans les détruire
        document.querySelectorAll('[data-bs-toggle="datepicker"], [data-tblr-toggle="datepicker"]').forEach(function (el) {
            var instance = window.tabler.Datepicker.getInstance(el);
            if (instance) {
                instance._config.locale = 'fr';
                instance._config.firstWeekday = 1;
                instance._config.dateFormat = formatFr;
                instance._parseInputValue();
            }
        });
    }

    // Protection des clics dans les modales Bootstrap :
    // Le FocusTrap de Bootstrap écoute sur document en phase de bouillonnement et vole
    // le focus dès qu'on clique sur un élément hors de la modale (.vc est dans body).
    // Ces écouteurs en phase de capture maintiennent le focus sur l'input et autorisent le clic.
    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.closest && e.target.closest('[data-vc="calendar"], .vc-calendar, .vc')) {
            e.stopImmediatePropagation();
        }
    }, true);

    document.addEventListener('mousedown', function (e) {
        if (e.target && e.target.closest && e.target.closest('[data-vc="calendar"], .vc-calendar, .vc')) {
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
            }
        }
    }, true);

    // Exécution immédiate au chargement du script
    initDatepickerFr();

    // ---------------------------------------------------------------- Autocomplétion
    /*
     * Transforme un <select data-autocomplete> en champ de recherche avec liste
     * filtrée. Le <select> d'origine reste dans le DOM (masqué visuellement) et
     * demeure la source de vérité : c'est lui qui est soumis, validé (required)
     * et pré-rempli par les scripts existants (`select.value = …`, form.reset()).
     */
    function normaliser(texte) {
        return String(texte || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[\s\-_.]+/g, ' ')
            .trim();
    }

    function echapperHtml(texte) {
        return String(texte).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Surligne les termes recherchés (insensible à la casse, hors balises HTML).
    function surlignerTexte(libelle, termes) {
        var html = echapperHtml(libelle);
        termes.forEach(function (t) {
            if (!t) { return; }
            var motif = echapperHtml(t).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            html = html.replace(new RegExp('(' + motif + ')(?![^<]*>)', 'ig'), '<mark>$1</mark>');
        });
        return html;
    }

    function initAutocompletion(select) {
        if (select.dataset.autocompleteInit === '1') { return; }
        select.dataset.autocompleteInit = '1';

        var maxResultats = parseInt(select.getAttribute('data-autocomplete-max') || '50', 10);
        var options = Array.prototype.filter.call(select.options, function (o) {
            return o.value !== '';
        }).map(function (o) {
            var libelle = o.textContent.replace(/\s+/g, ' ').trim();
            // L'immatriculation est aussi indexée sans tirets : « AB123CD » trouve « AB-123-CD ».
            return { valeur: o.value, libelle: libelle, cle: normaliser(libelle) + ' ' + normaliser(libelle).replace(/ /g, '') };
        });

        var conteneur = document.createElement('div');
        conteneur.className = 'autocomplete position-relative';

        var champ = document.createElement('input');
        champ.type = 'text';
        champ.className = 'form-control';
        champ.id = select.id ? select.id + '_recherche' : '';
        champ.placeholder = select.getAttribute('data-autocomplete-placeholder') || 'Rechercher…';
        champ.setAttribute('autocomplete', 'off');
        champ.setAttribute('role', 'combobox');
        champ.setAttribute('aria-autocomplete', 'list');
        champ.setAttribute('aria-expanded', 'false');

        var bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.className = 'autocomplete-effacer btn-close';
        bouton.setAttribute('aria-label', 'Effacer');
        bouton.hidden = true;

        var menu = document.createElement('div');
        menu.className = 'dropdown-menu autocomplete-menu w-100';
        menu.setAttribute('role', 'listbox');
        if (champ.id) {
            menu.id = champ.id + '_liste';
            champ.setAttribute('aria-controls', menu.id);
        }

        select.parentNode.insertBefore(conteneur, select);
        conteneur.appendChild(champ);
        conteneur.appendChild(bouton);
        conteneur.appendChild(menu);
        conteneur.appendChild(select);
        select.classList.add('autocomplete-select');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');

        // Le label pointe désormais vers le champ de recherche.
        if (select.id) {
            var label = document.querySelector('label[for="' + select.id + '"]');
            if (label) { label.setAttribute('for', champ.id); }
        }

        var resultats = [];
        var indexActif = -1;

        function libelleSelectionne() {
            var o = select.options[select.selectedIndex];
            return o && o.value !== '' ? o.textContent.replace(/\s+/g, ' ').trim() : '';
        }

        function synchroniserAffichage() {
            champ.value = libelleSelectionne();
            bouton.hidden = champ.value === '';
            champ.classList.remove('is-invalid');
        }

        function fermer() {
            menu.classList.remove('show');
            champ.setAttribute('aria-expanded', 'false');
            indexActif = -1;
        }

        function afficher(requete) {
            var termes = normaliser(requete).split(' ').filter(Boolean);
            resultats = options.filter(function (o) {
                return termes.every(function (t) { return o.cle.indexOf(t) !== -1; });
            });

            var total = resultats.length;
            var visibles = resultats.slice(0, maxResultats);
            menu.innerHTML = '';

            if (total === 0) {
                menu.innerHTML = '<span class="dropdown-item-text text-secondary">Aucun véhicule trouvé</span>';
            } else {
                visibles.forEach(function (o, i) {
                    var item = document.createElement('a');
                    item.href = '#';
                    item.className = 'dropdown-item' + (o.valeur === select.value ? ' active' : '');
                    item.setAttribute('role', 'option');
                    item.setAttribute('data-index', String(i));
                    item.innerHTML = surlignerTexte(o.libelle, requete.trim().split(/\s+/));
                    menu.appendChild(item);
                });
                if (total > visibles.length) {
                    var info = document.createElement('span');
                    info.className = 'dropdown-item-text small text-secondary';
                    info.textContent = (total - visibles.length) + ' autre(s) résultat(s) — affinez la recherche';
                    menu.appendChild(info);
                }
            }

            indexActif = -1;
            menu.classList.add('show');
            champ.setAttribute('aria-expanded', 'true');
        }

        function activer(index) {
            var items = menu.querySelectorAll('.dropdown-item[data-index]');
            if (!items.length) { return; }
            indexActif = (index + items.length) % items.length;
            items.forEach(function (el, i) { el.classList.toggle('focus', i === indexActif); });
            items[indexActif].scrollIntoView({ block: 'nearest' });
        }

        function choisir(index) {
            var o = resultats[index];
            if (!o) { return; }
            valeurNative.set.call(select, o.valeur);
            select.dispatchEvent(new Event('change', { bubbles: true }));
            synchroniserAffichage();
            fermer();
        }

        // Interception des affectations programmatiques `select.value = …`
        // (pré-remplissage des modales d'édition) pour rafraîchir le champ visible.
        var valeurNative = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
        Object.defineProperty(select, 'value', {
            configurable: true,
            get: function () { return valeurNative.get.call(this); },
            set: function (v) { valeurNative.set.call(this, v); synchroniserAffichage(); }
        });

        champ.addEventListener('input', function () {
            bouton.hidden = champ.value === '';
            afficher(champ.value);
        });

        champ.addEventListener('focus', function () {
            champ.select();
            afficher('');
        });

        champ.addEventListener('keydown', function (e) {
            var ouvert = menu.classList.contains('show');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!ouvert) { afficher(champ.value === libelleSelectionne() ? '' : champ.value); }
                activer(indexActif + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activer(indexActif - 1);
            } else if (e.key === 'Enter') {
                if (ouvert) {
                    e.preventDefault();
                    if (indexActif >= 0) {
                        choisir(indexActif);
                    } else if (resultats.length === 1) {
                        choisir(0);
                    }
                }
            } else if (e.key === 'Escape') {
                if (ouvert) {
                    // Ne pas fermer la modale : on ferme seulement la liste.
                    e.preventDefault();
                    e.stopPropagation();
                    fermer();
                    synchroniserAffichage();
                }
            } else if (e.key === 'Tab') {
                fermer();
            }
        });

        champ.addEventListener('blur', function () {
            // Délai : laisse le clic sur un élément de la liste aboutir.
            setTimeout(function () {
                fermer();
                synchroniserAffichage();
            }, 150);
        });

        menu.addEventListener('mousedown', function (e) {
            // Garde le focus sur le champ pendant le clic.
            e.preventDefault();
        });

        menu.addEventListener('click', function (e) {
            var item = e.target.closest('.dropdown-item[data-index]');
            if (!item) { return; }
            e.preventDefault();
            choisir(parseInt(item.getAttribute('data-index'), 10));
        });

        bouton.addEventListener('click', function () {
            valeurNative.set.call(select, '');
            select.dispatchEvent(new Event('change', { bubbles: true }));
            synchroniserAffichage();
            champ.focus();
        });

        select.addEventListener('change', synchroniserAffichage);

        // Validation HTML5 : le select masqué est requis, on signale l'erreur sur le champ visible.
        select.addEventListener('invalid', function (e) {
            e.preventDefault();
            champ.classList.add('is-invalid');
            champ.focus();
        });

        if (select.form) {
            select.form.addEventListener('reset', function () {
                setTimeout(synchroniserAffichage, 0);
            });
        }

        // Pré-remplissage effectué avant l'ouverture d'une modale : resynchronisation à l'affichage.
        var modale = select.closest('.modal');
        if (modale) {
            modale.addEventListener('shown.bs.modal', synchroniserAffichage);
            modale.addEventListener('hide.bs.modal', fermer);
        }

        synchroniserAffichage();
    }

    function initAutocompletions() {
        document.querySelectorAll('select[data-autocomplete]').forEach(initAutocompletion);
        document.querySelectorAll('input[data-suggestions]').forEach(initSuggestions);
    }

    /*
     * Suggestions sur un champ texte libre : <input data-suggestions='[{"valeur":…,"libelle":…}]'>.
     * Contrairement au <select data-autocomplete>, la saisie reste libre (recherche
     * partielle possible) ; la liste n'est qu'une aide. Avec `data-suggestions-submit`,
     * choisir une suggestion soumet directement le formulaire.
     */
    function initSuggestions(champ) {
        if (champ.dataset.suggestionsInit === '1') { return; }
        champ.dataset.suggestionsInit = '1';

        var donnees;
        try {
            donnees = JSON.parse(champ.getAttribute('data-suggestions') || '[]');
        } catch (err) {
            return;
        }
        if (!Array.isArray(donnees) || donnees.length === 0) { return; }

        var maxResultats = parseInt(champ.getAttribute('data-suggestions-max') || '30', 10);
        var soumettre = champ.hasAttribute('data-suggestions-submit');
        var items = donnees.map(function (d) {
            var libelle = String(d.libelle || d.valeur);
            var cle = normaliser(libelle);
            return { valeur: String(d.valeur), libelle: libelle, cle: cle + ' ' + cle.replace(/ /g, '') };
        });

        var parent = champ.parentNode;
        if (getComputedStyle(parent).position === 'static') {
            parent.classList.add('position-relative');
        }

        var menu = document.createElement('div');
        menu.className = 'dropdown-menu autocomplete-menu w-100';
        menu.setAttribute('role', 'listbox');
        if (champ.id) {
            menu.id = champ.id + '_suggestions';
            champ.setAttribute('aria-controls', menu.id);
        }
        champ.setAttribute('role', 'combobox');
        champ.setAttribute('aria-autocomplete', 'list');
        champ.setAttribute('aria-expanded', 'false');
        champ.insertAdjacentElement('afterend', menu);

        var resultats = [];
        var indexActif = -1;

        function fermer() {
            menu.classList.remove('show');
            champ.setAttribute('aria-expanded', 'false');
            indexActif = -1;
        }

        function afficher() {
            var requete = champ.value;
            var termes = normaliser(requete).split(' ').filter(Boolean);
            resultats = items.filter(function (o) {
                return termes.every(function (t) { return o.cle.indexOf(t) !== -1; });
            });

            menu.innerHTML = '';
            if (resultats.length === 0) {
                fermer();
                return;
            }

            resultats.slice(0, maxResultats).forEach(function (o, i) {
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'dropdown-item';
                a.setAttribute('role', 'option');
                a.setAttribute('data-index', String(i));
                a.innerHTML = surlignerTexte(o.libelle, requete.trim().split(/\s+/));
                menu.appendChild(a);
            });
            if (resultats.length > maxResultats) {
                var info = document.createElement('span');
                info.className = 'dropdown-item-text small text-secondary';
                info.textContent = (resultats.length - maxResultats) + ' autre(s) résultat(s) — affinez la recherche';
                menu.appendChild(info);
            }

            indexActif = -1;
            menu.classList.add('show');
            champ.setAttribute('aria-expanded', 'true');
        }

        function activer(index) {
            var liens = menu.querySelectorAll('.dropdown-item[data-index]');
            if (!liens.length) { return; }
            indexActif = (index + liens.length) % liens.length;
            liens.forEach(function (el, i) { el.classList.toggle('focus', i === indexActif); });
            liens[indexActif].scrollIntoView({ block: 'nearest' });
        }

        function choisir(index) {
            var o = resultats[index];
            if (!o) { return; }
            champ.value = o.valeur;
            champ.dispatchEvent(new Event('change', { bubbles: true }));
            fermer();
            if (soumettre && champ.form) {
                if (typeof champ.form.requestSubmit === 'function') {
                    champ.form.requestSubmit();
                } else {
                    champ.form.submit();
                }
            }
        }

        champ.addEventListener('input', afficher);
        champ.addEventListener('focus', afficher);

        champ.addEventListener('keydown', function (e) {
            var ouvert = menu.classList.contains('show');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!ouvert) { afficher(); }
                activer(indexActif + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activer(indexActif - 1);
            } else if (e.key === 'Enter') {
                // Sans élément actif, Entrée soumet la saisie libre (recherche partielle).
                if (ouvert && indexActif >= 0) {
                    e.preventDefault();
                    choisir(indexActif);
                }
            } else if (e.key === 'Escape') {
                if (ouvert) {
                    e.preventDefault();
                    e.stopPropagation();
                    fermer();
                }
            } else if (e.key === 'Tab') {
                fermer();
            }
        });

        champ.addEventListener('blur', function () {
            setTimeout(fermer, 150);
        });

        menu.addEventListener('mousedown', function (e) { e.preventDefault(); });
        menu.addEventListener('click', function (e) {
            var lien = e.target.closest('.dropdown-item[data-index]');
            if (!lien) { return; }
            e.preventDefault();
            choisir(parseInt(lien.getAttribute('data-index'), 10));
        });
    }

    // ---------------------------------------------------------------- Init
    document.addEventListener('DOMContentLoaded', function () {
        initDatepickerFr();
        initAutocompletions();
        initConfirmation();
        initFormulairesConfirmes();
        initBoutonsSuppression();
        initBoutonsCopie();
        initBoutonHautPage();
        hydraterFlash();
    });
})();

