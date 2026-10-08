/**
 * Flotteo — Graphiques du tableau de bord (ApexCharts, JavaScript Vanilla).
 * Données : endpoints JSON légers servis par ApiController.
 */
(function () {
    'use strict';

    var PALETTE = ['#206bc4', '#2fb344', '#f76707', '#d63939', '#7048e8', '#12b5cb', '#e8a33d', '#5b8c9e'];
    var GRIS = '#98a2b3';
    var euro = window.flotteoEuro || function (v) { return v; };

    /**_options communes à tous les graphiques (lisibilité Tabler). */
    function optionsAvatar() {
        return {
            chart: { fontFamily: 'inherit', toolbar: { show: false }, animations: { speed: 350 } },
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontSize: '12px', markers: { size: 6 } },
            tooltip: { theme: 'light' },
            grid: { borderColor: '#e6e7e9', strokeDashArray: 3 }
        };
    }

    function axeYAxis(extra) {
        return Object.assign({
            labels: {
                style: { colors: GRIS, fontSize: '11px' },
                formatter: function (v) { return euro(v); }
            }
        }, extra || {});
    }

    function axeXAxis(extra) {
        return Object.assign({
            labels: { style: { colors: GRIS, fontSize: '11px' }, rotate: 0, hideOverlappingLabels: true }
        }, extra || {});
    }

    function creer(id, config) {
        var element = document.getElementById(id);
        if (element) { return new ApexCharts(element, config); }
        return null;
    }

    function apiUrl(path) {
        var params = new URLSearchParams(window.location.search);
        var entite = params.get('entite');
        if (entite) {
            var sep = path.indexOf('?') !== -1 ? '&' : '?';
            return path + sep + 'entite=' + encodeURIComponent(entite);
        }
        return path;
    }

    // ---------------------------------------------- 1. Échéancier des sorties
    async function graphiqueEcheancier() {
        if (!document.getElementById('graph-echeancier')) { return; }
        try {
            const payload = await window.flotteoGet(apiUrl('/api/dashboard/echeancier'));
            var d = (payload && payload.data) || { etiquettes: [], series: [] };
            creer('graph-echeancier', Object.assign(optionsAvatar(), {
                series: d.series,
                chart: { type: 'bar', fontFamily: 'inherit', toolbar: { show: false }, height: 300 },
                colors: [PALETTE[0]],
                plotOptions: { bar: { columnWidth: '55%', borderRadius: 4, horizontal: false } },
                xaxis: axeXAxis({ categories: d.etiquettes }),
                yaxis: axeYAxis({ labels: { style: { colors: GRIS, fontSize: '11px' }, formatter: function (v) { return Math.round(v); } } }),
                tooltip: { y: { formatter: function (v) { return v + ' véhicule(s) à restituer'; } } }
            })).render();
        } catch(e) {} 
    }

    // ----------------------------------------- 2. Répartition de la flotte
    var DIMENSIONS_REPARTITION = {
        entite: { url: '/api/dashboard/repartition?dimension=entite', titre: 'Répartition par entité propriétaire' },
        loueur: { url: '/api/dashboard/repartition?dimension=loueur', titre: 'Exposition par organisme loueur' },
        lieu: { url: '/api/dashboard/repartition?dimension=lieu', titre: 'Affectation par lieu d\'exploitation' }
    };
    var repartitionCourante = 'entite';

    async function graphiqueRepartition(dimension) {
        if (!document.getElementById('graph-repartition')) { return; }
        repartitionCourante = dimension || repartitionCourante;
        var conf = DIMENSIONS_REPARTITION[repartitionCourante];

        document.querySelectorAll('[data-dimension]').forEach(function (onglet) {
            var actif = onglet.getAttribute('data-dimension') === repartitionCourante;
            onglet.classList.toggle('active', actif);
            onglet.setAttribute('aria-selected', actif ? 'true' : 'false');
        });
        var titre = document.getElementById('titre-repartition');
        if (titre) { titre.textContent = conf.titre; }

        try {
            const payload = await window.flotteoGet(apiUrl(conf.url));
            var d = (payload && payload.data) || { etiquettes: [], series: [] };
            if (window.__repartition) {
                window.__repartition.destroy();
                window.__repartition = null;
            }
            if (!(d.etiquettes || []).length) {
                etatVide('graph-repartition', 'Aucun véhicule à affecter sur cette dimension.');
                return;
            }
            /*
             * Un anneau attend un tableau de nombres, pas `[{ name, data }]`.
             *
             * `fleetSplit` renvoie la même forme `series` que les cinq autres
             * endpoints du tableau de bord, forme faite pour les graphiques à
             * axes : `Pie.draw` additionne les éléments de `series` comme des
             * nombres, si bien qu'un objet unique produisait `"0[object Object]"`
             * comme total, des angles `NaN`, et un anneau vide — sans lever la
             * moindre erreur. La légende, elle, alimentée par `labels`, restait
             * correcte : la carte semblait à moitié vivante.
             *
             * La conversion est faite ici plutôt qu'à l'API pour que ce point
             * d'entrée continue de partager le contrat `[{ name, data }]` des
             * autres, seul consommé par ce graphique.
             */
            var valeurs = (d.series && d.series[0] && d.series[0].data) || [];
            window.__repartition = creer('graph-repartition', Object.assign(optionsAvatar(), {
                series: valeurs,
                chart: { type: 'donut', fontFamily: 'inherit', toolbar: { show: false } },
                colors: PALETTE,
                labels: d.etiquettes,
                stroke: { width: 2, colors: ['#fff'] },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                name: { fontSize: '13px' },
                                value: {
                                    fontSize: '24px', fontWeight: 600,
                                    formatter: function (v) { return v + ' (' + Math.round((v / (d.total || 1)) * 100) + '%)'; }
                                },
                                total: {
                                    show: true, label: 'Total véhicules',
                                    fontSize: '13px',
                                    formatter: function () { return String(d.total || 0); }
                                }
                            }
                        }
                    }
                },
                legend: { position: 'right', fontSize: '12px', markers: { size: 7 } },
                tooltip: { y: { formatter: function (v) { return v + ' véhicule(s)'; } } }
            }));
            if (window.__repartition) { window.__repartition.render(); }
        } catch(e) {} }

    // ------------------------------------ 3. Évolution des coûts d'entretien
    async function graphiqueEntretien() {
        if (!document.getElementById('graph-entretien')) { return; }
        try {
            const payload = await window.flotteoGet(apiUrl('/api/dashboard/entretien'));
            var d = (payload && payload.data) || { etiquettes: [], series: [] };
            creer('graph-entretien', Object.assign(optionsAvatar(), {
                series: d.series,
                chart: { type: 'bar', stacked: true, fontFamily: 'inherit', toolbar: { show: false }, height: 320 },
                colors: PALETTE,
                plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } },
                xaxis: axeXAxis({ categories: d.etiquettes }),
                yaxis: axeYAxis(),
                tooltip: { y: { formatter: function (v) { return euro(v); } } }
            })).render();
        } catch(e) {} }

    // ------------------------------------------- 4. TCO moyen par modèle
    async function graphiqueTco() {
        if (!document.getElementById('graph-tco')) { return; }
        try {
            const payload = await window.flotteoGet(apiUrl('/api/dashboard/tco-modeles'));
            var d = (payload && payload.data) || { etiquettes: [], series: [] };
            if (!(d.etiquettes || []).length) {
                etatVide('graph-tco', 'Aucune prestation d\'entretien enregistrée sur la période.');
                return;
            }
            /*
             * Les catégories vont sur `xaxis`, et non sur `yaxis`.
             *
             * Sur une barre horizontale, ApexCharts lit `xaxis.categories` pour
             * les libellés de l'axe des catégories — `drawXaxisInversed` prend ses
             * textes dans `globals.labels`, lui-même alimenté par `xaxis.categories`.
             * `yaxis[*].categories` n'existe pas dans ApexCharts 3.54 : la clé est
             * acceptée puis ignorée, sans erreur ni avertissement, et l'axe affiche
             * alors sa numérotation automatique (1, 2, 3…). Les montants et la
             * hauteur des barres étaient justes, seule l'identification des modèles
             * manquait — d'où des chiffres sans libellé.
             *
             * `xaxis` porte donc ici le formateur en euros, `yaxis` seulement le
             * style : le formateur de l'axe des valeurs est bien `xaxis` pour une
             * barre horizontale, il ne doit pas être déplacé.
             */
            creer('graph-tco', Object.assign(optionsAvatar(), {
                series: d.series,
                chart: { type: 'bar', fontFamily: 'inherit', toolbar: { show: false }, height: 340 },
                colors: PALETTE,
                plotOptions: { bar: { horizontal: true, barHeight: '60%', borderRadius: 3, distributed: true } },
                xaxis: Object.assign(axeYAxis(), { categories: d.etiquettes }),
                yaxis: axeXAxis(),
                legend: { show: false },
                tooltip: { y: { formatter: function (v) { return euro(v) + ' par véhicule'; } } }
            })).render();
        } catch(e) {} }

    // -------------------------------- 5. Matrice des incidents / sinistralité
    /**
     * Remplace un conteneur de graphique par un état vide lisible.
     *
     * ApexCharts ne signale pas l'absence de données : il dessine des axes vides.
     * Le message est donc posé dans le conteneur, à la place du graphique, pour
     * que la carte reste compréhensible sur une flotte sans incident.
     */
    function etatVide(id, message) {
        var element = document.getElementById(id);
        if (!element) { return; }
        element.innerHTML = '';
        var bloc = document.createElement('div');
        bloc.className = 'empty-state';
        bloc.innerHTML = '<i class="fa-solid fa-circle-info icon" aria-hidden="true"></i>'
            + '<div>' + message + '</div>';
        element.appendChild(bloc);
    }

    /**
     * Le radar exige des séries structurées : `[{ name, data: [...] }]`.
     * L'endpoint renvoie deux tableaux plats — `par_type` (totaux) et
     * `type_labels` (libellés) — seule forme utile au graphique. Les injecter
     * tels quels dans `series` produisait `series: [3, 2, 1]` : ApexCharts y
     * lit `series[i].data`, soit `undefined`, et la carte restait vide sans
     * aucune erreur console.
     */
    function graphiqueIncidentsParType(d) {
        var totaux = d.par_type || [];
        var libelles = d.type_labels || [];
        if (!totaux.length) {
            etatVide('graph-incidents-types', 'Aucun incident enregistré sur la période.');
            return;
        }

        creer('graph-incidents-types', Object.assign(optionsAvatar(), {
            series: [{ name: 'Incidents', data: totaux }],
            chart: { type: 'radar', fontFamily: 'inherit', toolbar: { show: false }, height: 400 },
            colors: [PALETTE[0]],
            labels: libelles,
            stroke: { width: 2 },
            fill: { opacity: 0.25 },
            markers: { size: 4 },
            yaxis: { show: false },
            legend: { show: false },
            tooltip: { y: { formatter: function (v) { return v + ' incident(s)'; } } }
        })).render();
    }

    async function graphiqueIncidents() {
        // Chaque graphique se garde sa propre garde : l'un des deux pouvait
        // manquer du gabarit sans que l'autre s'en aperçoive.
        if (!document.getElementById('graph-incidents') && !document.getElementById('graph-incidents-types')) { return; }

        try {
            const payload = await window.flotteoGet(apiUrl('/api/dashboard/incidents'));
            var d = (payload && payload.data) || { par_type: [], type_labels: [], mois: [], tendance: [] };

            if (document.getElementById('graph-incidents')) {
                if (!(d.tendance || []).length) {
                    etatVide('graph-incidents', 'Aucune tendance sur les douze derniers mois.');
                } else {
                    creer('graph-incidents', Object.assign(optionsAvatar(), {
                        series: [{ name: 'Incidents', data: d.tendance }],
                        chart: { type: 'area', fontFamily: 'inherit', toolbar: { show: false }, height: 400 },
                        colors: [PALETTE[3]],
                        stroke: { curve: 'smooth', width: 2.5 },
                        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
                        markers: { size: 3 },
                        xaxis: axeXAxis({ categories: d.mois }),
                        yaxis: axeYAxis({ labels: { style: { colors: GRIS, fontSize: '11px' }, formatter: function (v) { return Math.round(v); } } }),
                        tooltip: { y: { formatter: function (v) { return v + ' incident(s)'; } } }
                    })).render();
                }
            }

            graphiqueIncidentsParType(d);
        } catch(e) {} }

    document.addEventListener('DOMContentLoaded', function () {
        graphiqueEcheancier();
        graphiqueRepartition(repartitionCourante);
        graphiqueEntretien();
        graphiqueTco();
        graphiqueIncidents();

        document.querySelectorAll('[data-dimension]').forEach(function (onglet) {
            onglet.addEventListener('click', function (evenement) {
                evenement.preventDefault();
                graphiqueRepartition(onglet.getAttribute('data-dimension'));
            });
        });
    });
})();
