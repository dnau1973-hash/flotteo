/**
 * Agenda — calendrier FullCalendar.
 *
 * Alimentation par intervalle affiché : chaque navigation déclenche une requête
 * sur la plage réellement visible, que le serveur regroupe en un seul appel
 * (`/api/agenda/evenements`). Aucun événement n'est figé au chargement de la
 * page — un agenda figé serait faux dès la première navigation.
 *
 * Deux points d'appui de FullCalendar v7 sont utilisés volontairement :
 *
 *  - `events` en fonction, pour ne charger que la vue affichée ;
 *  - `eventDidMount`, seul moyen d'accrocher une classe **nommée**. Les noms de
 *    classes internes de la bibliothèque sont hachés et changent d'une version à
 *    l'autre : écrire `.fc-event-title` dans `flotteo.css` n'aurait rien donné.
 *    Les classes `flotteo-evenement*` posées ici nous appartiennent et restent
 *    stables.
 */
(function () {
    'use strict';

    var el = document.getElementById('agenda-calendrier');
    if (!el || typeof FullCalendar === 'undefined' || !FullCalendar.Calendar) {
        return;
    }

    var conf = window.FLOTTEO_AGENDA || {};

    // Familles encore affichées, lues sur les boutons de l'en-tête de carte.
    //
    // L'état est porté par `aria-pressed` et non par une classe : le rendu de
    // FullCalendar réécrit le contenu des jours, et une classe posée par le
    // script risquerait d'être perdue au premier changement de vue.
    function typesActifs() {
        return Array.prototype.slice
            .call(document.querySelectorAll('.agenda-filtre'))
            .filter(function (bouton) {
                return bouton.getAttribute('aria-pressed') === 'true';
            })
            .map(function (bouton) {
                return bouton.getAttribute('data-type');
            });
    }

    /**
     * Recale les compteurs des cartes et des boutons sur la plage affichée.
     *
     * Une plage changeant au bouton précédent/suivant, des compteurs figés au
     * chargement de la page Becquerraient faux : c'est la réponse du serveur qui
     * fait foi.
     */
    function majCompte(compte) {
        if (!compte) {
            return;
        }

        var total = 0;

        Object.keys(compte).forEach(function (type) {
            total += Number(compte[type]) || 0;

            var carte = document.getElementById('agenda-compte-' + type);
            if (carte) {
                carte.textContent = String(compte[type]);
            }

            var badge = document.querySelector('[data-compte="' + type + '"]');
            if (badge) {
                badge.textContent = String(compte[type]);
            }
        });

        var blocTotal = document.getElementById('agenda-compte-total');
        if (blocTotal) {
            blocTotal.textContent = String(total);
        }

        // Le message « aucun événement » et le calendrier ne doivent pas se
        // contredire. Le calendrier reste affiché même vide : les flèches de
        // navigation doivent rester utilisables.
        var conteneur = document.getElementById('agenda-conteneur');
        var vide = document.querySelector('.agenda-vide');
        if (conteneur && vide) {
            conteneur.classList.toggle('d-none', total !== 0);
            vide.classList.toggle('d-none', total !== 0);
        }
    }

    var calendrier = new FullCalendar.Calendar(el, {
        locale: 'fr',
        initialView: 'dayGridMonth',
        initialDate: conf.jour || undefined,
        firstDay: 1,
        height: 'auto',
        fixedWeekCount: false,
        dayMaxEvents: 4,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek,listWeek'
        },
        // Libellés français. En v7 ce sont des options **plates** : l'objet passé
        // à `locale` n'est plus lu, et les boutons restaient « Today / Month /
        // Week / List ». Deux nuances, trouvées sur la page servie :
        //  - le libellé du bouton « semaine » se règle par `weekTextLong` ;
        //  - `weekText` sert au *numéro* de semaine, pas au bouton — d'où
        //    `S{numero}`, convention française.
        todayText: "Aujourd'hui",
        todayTextLong: "Aujourd'hui",
        monthText: 'Mois',
        monthTextLong: 'Mois',
        weekTextLong: 'Semaine',
        dayText: 'Jour',
        dayTextLong: 'Jour',
        listText: 'Liste',
        weekText: 'S{numero}',
        allDayText: 'Toute la journée',
        moreLinkText: 'de plus',
        noEventsText: 'Aucun événement sur cette période',
        events: function (info, succes, echec) {
            var params = new URLSearchParams();
            params.set('du', String(info.startStr).slice(0, 10));
            params.set('au', String(info.endStr).slice(0, 10));

            // Sans paramètre, le serveur renvoie les trois familles : inutile
            // d'énumérer celles qui sont toutes cochées.
            var retenus = typesActifs();
            var toutes = retenus.length === document.querySelectorAll('.agenda-filtre').length;
            if (retenus.length && !toutes) {
                params.set('types', retenus.join(','));
            }

            window.flotteoGet('/api/agenda/evenements?' + params.toString())
                .then(function (reponse) {
                    if (!reponse || reponse.success !== true) {
                        throw new Error((reponse && reponse.message) || 'Réponse inattendue');
                    }

                    majCompte(reponse.meta && reponse.meta.compte);
                    succes(reponse.data || []);
                })
                .catch(function (erreur) {
                    echec(erreur);
                    window.flotteoToast(
                        'Le calendrier n\'a pas pu être chargé. Réessayez dans un instant.',
                        'danger'
                    );
                });
        },
        eventDidMount: function (info) {
            var props = info.event.extendedProps || {};

            // Le serveur pose l'URL de la fiche véhicule : FullCalendar rend
            // alors l'événement en lien, ce qui conserve le ctrl-clic, le
            // nouvel onglet et le menu contextuel du navigateur.
            if (info.event.url) {
                info.el.setAttribute('title', (props.detail || info.event.title) + ' — ouvrir la fiche');
            }
        }
    });

    calendrier.render();

    // Filtres de famille : le filtrage est refait par le serveur sur la plage
    // affichée, et non en Javascript — les compteurs et le calendrier ne
    // pourraient alors plus diverger.
    Array.prototype.forEach.call(document.querySelectorAll('.agenda-filtre'), function (bouton) {
        bouton.addEventListener('click', function (evenement) {
            evenement.preventDefault();

            var actif = bouton.getAttribute('aria-pressed') === 'true';

            // Masquer la dernière famille active viderait le calendrier sans
            // laisser le moyen de le remplir à nouveau.
            if (actif && typesActifs().length <= 1) {
                return;
            }

            bouton.setAttribute('aria-pressed', actif ? 'false' : 'true');
            calendrier.refetchEvents();
        });
    });
})();