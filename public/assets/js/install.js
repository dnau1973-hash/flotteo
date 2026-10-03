/**
 * Assistant d'installation — comportements du formulaire en deux étapes.
 *
 * Contraintes respectées : aucun CDN, aucune dépendance tierce, aucune boîte
 * de dialogue native (alert/confirm/prompt). Les retours passent par la zone
 * #retour-test, la jauge de robustesse et les toasts Tabler de app.js.
 */
(function () {
    'use strict';

    var formulaireBdd = document.getElementById('formulaire-bdd');
    var formulaireAdmin = document.getElementById('formulaire-admin');
    var etape1 = document.getElementById('etape-1');
    var etape2 = document.getElementById('etape-2');
    var etape3 = document.getElementById('etape-3');
    var boutonTester = document.getElementById('bouton-tester');
    var boutonInstaller = document.getElementById('bouton-installer');
    var retourTest = document.getElementById('retour-test');
    var aideForce = document.getElementById('aide-force');
    var aideConfirmation = document.getElementById('aide-confirmation');
    var champMotdepasse = document.getElementById('admin_password');
    var champConfirmation = document.getElementById('admin_password_confirm');

    if (!formulaireBdd) {
        return;
    }

    /** Connexion validée : garde les paramètres BDD pour la soumission finale. */
    var connexionValidee = false;

    /** Jeton CSRF, injecté par la vue via window.FLOTTEO. */
    var TOKEN = (window.FLOTTEO && window.FLOTTEO.token) || '';

    function basculerEtape(active, precedent) {
        var etapes = [etape1, etape2, etape3];
        etapes.forEach(function (bloc) {
            if (bloc) { bloc.classList.add('d-none'); }
        });
        var cible = active === 1 ? etape1 : (active === 2 ? etape2 : etape3);
        if (cible) { cible.classList.remove('d-none'); }

        Array.prototype.forEach.call(
            document.querySelectorAll('[data-etape-marqueur]'),
            function (marqueur) {
                var numero = parseInt(marqueur.getAttribute('data-etape-marqueur'), 10);
                marqueur.classList.toggle('etape-faite', numero < active);
                marqueur.classList.toggle('etape-active', numero === active);
            }
        );

        var titre = document.querySelector('.install-titre');
        if (titre) { titre.setAttribute('data-etape', String(active)); }

        if (precedent) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    /**
     * Rend le retour du test de connexion.
     *
     * Le message n'est rendu qu'une seule fois, dans l'alerte Tabler. Une
     * seconde version en `text-secondary` avait été ajoutée pour faire ressortir
     * le détail en cas d'échec : elle répétait le texte à l'identique juste sous
     * lui, d'où un doublon gris sous l'alerte rouge. Le détail n'est affiché que
     * lorsqu'il apporte une information distincte du message.
     */
    function afficherRetour(etat, message, detail) {
        var icone = etat === 'succes'
            ? '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5l10 -10"/></svg>'
            : '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>';
        var classe = etat === 'succes' ? 'alert-success' : 'alert-danger';

        // Le détail n'est retenu que s'il diffère du message : pas de répétition.
        var complement = (detail && detail !== message)
            ? '<div class="text-secondary small mt-1">' + echapper(detail) + '</div>'
            : '';

        retourTest.innerHTML = '<div class="alert ' + classe + ' d-flex align-items-start mb-0">'
            + icone
            + '<div class="ms-2"><strong>' + (etat === 'succes' ? 'Connexion réussie' : 'Connexion impossible') + '</strong>'
            + '<div class="small">' + echapper(message) + '</div>' + complement + '</div>'
            + '</div>';
    }

    function echapper(texte) {
        var div = document.createElement('div');
        div.textContent = texte == null ? '' : String(texte);
        return div.innerHTML;
    }

    function parametresBdd() {
        return {
            db_host: document.getElementById('db_host').value.trim(),
            db_port: document.getElementById('db_port').value.trim(),
            db_name: document.getElementById('db_name').value.trim(),
            db_username: document.getElementById('db_username').value.trim(),
            db_password: document.getElementById('db_password').value
        };
    }

    function memoriserParametres() {
        var valeurs = parametresBdd();
        document.getElementById('repren_host').value = valeurs.db_host;
        document.getElementById('repren_port').value = valeurs.db_port;
        document.getElementById('repren_name').value = valeurs.db_name;
        document.getElementById('repren_user').value = valeurs.db_username;
        document.getElementById('repren_password').value = valeurs.db_password;
    }

    // ------------------------------------------------------ Étape 1 : test AJAX

    if (boutonTester) {
        boutonTester.addEventListener('click', function () {
            if (!formulaireBdd.checkValidity()) {
                formulaireBdd.reportValidity();
                return;
            }

            connexionValidee = false;
            boutonTester.disabled = true;
            boutonTester.textContent = 'Connexion en cours…';
            retourTest.innerHTML = '';

            var charge = { _token: TOKEN };

            fetch(formulaireBdd.getAttribute('action'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams(Object.assign({}, charge, parametresBdd())).toString()
            })
                .then(function (reponse) {
                    return reponse.json().then(function (corps) {
                        return { ok: reponse.ok, statut: reponse.status, corps: corps };
                    });
                })
                .then(function (resultat) {
                    var succes = Boolean(resultat.corps && resultat.corps.success);
                    afficherRetour(
                        succes ? 'succes' : 'echec',
                        (resultat.corps && resultat.corps.message) || 'Réponse inattendue du serveur.',
                        (resultat.corps && resultat.corps.detail) || ''
                    );
                    connexionValidee = succes;

                    if (succes) {
                        memoriserParametres();
                        basculerEtape(2, true);
                        var champNom = document.getElementById('nom_d_utilisateur');
                        if (champNom) { champNom.focus(); }
                    } else if (resultat.statut === 419) {
                        window.flotteoToast('Jeton de sécurité expiré : rechargez la page.', 'warning');
                    }
                })
                .catch(function () {
                    afficherRetour('echec', 'Le serveur n\'a pas répondu. Vérifiez que l\'URL de l\'application est correcte.');
                })
                .then(function () {
                    boutonTester.disabled = false;
                    boutonTester.textContent = 'Tester la connexion';
                });
        });
    }

    var boutonRetour = document.getElementById('retour-etape-1');
    if (boutonRetour) {
        boutonRetour.addEventListener('click', function () {
            basculerEtape(1, true);
        });
    }

    // -------------------------------------- Étape 2 : robustesse et soumission

    function evaluerRobustesse(valeur) {
        var score = 0;
        if (valeur.length >= 10) { score += 25; }
        if (valeur.length >= 14) { score += 15; }
        if (/[a-z]/.test(valeur) && /[A-Z]/.test(valeur)) { score += 25; }
        if (/\d/.test(valeur)) { score += 20; }
        if (/[^A-Za-z0-9]/.test(valeur)) { score += 15; }
        return Math.min(score, 100);
    }

    if (champMotdepasse && aideForce) {
        champMotdepasse.addEventListener('input', function () {
            var score = evaluerRobustesse(champMotdepasse.value);
            var jauge = document.getElementById('jauge-force');
            var barre = jauge ? jauge.firstElementChild : null;
            var libelle;

            if (score < 40) { libelle = 'Trop faible'; if (barre) { barre.className = 'faible'; } }
            else if (score < 70) { libelle = 'Acceptable'; if (barre) { barre.className = 'moyen'; } }
            else if (score < 90) { libelle = 'Correct'; if (barre) { barre.className = 'bon'; } }
            else { libelle = 'Excellent'; if (barre) { barre.className = 'excellent'; } }

            if (barre) { barre.style.width = score + '%'; }
            aideForce.textContent = champMotdepasse.value.length === 0
                ? '10 caractères minimum, avec au moins une minuscule, une majuscule et un chiffre.'
                : 'Robustesse : ' + libelle + ' (' + score + ' / 100)';
        });
    }

    if (champConfirmation && aideConfirmation) {
        champConfirmation.addEventListener('input', function () {
            if (champConfirmation.value.length === 0) {
                aideConfirmation.innerHTML = '&nbsp;';
                champConfirmation.classList.remove('is-invalid', 'is-valid');
                return;
            }
            var conforme = champMotdepasse && champConfirmation.value === champMotdepasse.value;
            aideConfirmation.textContent = conforme ? 'Les deux saisies correspondent.' : 'Les deux saisies diffèrent.';
            champConfirmation.classList.toggle('is-valid', conforme);
            champConfirmation.classList.toggle('is-invalid', !conforme);
        });
    }

    if (formulaireAdmin) {
        formulaireAdmin.addEventListener('submit', function (evenement) {
            evenement.preventDefault();

            if (!connexionValidee) {
                basculerEtape(1, true);
                window.flotteoToast('Testez d\'abord la connexion à la base de données.', 'warning');
                return;
            }
            if (!formulaireAdmin.checkValidity()) {
                formulaireAdmin.reportValidity();
                return;
            }
            if (champMotdepasse.value !== champConfirmation.value) {
                champConfirmation.setCustomValidity('La confirmation ne correspond pas.');
                formulaireAdmin.reportValidity();
                champConfirmation.setCustomValidity('');
                return;
            }

            memoriserParametres();
            basculerEtape(3, true);
            if (boutonInstaller) { boutonInstaller.disabled = true; }
            formulaireAdmin.submit();
        });
    }

    basculerEtape(1, false);
})();
