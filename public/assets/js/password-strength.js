/**
 * Flotteo — Contrôle de complexité et générateur de mot de passe sécurisé.
 *
 * Règles requises :
 * - Minimum 10 caractères
 * - Au moins une minuscule (a-z)
 * - Au moins une majuscule (A-Z)
 * - Au moins un chiffre (0-9)
 * - Au moins un caractère spécial (!@#$%^&*...)
 * - Concordance nouveau / confirmation
 */
(function () {
    'use strict';

    function initialiser() {
        var inputActuel = document.getElementById('password_actuel');
        var inputNouveau = document.getElementById('password_nouveau');
        var inputConfirm = document.getElementById('password_confirmation');
        if (!inputNouveau) { return; }

        var barForce = document.getElementById('force-bar');
        var texteForce = document.getElementById('force-texte');

        var critLongueur = document.getElementById('crit-longueur');
        var critMinuscule = document.getElementById('crit-minuscule');
        var critMajuscule = document.getElementById('crit-majuscule');
        var critChiffre = document.getElementById('crit-chiffre');
        var critSpecial = document.getElementById('crit-special');
        var critMatch = document.getElementById('crit-match');

        var btnGenerer = document.getElementById('btn-generer-mdp');
        var btnCopier = document.getElementById('btn-copier-mdp');
        var btnSoumettre = document.getElementById('btn-soumettre-mdp');

        var CHARS_MIN = 'abcdefghjkmnpqrstuvwxyz';
        var CHARS_MAJ = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        var CHARS_NUM = '23456789';
        var CHARS_SPE = '!@#$%&*+-=?_~';

        function randomInt(max) {
            if (window.crypto && window.crypto.getRandomValues) {
                var array = new Uint32Array(1);
                window.crypto.getRandomValues(array);
                return array[0] % max;
            }
            return Math.floor(Math.random() * max);
        }

        function genererMotDePasseFort() {
            var mdp = [];
            // Garantir au moins 2 de chaque catégorie
            for (var i = 0; i < 3; i++) { mdp.push(CHARS_MIN[randomInt(CHARS_MIN.length)]); }
            for (var j = 0; j < 3; j++) { mdp.push(CHARS_MAJ[randomInt(CHARS_MAJ.length)]); }
            for (var k = 0; k < 3; k++) { mdp.push(CHARS_NUM[randomInt(CHARS_NUM.length)]); }
            for (var l = 0; l < 3; l++) { mdp.push(CHARS_SPE[randomInt(CHARS_SPE.length)]); }

            // Remplir jusqu'à 16 caractères avec le tout
            var TOUT = CHARS_MIN + CHARS_MAJ + CHARS_NUM + CHARS_SPE;
            while (mdp.length < 16) {
                mdp.push(TOUT[randomInt(TOUT.length)]);
            }

            // Mélange Fisher-Yates
            for (var m = mdp.length - 1; m > 0; m--) {
                var n = randomInt(m + 1);
                var temp = mdp[m];
                mdp[m] = mdp[n];
                mdp[n] = temp;
            }

            return mdp.join('');
        }

        function majItemCritere(element, estValide, texteDefaut) {
            if (!element) { return; }
            var icone = element.querySelector('.critere-icon');
            if (estValide) {
                element.classList.remove('text-secondary', 'text-danger');
                element.classList.add('text-success');
                if (icone) {
                    icone.className = 'critere-icon fa-solid fa-check text-success me-1';
                }
            } else {
                element.classList.remove('text-success');
                element.classList.add('text-secondary');
                if (icone) {
                    icone.className = 'critere-icon fa-regular fa-circle text-secondary me-1';
                }
            }
        }

        function evaluerForce() {
            var val = inputNouveau.value || '';
            var valConfirm = (inputConfirm && inputConfirm.value) || '';

            var hasLen = val.length >= 10;
            var hasMin = /[a-z]/.test(val);
            var hasMaj = /[A-Z]/.test(val);
            var hasNum = /[0-9]/.test(val);
            var hasSpe = /[^a-zA-Z0-9]/.test(val);
            var doesMatch = val.length > 0 && val === valConfirm;

            majItemCritere(critLongueur, hasLen);
            majItemCritere(critMinuscule, hasMin);
            majItemCritere(critMajuscule, hasMaj);
            majItemCritere(critChiffre, hasNum);
            majItemCritere(critSpecial, hasSpe);

            if (critMatch) {
                if (valConfirm.length === 0) {
                    majItemCritere(critMatch, false);
                } else if (doesMatch) {
                    majItemCritere(critMatch, true);
                } else {
                    critMatch.classList.remove('text-secondary', 'text-success');
                    critMatch.classList.add('text-danger');
                    var iconeM = critMatch.querySelector('.critere-icon');
                    if (iconeM) {
                        iconeM.className = 'critere-icon fa-solid fa-xmark text-danger me-1';
                    }
                }
            }

            var score = 0;
            if (hasLen) { score += 20; if (val.length >= 14) { score += 10; } }
            if (hasMin) { score += 20; }
            if (hasMaj) { score += 20; }
            if (hasNum) { score += 20; }
            if (hasSpe) { score += 20; }
            if (score > 100) { score = 100; }

            if (barForce) {
                barForce.style.width = score + '%';
                barForce.classList.remove('bg-danger', 'bg-warning', 'bg-info', 'bg-success');

                if (score < 40) {
                    barForce.classList.add('bg-danger');
                    if (texteForce) { texteForce.textContent = val.length === 0 ? 'Aucun' : 'Faible'; texteForce.className = 'small text-danger fw-bold'; }
                } else if (score < 80) {
                    barForce.classList.add('bg-warning');
                    if (texteForce) { texteForce.textContent = 'Moyen'; texteForce.className = 'small text-warning fw-bold'; }
                } else if (score < 100) {
                    barForce.classList.add('bg-info');
                    if (texteForce) { texteForce.textContent = 'Fort'; texteForce.className = 'small text-info fw-bold'; }
                } else {
                    barForce.classList.add('bg-success');
                    if (texteForce) { texteForce.textContent = 'Très robuste'; texteForce.className = 'small text-success fw-bold'; }
                }
            }

            var toutValide = hasLen && hasMin && hasMaj && hasNum && hasSpe && doesMatch;
            if (btnSoumettre) {
                btnSoumettre.disabled = !toutValide;
            }

            if (btnCopier) {
                btnCopier.disabled = val.length === 0;
            }
        }

        inputNouveau.addEventListener('input', evaluerForce);
        if (inputConfirm) {
            inputConfirm.addEventListener('input', evaluerForce);
        }

        if (btnGenerer) {
            btnGenerer.addEventListener('click', function (e) {
                e.preventDefault();
                var nouveauMdp = genererMotDePasseFort();
                inputNouveau.value = nouveauMdp;
                if (inputConfirm) {
                    inputConfirm.value = nouveauMdp;
                }
                // Afficher temporairement en clair pour visibilité de l'utilisateur
                inputNouveau.type = 'text';
                if (inputConfirm) {
                    inputConfirm.type = 'text';
                }
                var toggles = document.querySelectorAll('[data-toggle-password]');
                toggles.forEach(function (btn) {
                    var ic = btn.querySelector('i');
                    if (ic) {
                        ic.className = 'fa-solid fa-eye-slash';
                    }
                });

                evaluerForce();

                if (btnCopier) {
                    btnCopier.focus();
                }
            });
        }

        if (btnCopier) {
            btnCopier.addEventListener('click', function (e) {
                e.preventDefault();
                var val = inputNouveau.value;
                if (!val) { return; }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(val).then(confirmerCopie).catch(function () {
                        copierFallback(val);
                    });
                } else {
                    copierFallback(val);
                }
            });
        }

        function copierFallback(val) {
            var temp = document.createElement('textarea');
            temp.value = val;
            temp.style.position = 'fixed';
            temp.style.opacity = '0';
            document.body.appendChild(temp);
            temp.select();
            try {
                document.execCommand('copy');
                confirmerCopie();
            } finally {
                document.body.removeChild(temp);
            }
        }

        function confirmerCopie() {
            if (!btnCopier) { return; }
            var htmlOrig = btnCopier.innerHTML;
            btnCopier.innerHTML = '<i class="fa-solid fa-check text-success me-1"></i>Copié !';
            setTimeout(function () {
                btnCopier.innerHTML = htmlOrig;
            }, 2500);
        }

        // Bascule afficher / masquer les mots de passe
        document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var targetId = btn.getAttribute('data-toggle-password');
                var targetInput = document.getElementById(targetId);
                if (!targetInput) { return; }

                var estMasque = targetInput.type === 'password';
                targetInput.type = estMasque ? 'text' : 'password';

                var icone = btn.querySelector('i');
                if (icone) {
                    icone.className = estMasque ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
                }
            });
        });

        // Évaluation initiale
        evaluerForce();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiser);
    } else {
        initialiser();
    }
})();

