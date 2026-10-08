# Registre des fonctionnalités — Flotteo

Ce registre inventorie les capacités **actives** et **planifiées** de l'application.
Version de référence : **1.0.1** — synchronisé avec `features.html`.

## Légende

| Marque | Signification |
| :--- | :--- |
| ✅ | Capacité active en version courante |
| 🔄 | Capacité planifiée |
| ⛔ | Hors périmètre (écart assumé vis-à-vis du cahier des charges) |

---

## 1. Authentification & sécurité

| Fonctionnalité | État | Détail d'implémentation |
| :--- | :---: | :--- |
| Écran de connexion | ✅ | `views/auth/login.php`, email + mot de passe, option d'affichage du secret |
| Hachage des mots de passe | ✅ | `password_hash(..., PASSWORD_DEFAULT)`, rehash automatique à la connexion |
| Session durcie | ✅ | `httponly`, `samesite=Strict`, `secure` sous HTTPS, `session_regenerate_id(true)` |
| Rôle *Lecture Seule* | ✅ | Consultation dashboards, fiches, plannings, coûts, incidents |
| Rôle *Modification* | ✅ | Écriture véhicules, révisions, incidents, téléversements |
| Rôle *Administration* | ✅ | Utilisateurs, tables de paramétrage, configuration système |
| Protection CSRF | ✅ | Jeton de session vérifié sur 100 % des actions `POST` (`core/Csrf.php`) |
| Échappement des sorties | ✅ | `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` sur toute variable de vue |
| Journalisation serveur | ✅ | `storage/logs/app-AAAA-MM.log`, jamais exposée à l'écran |
| Authentification à deux facteurs | 🔄 | Non prévu au cahier des charges |
| SSO / LDAP | 🔄 | Non prévu au cahier des charges |

## 2. Tableau de bord & pilotage visuel

| Fonctionnalité | État | Détail d'implémentation |
| :--- | :---: | :--- |
| Carte KPI — Flotte active | ✅ | `Models\Vehicle::stats()`, total et détail actifs / sortis |
| Carte KPI — Taux d'immobilisation | ✅ | Pourcentage + jauge, véhicules en atelier ou accidentés |
| Carte KPI — Alertes d'échéance | ✅ | Badge de comptage des sorties < 90 jours |
| Carte KPI — Coût d'entretien | ✅ | Cumul mensuel et annuel (HT) |
| 1. Échéancier des sorties sur 12 mois | ✅ | Barres mensuelles — `api/dashboard/echeancier` |
| 2a. Répartition par entité propriétaire | ✅ | Donut filtrable — onglet *Entités* |
| 2b. Répartition par organisme loueur | ✅ | Donut filtrable — onglet *Loueurs* |
| 2c. Répartition par lieu d'exploitation | ✅ | Donut filtrable — onglet *Lieux* |
| 3. Évolution des dépenses d'entretien | ✅ | Histogramme empilé par typologie sur 12 mois |
| 4. TCO moyen par marque / modèle | ✅ | Barres horizontales, 12 modèles les plus coûteux |
| 5. Matrice des incidents | ✅ | Radar de répartition + courbe de tendance annuelle, **400 px de haut** (`.chart-incidents`) pour rester lisible face à la courbe voisine ; la hauteur n'est plus figée à 260 px dans `dashboard.js` |
| Listes d'action rapide | ✅ | Sorties les plus proches, véhicules immobilisés |
| Export PDF / Excel du dashboard | 🔄 | Non prévu au cahier des charges |
| Comparaison inter-périodes | 🔄 | Non prévu au cahier des charges |

## 3. Gestion de flotte

| Fonctionnalité | État | Détail d'implémentation |
| :--- | :---: | :--- |
| Identifiant interne unique | ✅ | Clé primaire auto-incrémentée |
| Immatriculation | ✅ | Unique, normalisée en majuscules |
| Marque & modèle liés à la nomenclature | ✅ | Dictionnaires `marques` / `modeles` |
| Entité propriétaire | ✅ | Multi-sociétés, ajout / suppression |
| Organisme loueur | ✅ | Partenaires externes, ajout / suppression |
| Lieu d'exploitation | ✅ | Sites, agences, dépôts |
| Date d'entrée en flotte | ✅ | Contrôlée par le formulaire |
| Date de sortie prévisionnelle | ✅ | Contrôlée, postérieure à l'entrée |
| Date de sortie effective | ✅ | Renseigne la clôture de détention |
| Statut d'exploitation | ✅ | Actif / Immobilisé / Sorti |
| Jauge de détention | ✅ | Avancement visualisé sur la durée totale du contrat |
| Recherche et filtres combinés | ✅ | Recherche plein texte + 4 filtres + échéance |
| Historique consolidé sur la fiche | ✅ | Révisions et incidents rattachés au véhicule |
| Harmonisation de la fiche véhicule | ✅ | Les 3 cartes partagent la même mécanique de hauteur : colonne gauche en `d-flex`, cartes en `h-100 … d-flex flex-column`, régions extensibles en `flex-fill` (corps de la fiche, enveloppes `table-responsive`). La fiche n'est plus plus courte que la colonne droite |

