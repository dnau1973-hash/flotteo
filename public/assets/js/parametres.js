/**
 * Page Paramètres — section « Messagerie ».
 *
 * Deux responsabilités : refléter le mode d'envoi choisi sur le bloc des réglages
 * SMTP, et déclencher le test d'envoi sans recharger la page.
 *
 * Le test porte sur les valeurs actuellement saisies : il assemble donc le
 * formulaire lui-même plutôt que de s'appuyer sur des champs mémorisés.
 */
(function () {
    'use strict';

    var bouton = document.getElementById('btn-test-envoi');
    var resultat = document.getElementById('resultat-test');

    if (!bouton || !resultat) { return; }

    // --- Test d'envoi -------------------------------------------------------
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

    bouton.addEventListener('click', function () {
        if (enCours) { return; }

        var destinataire = relever('test_destinataire');
        if (!destinataire) {
            afficher('warning', 'Renseignez d\'abord une adresse de test.');
            return;
        }

        enCours = true;
        bouton.disabled = true;
        afficher('info', 'Envoi en cours…');

        window.flotteoPost('/admin/parametres/messagerie/tester', {
            test_destinataire: destinataire,
            mail_transport: relever('mail_transport'),
            smtp_host: relever('smtp_host'),
            smtp_port: relever('smtp_port'),
            smtp_chiffrement: relever('smtp_chiffrement'),
            smtp_user: relever('smtp_user'),
            smtp_password: relever('smtp_password'),
            email_expediteur: relever('email_expediteur')
        }).then(function (reponse) {
            var corps = reponse.json || {};
            afficher(corps.success ? 'success' : 'danger',
                corps.message || 'Réponse inattendue du serveur.');
        }).catch(function () {
            afficher('danger', 'Le serveur n\'a pas répondu. Réessayez dans un instant.');
        }).then(function () {
            enCours = false;
            bouton.disabled = false;
        });
    });
})();