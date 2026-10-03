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
| 5. Matrice des incidents | ✅ | Radar de répartition + courbe de tendance annuelle |
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
| Garde-fou dernier administrateur | ✅ | Empêche la perte d'accès total au système |
| Table Marques / Modèles | ✅ | Formulaire générique piloté par `Models\Dictionary` |
| Table Entités propriétaires | ✅ | Code interne unique |
| Table Organismes loueurs | ✅ | Email et téléphone de contact |
| Table Lieux d'exploitation | ✅ | Site et ville |
| Table Types d'intervention | ✅ | Libellé et catégorie |
| Échéances | ✅ | Page `/echeances` dédiée : échéancier des fins de contrat, 4 cartes KPI, tri par urgence croissante, filtre par palier sans JavaScript — rôle `lecture` |
| Paramètres système | ✅ | Navigation verticale par sections (Messagerie, Alertes) ; mode d'envoi natif ou SMTP ; serveur SMTP, authentification, test d'envoi ; paliers et destinataire des alertes |
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
| Cache HTTP des assets | ✅ | `mod_expires` : 7 jours sur CSS/JS/SVG, 0 seconde sur PHP |
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