## 4. Suivi du cycle de vie, entretien & incidents

| Fonctionnalité | État | Détail d'implémentation |
| :--- | :---: | :--- |
| Types de prestation paramétrables | ✅ | Constructeur, pneumatique, freinage, contrôle, carrosserie, autre |
| Horodatage précis des révisions | ✅ | Date d'opération + kilométrage |
| Coût financier HT / TTC | ✅ | Saisie manuelle, TTC pré-calculé à 20 % |
| Cumuls par véhicule et par période | ✅ | Pied de tableau et carte KPI |
| Saisie d'incident / sinistre | ✅ | Accident, panne, vandalisme, bris de glace, autre |
| Date, lieu et descriptif | ✅ | Champs dédiés sur la fiche d'incident |
| Pièces jointes PDF | ✅ | Factures, constats amiables |
| Pièces jointes photographiques | ✅ | JPG, PNG, WEBP |
| Nommage unique et contrôle MIME | ✅ | `core/Upload.php`, détection `finfo` réelle |
| Téléchargement contrôlé | ✅ | Flux PHP, nom d'origine assaini, `nosniff` |
| Vignette des pièces jointes image | ✅ | Aperçu 56 px, clic pour agrandir, PDF en icône |
| Aperçu servi hors stockage public | ✅ | `/incidents/fichier/apercu`, type relu par `finfo` |
| Suivi de la responsabilité | ✅ | Case responsable / immatriculé |
| Circuit de statut du dossier | ✅ | Ouvert → En traitement → Clôturé |
| Coût de sinistre chiffré | 🔄 | Non prévu au cahier des charges |
| Chronologie photographique annotée | 🔄 | Non prévu au cahier des charges |

## 5. Alertes & notifications

| Fonctionnalité | État | Détail d'implémentation |
| :--- | :---: | :--- |
| Calcul du temps restant | ✅ | `Models\Vehicle::joursRestants()` |
| Seuil d'anticipation paramétrable | ✅ | 3 / 6 / 9 mois, pilotés en administration |
| Mail condensé récapitulatif | ✅ | Tableau HTML groupé par palier, `core/Mailer.php` |
| Tâche cron CLI | ✅ | `scripts/alert_cron.php`, code de sortie exploitable |
| Déclenchement manuel | ✅ | Bouton dédié dans les paramètres système |
| Aperçu avant envoi | ✅ | Échéancier affiché dans l'interface d'administration |
| Notifications SMS / Teams | 🔄 | Non prévu au cahier des charges |

## 6. Administration & paramétrage

