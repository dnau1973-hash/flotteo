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

    // ---------------------------------------------- 1. Échéancier des sorties
    function graphiqueEcheancier() {
        if (!document.getElementById('graph-echeancier')) { return; }
        window.flotteoGet('/api/dashboard/echeancier').then(function (payload) {
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
        });
    }

    // ----------------------------------------- 2. Répartition de la flotte
    var DIMENSIONS_REPARTITION = {
        entite: { url: '/api/dashboard/repartition?dimension=entite', titre: 'Répartition par entité propriétaire' },
        loueur: { url: '/api/dashboard/repartition?dimension=loueur', titre: 'Exposition par organisme loueur' },
        lieu: { url: '/api/dashboard/repartition?dimension=lieu', titre: 'Affectation par lieu d\'exploitation' }
    };
    var repartitionCourante = 'entite';

    function graphiqueRepartition(dimension) {
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

        window.flotteoGet(conf.url).then(function (payload) {
            var d = (payload && payload.data) || { etiquettes: [], series: [] };
            if (window.__repartition) { window.__repartition.destroy(); }
            window.__repartition = creer('graph-repartition', Object.assign(optionsAvatar(), {
                series: d.series,
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
        });
    }

    // ------------------------------------ 3. Évolution des coûts d'entretien
    function graphiqueEntretien() {
        if (!document.getElementById('graph-entretien')) { return; }
        window.flotteoGet('/api/dashboard/entretien').then(function (payload) {
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
        });
    }

    // ------------------------------------------- 4. TCO moyen par modèle
    function graphiqueTco() {
        if (!document.getElementById('graph-tco')) { return; }
        window.flotteoGet('/api/dashboard/tco-modeles').then(function (payload) {
            var d = (payload && payload.data) || { etiquettes: [], series: [] };
            creer('graph-tco', Object.assign(optionsAvatar(), {
                series: d.series,
                chart: { type: 'bar', fontFamily: 'inherit', toolbar: { show: false }, height: 340 },
                colors: [PALETTE[1]],
                plotOptions: { bar: { horizontal: true, barHeight: '60%', borderRadius: 3, distributed: true } },
                xaxis: axeYAxis(),
                yaxis: axeXAxis({ categories: d.etiquettes }),
                legend: { show: false },
                tooltip: { y: { formatter: function (v) { return euro(v) + ' par véhicule'; } } }
            })).render();
        });
    }

    // -------------------------------- 5. Matrice des incidents / sinistralité
    function graphiqueIncidents() {
        if (!document.getElementById('graph-incidents')) { return; }
        window.flotteoGet('/api/dashboard/incidents').then(function (payload) {
            var d = (payload && payload.data) || { par_type: [], type_labels: [], mois: [], tendance: [] };

            creer('graph-incidents', Object.assign(optionsAvatar(), {
                series: [{ name: 'Incidents', data: d.tendance }],
                chart: { type: 'area', fontFamily: 'inherit', toolbar: { show: false }, height: 260 },
                colors: [PALETTE[3]],
                stroke: { curve: 'smooth', width: 2.5 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
                markers: { size: 3 },
                xaxis: axeXAxis({ categories: d.mois }),
                yaxis: axeYAxis({ labels: { style: { colors: GRIS, fontSize: '11px' }, formatter: function (v) { return Math.round(v); } } }),
                tooltip: { y: { formatter: function (v) { return v + ' incident(s)'; } } }
            })).render();

            creer('graph-incidents-types', Object.assign(optionsAvatar(), {
                series: d.par_type,
                chart: { type: 'radar', fontFamily: 'inherit', toolbar: { show: false }, height: 260 },
                colors: [PALETTE[0]],
                labels: d.type_labels,
                stroke: { width: 2 },
                fill: { opacity: 0.25 },
                markers: { size: 4 },
                yaxis: { show: false },
                legend: { show: false },
                tooltip: { y: { formatter: function (v) { return v + ' incident(s)'; } } }
            })).render();
        });
    }

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
