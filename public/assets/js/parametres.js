/**
 * Page Paramètres.
 *
 * Deux sections portent une épreuve côté client, chacune dans son propre
 * conteneur. Le script s'organise donc en deux blocs indépendants, chacun
 *_No-op when its own markup is absent : la même feuille sert les deux sections
 * sans qu'un contrôleur doive savoir laquelle est affichée.
 *
 *  - « Messagerie » : le test d'envoi refl��te les valeurs saisies ;
 *  - « Sauvegardes » : l'épreuve du partage Samba, sur les mêmes principes.
 *
 * Le test porte toujours sur les valeurs **saisies** : il assemble le
 * formulaire lui-même plutôt que de se fier à des champs mémorisés. Une
 * configuration peut ainsi être validée avant d'être persistée.
 *
 * Aucun dialogue natif : les retours passent par la pile de toasts et par des
 * blocs `alert` Tabler.
 */
(function () {
    'use strict';

    // ------------------------------------------------------- Section Messagerie
    (function () {
        var bouton = document.getElementById('btn-test-envoi');
        var resultat = document.getElementById('resultat-test');

        if (!bouton || !resultat) { return; }

        var enCours = false;

        function afficher(type, message) {
            resultat.innerHTML = '';
            var alerte = document.createElement('div');
            alerte.className = 'alert alert-' + type;
            alerte.textContent = message;
            resultat.appendChild(alerte);
        }

        function relever(champ) {
            var noeud = document.getElementById(champ);
            return noeud ? noeud.value : '';
        }

        bouton.addEventListener('click', async function () {
            if (enCours) { return; }

            var destinataire = relever('test_destinataire');
            if (!destinataire) {
                afficher('warning', 'Renseignez d\'abord une adresse de test.');
                return;
            }

            enCours = true;
            bouton.disabled = true;
            afficher('info', 'Envoi en cours…');

            try {
                const reponse = await window.flotteoPost('/admin/parametres/messagerie/tester', {
                    test_destinataire: destinataire,
                    mail_transport: relever('mail_transport'),
                    smtp_host: relever('smtp_host'),
                    smtp_port: relever('smtp_port'),
                    smtp_chiffrement: relever('smtp_chiffrement'),
                    smtp_user: relever('smtp_user'),
                    smtp_password: relever('smtp_password'),
                    email_expediteur: relever('email_expediteur')
                });
                const corps = reponse.json || {};
                afficher(corps.success ? 'success' : 'danger',
                    corps.message || 'Réponse inattendue du serveur.');
            } catch (e) {
                afficher('danger', "Le serveur n'a pas répondu. Réessayez dans un instant.");
            } finally {
                enCours = false;
                bouton.disabled = false;
            }
        });
    })();

    // ------------------------------------------------------- Section Sauvegardes
    (function () {
        var bouton = document.getElementById('bouton-test-samba');
        if (!bouton) { return; }

        var url = bouton.getAttribute('data-url') || '';
        var enCours = false;

        /**
         * Zone de retour insérée sous le bouton.
         *
         * Elle est créée à la première épreuve plutôt que présente dans le
         * gabarit : un bloc vide réserve une ligne verticale pour rien, et
         * l'utilisateur ne voit que ce qui le concerne.
         */
        function zone() {
            var noeud = document.getElementById('resultat-test-samba');
            if (noeud) { return noeud; }

            noeud = document.createElement('div');
            noeud.id = 'resultat-test-samba';
            noeud.className = 'mt-3';
            bouton.parentNode.insertBefore(noeud, bouton.parentNode.nextSibling);
            return noeud;
        }

        function afficher(type, message) {
            var cible = zone();
            cible.innerHTML = '';
            var alerte = document.createElement('div');
            alerte.className = 'alert alert-' + type;
            alerte.textContent = message;
            cible.appendChild(alerte);
        }

        function relever(champ) {
            var noeud = document.getElementById(champ);
            return noeud ? noeud.value : '';
        }

        bouton.addEventListener('click', async function () {
            if (enCours) { return; }

            var hote = relever('samba_hote');
            var partage = relever('samba_partage');
            if (!hote || !partage) {
                afficher('warning', 'Renseignez au moins l\'hôte et le nom du partage.');
                return;
            }

            enCours = true;
            bouton.disabled = true;
            afficher('info', 'Contact du partage en cours…');

            try {
                const reponse = await window.flotteoPost(url, {
                    samba_hote: hote,
                    samba_partage: partage,
                    samba_repertoire: relever('samba_repertoire'),
                    samba_utilisateur: relever('samba_utilisateur'),
                    samba_mot_de_passe: relever('samba_mot_de_passe'),
                    samba_domaine: relever('samba_domaine')
                });
                const corps = reponse.json || {};
                afficher(corps.success ? 'success' : 'danger',
                    corps.message || 'Réponse inattendue du serveur.');
            } catch (e) {
                afficher('danger', "Le serveur n'a pas répondu. Réessayez dans un instant.");
            } finally {
                enCours = false;
                bouton.disabled = false;
            }
        });
    })();
})();