| Fonctionnalité | État | Détail d'implémentation |
| :--- | :---: | :--- |
| CRUD utilisateurs | ✅ | Création, lecture, modification, suppression — liste en grille de cartes de profil Tabler (`row row-cards`, `col-md-6 col-lg-4`) avec bandeau de teinte par rôle, avatar, badge de rôle et état |
| Avatar de profil | ✅ | Dépôt JPG / PNG / WEBP de 8 Mo maximum à la création et à l'édition ; `Core\Upload::store()` n'accepte qu'un sous-ensemble de `securite.mime_autorises`, donc la configuration générale ne peut pas être élargie. Stockage dans `public/uploads/avatars/` sous nom aléatoire, MIME constaté par `finfo` ; `Core\Url::upload()` sert le fichier, l'ancienne version est supprimée |
| Migrations de schéma | ✅ | `sql/migrations.sql` : les « ALTER » des installations antérieures à `schema.sql`, tous porteurs de `IF NOT EXISTS` donc réapplicables sans risque. Étape décrite dans le README — sans elle, la fonction concernée échoue avec un message qui ne nomme pas la cause |
| Garde-fou dernier administrateur | ✅ | Empêche la perte d'accès total au système |
| Table Marques / Modèles | ✅ | Formulaire générique piloté par `Models\Dictionary` |
| Table Entités propriétaires | ✅ | Code interne unique |
| Table Organismes loueurs | ✅ | Email et téléphone de contact |
| Table Lieux d'exploitation | ✅ | Site et ville |
| Table Types d'intervention | ✅ | Libellé et catégorie |
| Échéances | ✅ | Page `/echeances` dédiée : échéancier des fins de contrat, 4 cartes KPI, tri par urgence croissante, filtre par palier sans JavaScript — rôle `lecture` |
| **Agenda** | ✅ | Page `/agenda`, **2ᵉ entrée de la barre de menu**, calendrier **FullCalendar 7.0.0** auto-hébergé (`public/assets/fullcalendar/`, aucun CDN). Réunit trois sources : échéances de fin de contrat (`vehicules.date_sortie_prevue`), révisions (`maintenances.date_operation`) et immobilisations (`vehicules.statut = 'immobilise'`). Vues Mois / Semaine / Liste, navigation par les flèches, libellés français. Chaque événement porte l'URL de la fiche du véhicule, donc c'est un lien ordinaire : ctrl-clic et nouvel onglet fonctionnent. Rôle `lecture` |
| **Alimentation par intervalle affiché** | ✅ | `events` est une fonction : chaque navigation demande la plage réellement visible à `GET /api/agenda/evenements?du=…&au=…`, que `AgendaService` regroupe en un seul appel. Un agenda figé au chargement serait faux dès la première navigation. Les compteurs des cartes et des boutons sont **recalculés depuis la réponse**, jamais figés côté serveur |
| **Plage bornée côté serveur** | ✅ | `du` et `au` sont échangés s'ils sont inversés, une plage de plus de 730 jours est ramenée à deux ans, et une date illisible (`2026-13-45`) retombe sur le mois courant. `Request::date()` ne valide que la forme ; c'est `strtotime()` qui tranche sur le sens. Sans ce bornage, `?du=1900-01-01` transformerait l'agenda en requête de plusieurs années sur l'historique d'entretien |
| **Filtrage par famille, refait par le serveur** | ✅ | Les trois boutons de l'en-tête de carte portent l'état par `aria-pressed` et non par une classe : le rendu de FullCalendar réécrit le contenu des jours, et une classe posée par le script risquerait d'être perdue. Masquer la dernière famille active est refusé, sinon le calendrier se viderait sans moyen de le remplir à nouveau. Les cartes KPI suivent la plage affichée |
| **Échéances colorées par urgence** | ✅ | Sous 30 jours en rouge, jusqu'à 90 en orange, jusqu'à 180 en ambre, au-delà en bleu ardoise — mêmes paliers que la page « Échéances », avec le nombre de jours restants dans l'info-bulle |
| **`Services\AgendaService` — trois sources, trois niveaux de certitude** | ⚠️ | Les sources ne sont pas équivalentes et ne sont pas présentées comme telles. **Échéance** : date contractuelle réelle. **Révision** : date réelle, mais celle d'une intervention **déjà réalisée** — le schéma ne porte ni périodicité ni date de prochaine révision. **Immobilisation** : un **état sans aucune date**, la table `immobilisations` n'existant pas ; l'événement est ancré sur la date de consultation, marqué « en cours », et la carte l'explique |
| **FullCalendar v7 — API corrigée** | ✅ | La v7 a **supprimé** `backgroundColor`, `borderColor` et `textColor`, et ne lit plus `classNames` (tableau) mais `className` (chaîne) ; l'objet passé à `locale` n'est plus lu. Un `classNames` en tableau n'aurait produit **aucun style, sans la moindre erreur** : tous les événements seraient restés de la couleur du thème. Les libellés sont des options **plates** (`todayText`, `monthText`, `weekTextLong`, `listText`…), `weekText` servant au numéro de semaine (`S{numero}`) et non au bouton |
| **Noms de classes de FullCalendar inutilisables** | ✅ | Les classes internes sont **hachées** (`fc-4c`, `fc-classic-TZ4`) et changent d'une version à l'autre : ni sélecteur de style ni mesure de test ne peut s'y fier. Seuls les sélecteurs publics et les classes `flotteo-evenement*` posées par `AgendaService` via `className` sont utilisés, ce qui les rend stables d'une version à l'autre |
| **`Core\View::useStyle()`** | ✅ | Symétrique de `useScript()`, pour les feuilles de page : elles sont émises dans l'en-tête et non dans le corps, une feuille déclarée au milieu du document appliquant ses règles après l'affichage initial. `useScript()` accepte désormais un chemin explicite (`fullcalendar/fullcalendar.global.js`) en plus d'un nom de page (`agenda.js`) |
| Paramètres système | ✅ | Navigation verticale par sections (Messagerie, Alertes, Sauvegardes, Mises à jour) ; mode d'envoi natif ou SMTP ; serveur SMTP, authentification, test d'envoi ; paliers et destinataire des alertes ; réglages de sauvegarde, d'archivage local et d'externalisation Samba ; dépôt GitHub de référence et recherche de mise à jour |
| **Sauvegarde et restauration** | ✅ | `Services\BackupService` : archive `ZIP` (extension `zip` requise) contenant le schéma, les données de toutes les tables applicatives et le contenu de `public/uploads/`. Bande en tête `manifest.json` (version, horodatage, tables, volume) permettant de vérifier l'intégrité et l'origine d'une archive avant restauration. Historique horodaté en base, téléchargement, suppression, restauration après contrôle de l'archive |
| **Rotation des archives** | ✅ | Purge automatique à chaque sauvegarde selon la rétention configurée ; purge manuelle disponible depuis l'historique |
| **Externalisation Samba** | ✅ | `Services\SambaClient` : dépôt de l'archive sur un partage réseau en accès anonyme ou authentifié, **sans bibliothèque tierce** — montage `smbclient` ou `mount -t cifs` piloté par `escapeshellarg()`, jamais de concaténation de commande. Test de connexion dédié, échecs journalisés sans jamais consigner le mot de passe |
| **Onglet Sauvegardes & Restauration** | ✅ | Section `sauvegardes` de `/admin/parametres/{section}` : formulaire de configuration, test Samba, lancement manuel, historique horodaté. Lancement et suppression passent par la modale de confirmation Tabler et un jeton CSRF ; le mot de passe Samba n'est jamais réémis dans le HTML |
| **Sauvegarde planifiée** | ✅ | `bin/backup.php` : même service que le lancement manuel, à appeler en cron. Exemple : `17 2 * * * php /var/www/flotteo/bin/backup.php` |
| **Recherche de mise à jour** | ✅ | Section « Mises à jour » de `/admin/parametres/{section}` : dépôt GitHub de référence saisissable (forme `compte/depot`), recherche à la demande de la dernière version publiée, comparaison avec la version installée par `version_compare()`, et lien vers la publication. **Repli sur les étiquettes** : un dépôt qui versionne par tags sans créer de release renvoie 404 sur `releases/latest` ; le dépôt est alors confirmé par un appel sur lui-même, et son **étiquette de version la plus haute** sert de référence — préversions écartées, plus haut numéro retenu plutôt que le premier tag renvoyé (GitHub les ordonne par date de création). La provenance (`version` ou `etiquette`) est conservée avec le relevé et affichée. `Services\GithubClient` — appel `api.github.com` par cURL, **aucune bibliothèque tierce**, aucun jeton enregistré |
| **Dépôt saisi traité comme une entrée hostile** | ✅ | La forme `compte/depot` est validée **avant** écriture en base et avant tout appel ; chaque segment est ré-encodé séparément, `CURLOPT_PROTOCOLS` est verrouillé sur HTTPS et les redirections ne sont pas suivies. L'hôte est une constante, jamais reconstruit depuis la saisie |
| **Relevé mémorisé, jamais d'appel au rendu** | ✅ | Le résultat est écrit sous la clé `depot_github_releve` et relu par `ParamController::miseAJour()`. L'affichage n'instancie donc jamais `GithubClient` : pas de lenteur, pas de consommation du quota à chaque ouverture, et la page reste lisible sans réseau sortant |
| **Échements distingués** | ✅ | Dépôt inexistant (404), dépôt sans version publiée (le 404 des versions est rejoué sur `/repos/…` pour trancher), limite de débit (403/429 — 60 appels/heure/IP sans jeton), coupure réseau, réponse illisible, étiquette sans numéro de version : chaque cas a son message, journalisé quand il s'agit d'une panne |
| **Contrôle du dépôt saisi, pas du dépôt enregistré** | ✅ | Le contrôle porte sur la valeur du formulaire quand elle est présente, comme le test SMTP : une correction peut être vérifiée avant d'être enregistrée. Un dépôt invalide efface le relevé précédent, faute de quoi la section afficherait une version sans rapport avec la saisie |
| **Jeton GitHub hors base** | ✅ | Lu dans `config/secrets.php` (clé `github_token`, 0600, hors racine servie) ou dans la variable d'environnement `FLOTTEO_GITHUB_TOKEN` qui prime sur lui. Jamais stocké en `parametres`, donc **absent de toute archive de sauvegarde** ; jamais renvoyé par un formulaire, jamais journalisé. Le fichier est exclu du dépôt par `.gitignore` — un `ghp_…` versionné serait divulgué à chaque clone |
| **Repli anonyme** | ✅ | Sans jeton, le contrôle reste fonctionnel sur un dépôt public. `GithubClient::quotaHoraire()` annonce 60 requêtes/heure sans jeton et 5 000 avec, et la section affiche l'état réel. `401` (jeton refusé) est distingué de `403`/`429` (limite de débit) pour que le message désigne la bonne correction |
| Référentiels | ✅ | CRUD complet sur 6 tables de paramétrage — marques, modèles, entités propriétaires, organismes loueurs, lieux d'exploitation, types d'intervention — via une page générique pilotée par `Dictionary::TYPES`. Ajout et édition en modale `modal-blur`, suppression confirmée en modale Tabler. Atteignable par l'entrée « Référentiels » de la barre (niveau `administration`) |
| Guide utilisateur | ✅ | `docs/user_guide.md`, rendu dans le layout général à `/docs/user_guide` : écran par écran, résolution des pannes courantes et avertissements de sécurité (certificat SMTP auto-signé, conservation du mot de passe) |
| Refus de suppression référencée | ✅ | Clés étrangères SQL + message explicite |
| Journal d'audit des modifications | 🔄 | Non prévu au cahier des charges |

