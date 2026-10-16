/**
 * Flotteo — Gestion dynamique du formulaire d'incident et des règles d'assurance.
 */
(function () {
    'use strict';

    function initialiserModuleIncidents() {
        var modale = document.getElementById('modal-incident');
        var formulaire = document.getElementById('formulaire-incident');
        if (!formulaire) { return; }

        var champVehicule = document.getElementById('i_v_vehicule');
        var champEntite = document.getElementById('i_v_entite');
        var champStatut = document.getElementById('i_v_statut');
        var champAssuranceStatut = document.getElementById('i_v_assurance_statut');
        var champRembAttendu = document.getElementById('i_v_remb_attendu');
        var champMontantRemb = document.getElementById('i_v_montant_rembourse');
        var champMontantFran = document.getElementById('i_v_montant_franchise');
        var champRembPercu = document.getElementById('i_v_remb_percu');
        var etoileMontantRemb = document.getElementById('etoile_montant_rembourse');
        var blocDetails = document.getElementById('bloc-assurance-details');
        var alerteCloture = document.getElementById('alerte-cloture-remboursement');
        var alerteMontantPercu = document.getElementById('alerte-montant-percu-requis');
        var btnEnregistrer = document.getElementById('btn-enregistrer-incident');

        /**
         * Lit la valeur numérique du montant perçu de l'assurance.
         */
        function getMontantPercu() {
            if (!champMontantRemb || champMontantRemb.value === '') {
                return null;
            }
            var v = parseFloat(champMontantRemb.value.replace(',', '.'));
            return isNaN(v) ? null : v;
        }

        /**
         * Actualise l'affichage conditionnel des champs de l'assurance.
         */
        function actualiserAffichageAssurance() {
            var estPrisEnCharge = champAssuranceStatut && champAssuranceStatut.value === 'prise_en_charge';
            var estPercu = champRembPercu && champRembPercu.checked;
            var estAttendu = champRembAttendu && champRembAttendu.checked;

            // Si un remboursement est coché (perçu ou attendu), l'incident est de facto pris en charge par l'assurance
            if ((estPercu || estAttendu) && champAssuranceStatut && champAssuranceStatut.value !== 'prise_en_charge') {
                champAssuranceStatut.value = 'prise_en_charge';
                estPrisEnCharge = true;
            }

            if (blocDetails) {
                blocDetails.style.display = (estPrisEnCharge || estPercu || estAttendu) ? 'block' : 'none';
            }

            // Étoile rouge indiquant le champ obligatoire quand perçu
            if (etoileMontantRemb) {
                etoileMontantRemb.style.display = estPercu ? 'inline' : 'none';
            }
        }

        /**
         * Détermine si le dossier fait l'objet d'un remboursement d'assurance attendu.
         */
        function faitObjetRemboursement() {
            if (!champAssuranceStatut || champAssuranceStatut.value !== 'prise_en_charge') {
                return false;
            }
            var attenduCoche = champRembAttendu && champRembAttendu.checked;
            var montant = getMontantPercu();
            return attenduCoche || (montant !== null && montant > 0);
        }

        /**
         * Vérifie si le statut sélectionné correspond à une clôture / résolution.
         */
        function estStatutCloture() {
            if (!champStatut) { return false; }
            var s = champStatut.value;
            return s === 'cloture' || s === 'clos' || s === 'resolu';
        }

        /**
         * Contrôle global des règles métier :
         * 1. Si "Remboursement perçu" est coché, le champ "Montant perçu de l'assurance" doit être saisi (> 0).
         * 2. Un incident NE PEUT PAS être clôturé si un remboursement est attendu et que "Remboursement perçu" n'est PAS coché.
         */
        function verifierValiditeFormulaire() {
            var rembPercu = champRembPercu && champRembPercu.checked;
            var montant = getMontantPercu();

            // 1. Contrôle du montant perçu
            var montantManquant = rembPercu && (montant === null || montant <= 0);

            if (alerteMontantPercu) {
                alerteMontantPercu.style.display = montantManquant ? 'block' : 'none';
            }
            if (champMontantRemb) {
                if (montantManquant) {
                    champMontantRemb.classList.add('is-invalid');
                } else {
                    champMontantRemb.classList.remove('is-invalid');
                }
            }

            // 2. Contrôle de clôture
            var enCloture = estStatutCloture();
            var rembAttendu = faitObjetRemboursement();
            var clotureBloquee = enCloture && rembAttendu && !rembPercu;

            if (alerteCloture) {
                alerteCloture.style.display = clotureBloquee ? 'block' : 'none';
            }

            var estBloque = montantManquant || clotureBloquee;

            if (btnEnregistrer) {
                if (estBloque) {
                    btnEnregistrer.classList.add('disabled');
                    btnEnregistrer.setAttribute('aria-disabled', 'true');
                    if (montantManquant) {
                        btnEnregistrer.title = "Veuillez saisir le montant perçu de l'assurance.";
                    } else {
                        btnEnregistrer.title = "Clôture impossible : un remboursement d'assurance est attendu et non perçu.";
                    }
                } else {
                    btnEnregistrer.classList.remove('disabled');
                    btnEnregistrer.removeAttribute('aria-disabled');
                    btnEnregistrer.removeAttribute('title');
                }
            }

            return {
                valide: !estBloque,
                montantManquant: montantManquant,
                clotureBloquee: clotureBloquee
            };
        }

        /**
         * Synchronise l'entité sélectionnée lors du choix d'un véhicule.
         */
        function synchroniserEntiteDepuisVehicule() {
            if (!champVehicule || !champEntite) { return; }
            var option = champVehicule.options[champVehicule.selectedIndex];
            if (!option) { return; }
            var entiteId = option.getAttribute('data-entite-id');
            if (entiteId && entiteId !== '0') {
                if (!champEntite.value) {
                    champEntite.value = entiteId;
                }
            }
        }

        // Écouteurs de changement
        if (champAssuranceStatut) {
            champAssuranceStatut.addEventListener('change', function () {
                if (champAssuranceStatut.value === 'non_prise_en_charge') {
                    if (champRembPercu) champRembPercu.checked = false;
                    if (champRembAttendu) champRembAttendu.checked = false;
                }
                actualiserAffichageAssurance();
                verifierValiditeFormulaire();
            });
        }

        if (champStatut) {
            champStatut.addEventListener('change', verifierValiditeFormulaire);
        }

        if (champRembAttendu) {
            champRembAttendu.addEventListener('change', function () {
                actualiserAffichageAssurance();
                verifierValiditeFormulaire();
            });
        }

        if (champMontantRemb) {
            champMontantRemb.addEventListener('input', verifierValiditeFormulaire);
            champMontantRemb.addEventListener('change', verifierValiditeFormulaire);
        }

        if (champRembPercu) {
            champRembPercu.addEventListener('change', function () {
                actualiserAffichageAssurance();
                verifierValiditeFormulaire();
            });
        }

        if (champVehicule) {
            champVehicule.addEventListener('change', synchroniserEntiteDepuisVehicule);
        }

        // Blocage strict à la soumission
        formulaire.addEventListener('submit', function (evenement) {
            var etat = verifierValiditeFormulaire();
            if (!etat.valide) {
                evenement.preventDefault();
                evenement.stopPropagation();

                if (etat.montantManquant) {
                    if (champMontantRemb) {
                        champMontantRemb.focus();
                        champMontantRemb.classList.add('is-invalid');
                    }
                    if (alerteMontantPercu) {
                        alerteMontantPercu.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    if (window.FLOTTEO && typeof window.FLOTTEO.toast === 'function') {
                        window.FLOTTEO.toast("Veuillez renseigner le montant perçu de l'assurance lorsque le remboursement est indiqué comme perçu.", 'danger');
                    } else {
                        alert("Veuillez renseigner le montant perçu de l'assurance lorsque la case « Remboursement perçu » est cochée.");
                    }
                    return false;
                }

                if (etat.clotureBloquee) {
                    if (alerteCloture) {
                        alerteCloture.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    if (window.FLOTTEO && typeof window.FLOTTEO.toast === 'function') {
                        window.FLOTTEO.toast("Impossible de clôturer l'incident : remboursement d'assurance non perçu.", 'danger');
                    } else {
                        alert("Impossible de clôturer l'incident : un remboursement d'assurance est attendu mais n'a pas encore été perçu.");
                    }
                    return false;
                }
            }
        });

        // Réinitialisation lors de l'ouverture en création
        if (modale) {
            modale.addEventListener('show.bs.modal', function (evenement) {
                var declencheur = evenement.relatedTarget;
                if (declencheur && !declencheur.hasAttribute('data-edition-incident')) {
                    var champId = document.getElementById('incident-id');
                    if (champId && champId.value === '0') {
                        actualiserAffichageAssurance();
                        verifierValiditeFormulaire();
                    }
                }
            });
        }

        // Export de la méthode de rafraîchissement global
        window.actualiserBlocAssurance = function () {
            actualiserAffichageAssurance();
            verifierValiditeFormulaire();
        };

        // Initialisation immédiate
        actualiserAffichageAssurance();
        verifierValiditeFormulaire();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiserModuleIncidents);
    } else {
        initialiserModuleIncidents();
    }
})();