## 7. Infrastructure & exploitation

| Fonctionnalité | État | Détail d'implémentation |
| :--- | :---: | :--- |
| Front controller Apache + mod_rewrite | ✅ | `public/.htaccess`, routage `/module/action` |
| **Résolution des URL publiques** | ✅ | `core/Url.php` : préfixe de base déduit de `SCRIPT_NAME`, jamais codé en dur. Fonction pure `Url::detect()` couverte par 6 cas de déploiement |
| **Court-circuit des ressources statiques** | ✅ | `public/.htaccess` : `assets/`, `uploads/`, `app.json`, `favicon.svg` exclus de la réécriture avant toute condition |
| **Types MIME explicites** | ✅ | `application/json`, `image/svg+xml`, `text/css`, `application/javascript`, `font/woff2` |
| **Manifeste d'application** | ✅ | `public/app.json` + 2 icônes SVG (standard et maskable), `start_url` et `scope` relatifs au préfixe détecté |
| **Icône de site** | ✅ | `public/assets/img/flotteo.svg` + `<link rel="icon">`, supprime le 404 `favicon.ico` |
| Registres documentaires accessibles en HTTP | ✅ | `DocsController` rend `/docs/{doc}` **dans le layout général** (`.card`, `col-lg-3` / `col-lg-9`) au lieu de diffuser le fichier compilé par `readfile()` ; `/docs` redirige vers le guide. Clés bornées par `Models\Registre::DOCUMENTS`, authentification requise. `docs/` reste hors racine servie |
| Cache HTTP des assets | ✅ | **Jeton de version obligatoire** : `Core\Url::asset()` ajoute `?v=<horodatage de modification>`, si bien qu'un asset corrigé change d'URL et est nécessairement redemandé. `mod_expires` accorde 7 jours sur CSS/JS/SVG, **mais ce module doit être chargé** — sans lui, aucune directive de cache n'est envoyée et le navigateur applique une fraîcheur heuristique. Le cache long reste sûr dans les deux cas grâce au jeton |
| En-têtes de sécurité HTTP | ⚠️ | `X-Content-Type-Options`, `X-Frame-Options` et `Referrer-Policy` déclarés dans `public/.htaccess`, sous `mod_headers` — **module à activer** (`sudo a2enmod headers`) |
| Dépôt des sources hors zone servie | ✅ | `config/`, `core/`, `models/`, `controllers/`, `views/`, `sql/`, `scripts/`, `storage/` : HTTP 403 |
| Zone d'uploads non exécutable | ✅ | `.htaccess` dédié, extensions PHP bloquées |
| **Jeu de données de démonstration** | ✅ | `scripts/seed_demo.php` : injection PDO, génération de `sql/demodata.sql`, ou vérification sans base (`--verifier`) |
| Source unique des fixtures | ✅ | `scripts/seed/Data.php` — l'injecteur et le fichier SQL en découlent, ils ne peuvent pas diverger |
| Contrôle d'intégrité des fixtures | ✅ | Référentiels, ENUM, format SIV et code département, monotonie des kilométrages, TVA, paliers d'alerte — exécuté avant toute injection |
| Comptes de démonstration | ✅ | `admin@` / `gestion@` / `consultation@flotteo.local`, `Password123!` haché en Argon2id (repli bcrypt), condensat jamais stocké dans le dépôt |
| Script d'installation automatisé | ✅ | `scripts/install.php` : délègue à `Services\Installer`, identifiants administrateur fournis par `FLOTTEO_ADMIN_EMAIL` / `FLOTTEO_ADMIN_PASSWORD`, aucun mot de passe en dur |
| Assets 100 % locaux | ✅ | Tabler.io et ApexCharts embarqués, aucun CDN à l'exécution |
| Thème sombre | 🔄 | Tabler le propose, non activé dans le cahier des charges |
| Export / import CSV du parc | 🔄 | Non prévu au cahier des charges |

---

## 8. Correctifs d'infrastructure

### 8.1 Chemins d'actifs de la mire de connexion — 2026-10-02

**Symptôme.** La page `/login` s'affichait mais la console JavaScript remontait des
404 sur toutes les ressources critiques : Tabler.io (CSS/JS), la feuille de style
applicative, `app.json` et le manifeste. Aucun asset ne se chargeait, l'écran
était donc non stylé et le JavaScript inopérant.

**Cause racine.** Le préfixe d'URL était codé en dur dans `config/config.php`
(`'base_url' => '/flotteo'`) alors que le vhost Apache sert l'application à la
**racine du domaine** (`DocumentRoot /var/www/flotteo/public`, `ServerName
flotteo.local`). Toutes les URL générées valaient donc `/flotteo/assets/...`
au lieu de `/assets/...` et ne correspondaient à aucun fichier. Le défaut
touchait aussi tous les liens de navigation, les `action` de formulaire et les
redirections, pas uniquement la mire de connexion.

**Correctif.**

| Élément | Avant | Après |
| :--- | :--- | :--- |
| Préfixe d'URL | codé en dur `'/flotteo'` | auto-détecté via `SCRIPT_NAME` (`core/Url.php`) |
| Générateur d'URL | closure locale `$lien` répétée par vue | helper central `Url::to()`, `Url::asset()`, `Url::manifest()` |
| Routeur | lisait `base_url` du fichier de config | lit `Url::base()` |
| `Controller::baseUrl()`, redirection de connexion | config | `Url::base()` / `Url::to('/login')` |
| Manifeste | inexistant | `public/app.json` + 2 icônes SVG, MIME `application/json` |
| Icône | 404 `favicon.ico` implicite | `assets/img/flotteo.svg` |
| Assets en `.htaccess` | réécriture conditionnelle uniquement | court-circuit explicite avant conditions |
| Liens des registres | `/docs/*.html` : 404 (dossier hors racine servie) | route `/docs/{doc}` bornée par `Models\Registre`, rendue dans le layout, authentifiée |
| Titre de page | toujours « Tableau de bord » | libellé dérivé de la route active |

**Note Tabler.io.** `tabler-vendors.min.css` **n'existe pas** dans Tabler 1.0.0 :
c'est un nom hérité des versions 0.x. Le bundle `tabler.min.css` est autonome
(aucune référence à une police externe, icônes SVG inline). Le référencer
produirait un 404 ; il n'est donc pas appelé.

**Vérification.** Les 6 ressources de `/login` répondent désormais en `200` avec
le bon type MIME, en déploiement racine comme en sous-répertoire ; les 8
répertoires sensibles répondent toujours `403` ; 57 fichiers PHP valides au lint.

### 8.2 Résolution des libellés de dictionnaire — 2026-10-05

**Symptôme.** Chaque affichage d'un référentiel écrivait deux fois dans le
journal serveur :

```
ERREUR Dictionnaire source  inaccessible | SQLSTATE[42000]: Syntax error or
access violation: 1064 ... near 'AS libelle FROM' at line 1
```

La page s'affichait correctement — la boucle d'ingestion des libellés
déchouait sur une exception, était journalisée puis court-circuitée — mais le
bruit était permanent et masquait les erreurs réelles.

**Cause racine.** Dans `Models\Dictionary::TYPES`, l'entrée `marques` déclarait

```php
'sources' => ['marques' => ['id', 'nom']],
```

alors que la résolution des clés étrangères lit `$source['table']` et
`$source['label']`. Ces deux clés étant absentes, la requête assemble
`SELECT id,  AS libelle FROM ` : le nom de table **et** la colonne sont vides.

**Correctif.** Le dictionnaire `marques` ne possède aucune clé étrangère — il
est la table *référencée* par `modeles`, pas une table qui référence. Sa
déclaration devient donc `'sources' => []`, forme attendue pour un dictionnaire
sans relation. La description de `modeles`, seule à utiliser réellement ce
mécanisme, était déjà correcte (`['table' => 'marques', 'label' => 'nom']`).

Le `try` / `catch` autour de la requête est conservé : une table de
paramétrage absente sur une installation partielle doit rester non fatale.

**Vérification.** `/admin/dictionnaires/marques` répond `200` et plus aucune
entrée « Dictionnaire source inaccessible » n'est écrite au journal.

---

## 9. Déploiement & première installation

| Fonctionnalité | État | Détail d'implémentation |
| :--- | :---: | :--- |
| **Assistant web `/install`** | ✅ | Parcours 3 étapes (base, administrateur, initialisation) servi par le front controller : bootstrap, `Core\Url`, CSRF et flash conservés. `controllers/InstallController.php` + `views/install/index.php` |
| Test de connexion AJAX | ✅ | `POST /api/install/tester` répond en JSON (`success`, `message`, `detail`), codes 200 / 422 / 419 / 429 / 403 |
| Création de la base & du schéma | ✅ | `Services\Installer::executer()` : `CREATE DATABASE`, application des 12 tables, création du compte administrateur |
| Refus d'écrasement | ✅ | Installation bloquée si la base contient déjà des tables — `sql/schema.sql` porte des `DROP TABLE` |
| Écriture de la configuration | ✅ | `config/database.php` régénéré, permissions `0600`, écriture atomique |
| **Verrou d'installation** | ✅ | `storage/install.lock` hors racine servie, écrit en fin d'installation (`rename()` atomique) |
| États d'installation | ✅ | `Core\InstallState` : `a_installer`, `installe`, `defaillant` |
| Verrouillage de l'assistant | ✅ | `/install` et `/api/install/*` redirigent vers `/login` dès que le témoin d'installation est présent — y compris en état `defaillant`, où ouvrir l'assistant autoriserait à écraser une base en service. Seuls les 3 états (`a_installer`, `installe`, `defaillant`) × 2 types de route sont contrôlés |
| Échéances | ✅ | Page `/echeances` dédiée : échéancier des fins de contrat, 4 cartes KPI, tri par urgence croissante, filtre par palier sans JavaScript — rôle `lecture` |
| Paramètres système | ✅ | Navigation verticale `col-lg-3` (`list-group` + `aria-current="page"`) et contenu contextuel `col-lg-9`, section portée par la route `/admin/parametres/{section}` ; une section = un formulaire et un jeu de clés propre (`ParamController::SECTIONS`) |
| Messagerie | ✅ | Deux transports au choix — `mail` (frontal `mail()`) ou `smtp` (serveur externe) ; `Core\SmtpClient` implémente le dialogue SMTP sans bibliothèque tierce, avec STARTTLS, TLS direct, `AUTH PLAIN` et vérification du certificat du serveur ; test d'envoi POST JSON sur les valeurs saisies |
| Copie dans le presse-papiers | ✅ | Bouton `data-copier` : API Clipboard si disponible, sinon sélection du champ + toast — jamais d'alerte native |
| Édition d'une entrée de référentiel | ✅ | Le bouton « Éditer » déclenche la modale par l'API déclarative (`data-bs-toggle="modal"` + `data-bs-target`), jamais par instanciation manuelle ; le marqueur `data-edition-dictionnaire` permet à l'écouteur `show.bs.modal` de distinguer l'ajout de la modification via `relatedTarget` et de recopier les valeurs de la ligne |
| Barre d'onglets des référentiels | ✅ | `nav nav-underline`, seule variante Tabler produisant un liseré inférieur ; classe `active` sur le `nav-item` **et** le `nav-link`, avec `aria-current="page"` |
| Registres documentaires | ✅ | Les 4 registres (guide, changelog, fonctionnalités, recette) rendus comme écrans applicatifs : navigation `col-lg-3` (`list-group` + `aria-current="page"`), document `col-lg-9` en `.card`, dans le layout complet. Plus de `readfile()` |
| Source unique des registres | ✅ | `Models\Registre::DOCUMENTS` déclare fichier, libellé, titre, glyphe et résumé ; `DocsController` et `scripts/build_docs.php` le lisent tous deux, donc aucune dérive entre l'écran et le fichier compilé |
| Moteur Markdown partagé | ✅ | `Core\Markdown` convertit le sous-ensemble utilisé ; rendu **identique** dans l'application et dans les vues HTML autonomes, vérifié octet par octet. Conversion à la demande : 0,5 à 3,5 ms par registre, sans cache |
| Présentation du corps documentaire | ✅ | Classe `.markdown` de `flotteo.css` : titres, listes, code, citations et tableaux alignés sur les variables `--tblr-*` ; sans elle le HTML nu s'affichait avec les styles par défaut du navigateur |
| Icônes des registres | ✅ | Font Awesome (`fa-book`, `fa-file-lines`, `fa-list`, `fa-check`) via `Core\Icon` — les emoji qui figuraient auparavant sont supprimés, conformément au §3 |
| **Marqueurs d'état des registres** | ✅ | Les 154 marqueurs Unicode de la colonne « État » (`✅`, `🔄`, `⛔`, `⚠️`) sont rendus en icônes `fa-check`, `fa-clock`, `fa-xmark` et `fa-triangle-exclamation` par `Core\Markdown::MARQUEURS`, sans ajout au sous-ensemble embarqué. La source conserve le marqueur, lisible dans un éditeur de texte et dans un diff ; seul le rendu change. Conversion limitée aux **cellules dont le contenu entier est un marqueur** : les occurrences citées dans une phrase — le journal et la recette parlent des emoji retirés — restent du texte |
| Item actif de la barre supérieure | ✅ | Détection sur le chemin complet via `Core\Router::currentPath()` : `/admin/parametres` et `/admin/utilisateurs` sont distingués, les sous-chemins restent rattachés à leur module |
| Sous-ensemble d'icônes vérifié | ✅ | `Core\Icon::GLYPHES` et `fontawesome.css` déclarent **37 glyphes identiques**, sans écart dans aucun sens ; une icône absente lève une exception explicite au lieu d'afficher un carré |
| Redirection obligatoire | ✅ | En état `a_installer`, toute route applicative redirige vers `/install` : l'application ne peut pas tourner sur une base vide |
| Panne de configuration verrouillée | ✅ | `views/erreur/503.php` — le verrou est conservé, l'assistant n'est **pas** rouvert, procédure de restauration documentée à l'écran |
| Limitation des tentatives | ✅ | 12 tentatives sur fenêtre glissante de 5 minutes, en session, sur l'endpoint de test comme sur l'installation |
| Confidentialité des identifiants | ✅ | Le mot de passe MySQL n'est ni journalisé, ni réaffiché par la vue, ni transmis dans l'URL |
| Police de titraille locale | ✅ | Dela Gothic One, sous-ensemble latin, 12 Ko, servie depuis `public/assets/fonts/` |
| **Icônes Font Awesome** | ✅ | Font Awesome Free 7.3.1 auto-hébergé : `fa-solid` + `fa-brands` en woff2 local (230 Ko), aucun CDN. CSS élagué aux **37 glyphes utilisés** (6 Ko au lieu des ~2 000 icônes) |
| Helper d'icônes | ✅ | `Core\Icon::solid()` / `Core\Icon::brands()` : valide le glyphe contre le sous-ensemble embarqué et lève une exception explicite si l'icône est absente, au lieu d'un carré vide |
| Bandeau supérieur horizontal | ✅ | `views/layout/header.php` : `navbar-expand-xl` en `container-fluid px-4 px-lg-5`, 100 % de la largeur, sticky. Sidebar vertical supprimé (entrées dupliquées). Se replie sous 1200 px, en deçà des ~1130 px qu'exigent les sept modules, l'identité et le menu utilisateur |
| Largeur du contenu | ✅ | Corps de page et pied de page en `container-fluid px-4 px-lg-5` — plus de plafond de 1320 px sur grand écran ; 32 px de marge latérale au-dessus de 992 px, 24 px en dessous, sur la barre comme sur le corps |
| Aucun défilement horizontal | ✅ | Vérifié sous Chrome headless de 500 à 1920 px sur 3 pages du layout complet : une page en débordement avant correction, aucune après. Tableau à 12 colonnes éprouvé : remplit la largeur quand il le peut, sinon défile dans son `.table-responsive` |
| Graphiques fluides | ✅ | Aucune largeur fixe dans les configurations ApexCharts de `dashboard.js` : les 4 graphiques du tableau de bord suivent la largeur de leur conteneur, de 866 px à 418 px selon la fenêtre, sans débordement |
| **Bouton de retour en haut** | ✅ | Bouton flottant dans `views/layout/footer.php` + `.retour-haut` dans `flotteo.css` ; il n'apparaît qu'au-delà de 400 px de défilement et utilise le défilement doux, neutralisé si `prefers-reduced-motion` est actif. Masqué par `visibility: hidden`, donc absent du parcours de tabulation tant qu'il n'est pas visible, et exclu de l'impression (`d-print-none`) |
| Identité et modules dans la barre | ✅ | « Flotteo » à gauche ; Tableau de bord, Véhicules, Révisions, Incidents, Échéances, Référentiels, Paramètres, Utilisateurs dans la barre ; repli en `collapse` sous 992 px |
| Zone profil et déconnexion | ✅ | Avatar, nom et e-mail, badge de rôle, menu déroulant (profil, documentation, déconnexion `POST` protégé par CSRF) |
| Recette fonctionnelle de l'installation | ✅ | `docs/qa_recette.md` § 0, scénario `INS-01` à `INS-08` |
| **Affichage de l'étape 2** | ✅ | Le `d-none` initial porte sur la carte `#etape-2`, seul nœud piloté par `install.js`. Un `d-none` sur le `<form>` masquait la carte même rendue visible |
| **Champs administrateur** | ✅ | `nom_d_utilisateur` (texte, requis, placeholder homonyme), `admin_email` (`email`, requis), `admin_password` (`password`, requis, 10 car. mini), `admin_password_confirm` (`password`, requis) — nomenclature alignée entre vue, contrôleur et JavaScript |
| **Colonne cible du nom d'utilisateur** | ✅ | `utilisateurs.nom` — le schéma ne comporte **ni `username` ni `login`** ; l'identifiant de connexion reste l'e-mail, unique via `uq_utilisateurs_email` |
| Bouton de validation étape 2 → 3 | ✅ | `bouton-installer` (`type="submit"`) : contrôle de validité, comparaison des deux mots de passe, bascule vers l'étape 3 puis soumission réelle du formulaire |
| Jauge de robustesse du mot de passe | ✅ | Évaluation indicative côté client sur `admin_password`, libellée « excellent / correct / acceptable / trop faible » ; la règle serveur fait foi |
| Concordance des deux mots de passe | ✅ | Retour immédiat sur `admin_password_confirm` via `.is-valid` / `.is-invalid`, doublé d'un contrôle serveur à la soumission |
| **Retour d'erreur non dupliqué** | ✅ | `afficherRetour()` rend le message une seule fois dans l'alerte Tabler ; le bloc gris `text-secondary` n'apparaît que si `detail` est non vide **et différent** du message |
| **Conservation des saisies de l'étape 1** | ✅ | `InstallController::saisieBdd()` réémet hôte, port, nom de base et utilisateur après un re-rendu serveur ; le mot de passe MySQL n'est jamais réémis |
| Alerte positionnée au-dessus des champs | ✅ | `#retour-test` est le dernier élément de `<form id="formulaire-bdd">`, donc sous les champs et au-dessus du pied de carte d'actions |
| **Largeur de frame de l'assistant** | ✅ | `.container-tight` (30rem / 480px, trop étroit) remplacé par `.install-container`, fluide sous 576 px puis 40rem → 48rem → 54rem → 56rem selon le point de rupture |
| **Grille de l'étape 1** | ✅ | Hôte `col-6 col-md-8` + port `col-6 col-md-4` sur une ligne ; nom de base et utilisateur MySQL en `col-12 col-md-6` ; mot de passe MySQL en `col-12 col-md-6` |
| **Alignement des boutons d'action** | ✅ | `.install-actions` : `flex-wrap` + gouttière, action principale à droite via `.install-action-final`, note d'appoint repliée sous 768 px |
| **Empilement mobile** | ✅ | Sous 768 px, boutons en `column-reverse` pleine largeur, la validation reste l'action la plus proche du pouce |
| **Stabilité verticale des champs** | ✅ | `.form-hint` à hauteur minimale réservée : plus de décalage entre colonnes dont une seule porte une indication |

