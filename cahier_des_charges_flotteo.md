# Cahier des Charges & Règles Fondatrices — Projet Flotteo

**Version :** 1.1  
**Statut :** Document Cadre / Spécifications Fonctionnelles et Techniques  
**Application :** Flotteo (Système de Gestion de Parc Automobile)  
**Environnement de développement :** VS Code / Antigravity / IA Gemini  

---

## 1. Présentation Générale du Projet

### 1.1 Objectif
Flotteo est une application web interne dédiée à la gestion, au pilotage opérationnel, à l'analyse financière et à la traçabilité complète du cycle de vie d'un parc de véhicules de location.

### 1.2 Principes directeurs
* **Simplicité & Légèreté :** Zéro framework backend lourd. Stack PHP native ultra-rapide.
* **Autonomie :** Déploiement et exécution sur architecture serveur Apache standard.
* **Sobriété de code & économie de tokens :** Architecture modulaire, fonctions concises, fichiers ciblés évitant le gaspillage lors des sessions d'assistance IA.
* **Interface standardisée :** UI élégante et professionnelle via Tabler.io / Bootstrap 5, sans jamais recourir aux boîtes de dialogue natives du navigateur (`alert`, `confirm`, `prompt`).
* **Visualisation décisionnelle :** Tableaux de bord synthétiques et graphiques temps réel pour un pilotage direct de la flotte.

---

## 2. Architecture & Directives Techniques

### 2.1 Stack Technologique
* **Serveur Web :** Apache avec module `mod_rewrite` activé.
* **Backend :** PHP (8.1+ recommandé, PHP natif sans framework MVC tiers).
* **Architecture Backend :** **VMVC Strict** (View - Model - View - Controller / Modèle-Vue-Contrôleur natif strict avec séparation étanche des couches d'affichage et de logique métier).
* **Base de Données :** MySQL / MariaDB via extension **PDO**.
* **Frontend :**
  * **HTML5 / CSS3 / JavaScript Vanilla** (aucun framework JS lourd type React/Vue).
  * **UI Framework :** Bootstrap 5 intégré avec le thème d'interface **Tabler.io**.
  * **Moteur Graphique :** **ApexCharts** (intégré nativement et stylisé pour Tabler.io), consommant des endpoints JSON légers fournis par les contrôleurs PHP.
  * **Modals & Dialogs :** Utilisation stricte des composants Modal de Tabler/Bootstrap pour toutes les interactions, messages de succès, d'erreur et confirmations de suppression.

### 2.2 Conventions de Codage & Règles d'Or
1. **Typage strict PHP :** Présence obligatoire de `declare(strict_types=1);` en première ligne de chaque fichier PHP.
2. **Sécurité SQL :** Requêtes préparées PDO systématiques. Aucune concaténation de variables dans les requêtes SQL.
3. **Robustesse :** Encapsulation systématique des opérations critiques dans des blocs `try { ... } catch (PDOException | Exception $e) { ... }` avec journalisation propre et affichage sécurisé (pas de fuite de traces d'erreur techniques à l'écran).
4. **Interdiction d'alertes natives :** `window.alert()`, `window.confirm()` et `window.prompt()` sont formellement proscrits. Les confirmations et rétroactions utilisateur se font exclusivement via des fenêtres modales et toasts Tabler.io.

### 2.3 Économie de Tokens & Travail avec l'IA
* Structurer le projet en petits modules autonomes.
* Privilégier des fonctions unitaires courtes et documentées.
* Ne jamais renvoyer ou réécrire l'intégralité d'un fichier volumineux quand une modification locale suffit.

---

## 3. Protocoles Documentaires et Registres

Pour garantir la traçabilité et la qualité de la production logicielle, trois registres synchronisés doivent être tenus à jour :

| Type de registre | Version Source (Markdown) | Version Exploitable (HTML / Interface) | Objectif |
| :--- | :--- | :--- | :--- |
| **Journal des modifications** | `changelog.md` | `changelog.html` | Historique des versions, correctifs et évolutions pour les utilisateurs. |
| **Registre des fonctionnalités** | `features.md` | `features.html` | Inventaire exhaustif des capacités actives et planifiées de l'outil. |
| **Registre de Recette & Qualité** | `qa_recette.md` | `qa_recette.html` | Cas de tests fonctionnels, résultats des recettes et validation des bugs. |

### Protocole de Clôture Obligatoire
Avant de déclarer une tâche ou une sous-tâche achevée :
1. Les vérifications de non-régression et de typage strict doivent être validées.
2. Les éventuels impacts sur l'interface doivent être testés avec les composants Tabler.io.
3. Les registres `changelog.md` / `changelog.html`, `features.md` / `features.html` et `qa_recette.md` / `qa_recette.html` doivent être synchronisés et mis à jour.

---

## 4. Spécifications Fonctionnelles

### 4.1 Module Authentification & Rôles
* **Écran de connexion :** Identifiant/email et mot de passe sécurisé (hashage via `password_hash()` BCRYPT ou ARGON2ID).
* **Gestion des sessions :** Sécurisation des cookies de session (`httponly`, `samesite`, `secure`).
* **Matrice des Rôles (3 niveaux) :**
  1. **Lecture Seule :** Consultation des dashboards, fiches véhicules, plannings, coûts et incidents sans droit d'écriture.
  2. **Modification :** Ajout, édition des véhicules, enregistrement des révisions, incidents et modifications courantes.
  3. **Administration :** Accès complet incluant la gestion des utilisateurs, les tables de paramétrage (marques, entités, loueurs) et la configuration système.
* **Module d'Administration des Utilisateurs :** interface CRUD (Création, Lecture, Modification, Suppression/Désactivation), présentée en **grille de cartes de profil** inspirée de Tabler.io : `row row-cards` en `col-md-6 col-lg-4`, une carte par compte.

### 4.2 Module Tableau de Bord & Pilotage Visuel (Dashboard Graphique)
Le dashboard constitue la vue d'accueil après authentification. Il présente une synthèse opérationnelle et financière temps réel basée sur des cartes KPI et des graphiques interactifs (ApexCharts / Tabler.io).

#### A. Indicateurs Clés (Cartes KPI Synthétiques)
* **Taille de la flotte active :** Nombre total de véhicules sous contrat / en exploitation.
* **Taux d'immobiliasation :** Pourcentage de véhicules actuellement indisponibles (en atelier ou accidentés).
* **Alertes Échéances Imminentes :** Badge d'alerte indiquant le nombre de sorties prévues dans les 30 à 90 jours.
* **Coût d'Entretien Mensuel / Annuel :** Montant cumulé des révisions et réparations sur la période en cours.

#### B. Graphiques Opérationnels & Stratégiques (Propositions Clés)
1. **Échéancier des Sorties de Flotte (Graphique en barres mensuelles / Timeline) :**
   * Visualisation sur 12 mois des volumes de véhicules à restituer, facilitant l'anticipation des négociations avec les loueurs.
2. **Répartition de la Flotte (Donuts interactifs avec filtres) :**
   * *Par Entité propriétaire :* Répartition du parc entre les différentes sociétés internes.
   * *Par Organisme Loueur :* Degré d'exposition et dépendance commerciale envers chaque partenaire.
   * *Par Lieu d'exploitation :* Affectation géographique des véhicules sur les différents sites/dépôts.
3. **Évolution des Dépenses d'Entretien (Courbe / Histogramme empilé) :**
   * Historique mensuel des coûts découpé par typologie de révision (Pneumatiques, Vidanges/Révisions constructeur, Freinage, Contrôles techniques).
4. **Indicateur de TCO Entretien Moyen par Marque / Modèle (Barres horizontales) :**
   * Comparatif du coût d'entretien cumulé par modèle de véhicule pour identifier les séries les plus coûteuses ou fragiles.
5. **Matrice des Incidents & Sinistralité (Graphique radar ou secteurs) :**
   * Proportion des types d'incidents (accidents responsables, bris de glace, pannes mécaniques, vandalisme) et tendance annuelle.

### 4.3 Module Gestion de Flotte (Véhicules)
Chaque fiche véhicule comprend :
* **Identifiant interne (ID)** unique.
* **Marque & Modèle** (liés à une nomenclature paramétrable).
* **Entité propriétaire** (gestion multi-sociétés internes avec ajout/suppression d'entités).
* **Organisme loueur** (partenaires de leasing/location longue durée avec ajout/suppression de tiers).
* **Lieu d'exploitation** (site, agence ou dépôt d'affectation).
* **Cycle d'exploitation :**
  * Date d'entrée en flotte.
  * Date de sortie prévisionnelle.
  * Date de sortie effective.

### 4.4 Tables de Paramétrage (Dictionnaires)
Pour éviter la saisie libre et standardiser les données, les administrateurs gèrent :
* Les **Marques et Modèles**.
* Les **Sociétés / Entités propriétaires**.
* Les **Organismes Loueurs**.
* Les **Lieux d'exploitation**.
* Les **Types d'interventions/révisions**.

### 4.5 Module Suivi du Cycle de Vie, Entretien & Incidents
* **Historique des Révisions :**
  * Type de prestation paramétrable (ex: Révision constructeur, Pneumatiques, Freinage, Contrôle technique).
  * Horodatage précis (date et kilométrage lors de l'opération).
  * Coût financier associé HT / TTC.
* **Gestion des Incidents & Sinistres :**
  * Saisie de l'événement (accrochage, panne, vandalisme, bris de glace).
  * Date, lieu et descriptif de l'incident.
  * Upload de pièces jointes (fichiers PDF de factures, constats amiables, photographies de dommages). Stockage sécurisé sur serveur avec nommage unique et contrôle des types MIME autorisés.

### 4.6 Système d'Alertes et de Notification par Email
* **Gestion de fin de détention / fin de contrat :**
  * Calcul automatique du temps restant avant la date de sortie prévue.
  * Seuil d'anticipation paramétrable : préavis à **3 mois**, **6 mois** ou **9 mois**.
  * Génération d'un **mail condensé récapitulatif** envoyé périodiquement à l'adresse administrateur/gestionnaire, récapitulant l'ensemble des véhicules du parc arrivant à échéance selon les paliers définis.
  * Tâche automatisable (script CLI exécuté via tâche Cron Apache/Linux).

### 4.7 Barre supérieure et mise en évidence de la page courante
* **Bandeau unique :** barre horizontale à 100 % de la largeur (`container-fluid`), identité à gauche, modules au centre, profil et déconnexion à droite. Aucun `navbar-vertical`.
* **Point de rupture `navbar-expand-xl` :** la barre se replie sous **1200 px**, et non sous 992 px. Les sept modules, l'identité et le menu utilisateur exigent environ 1130 px de contenu : entre 992 et 1200 px, `navbar-expand-lg` maintenait une barre à une ligne qui débordait de la fenêtre et imposait un défilement horizontal à toute la page. Tabler fournit les règles du liseré actif pour ce point de rupture comme pour les autres ; `flotteo.css` applique en conséquence `@media (max-width: 1199.98px)` aux règles de l'état replié.
* **Liseré de l'item actif :** le module courant est signalé par la classe `active` posée **à la fois** sur `nav-item` et sur `nav-link`, complétée par `aria-current="page"`. Le liseré bleu est produit par Tabler via `.navbar-expand-xl .nav-item.active:after` (bordure inférieure 2 px, `position: relative` sur `nav-item`, à partir de 1200 px) : le code applicatif ne dessine jamais ce liseré lui-même et le CSS maison ne doit pas le masquer. **Menu replié :** sous 1200 px, Tabler ne dessine aucun repère par `:after` — la variante `.navbar-collapse .nav-item.active:after` ne fixe pas de `content` et ne produit donc rien. Le module courant reste identifiable par son gras (`600` contre `400`), apportée par `.barre-superieure .navbar-nav .nav-link.active`.
* **Largeur du contenu :** le corps de page et le pied de page utilisent `container-fluid` **avec `px-4 px-lg-5`** — 1,5 rem de marge latérale, 2 rem à partir de 1200 px. La barre supérieure reçoit le même padding, faute de quoi l'identité et la première entrée de menu seraient désalignées du contenu des cartes. Ces utilitaires portent `!important` et l'emportent donc sur le padding par défaut de `.container-fluid` : aucune règle CSS maison n'est nécessaire. `container-xl` est proscrit dans le layout, son `max-width` de 1320 px à partir de 1400 px laissant de larges marges inutiles sur grand écran. Le plafond de `.layout-boxed .page` ne s'applique pas : la classe `layout-boxed` n'est pas employée.
* **Aucun défilement horizontal :** le comportement est vérifié au rendu sous Chrome headless, de 500 px à 1920 px, sur des pages représentatives. Un tableau à 12 colonnes remplit la largeur disponible quand il le peut et, sinon, défile dans son `.table-responsive` (`overflow-x: auto`) sans jamais faire déborder la page. Les graphiques ApexCharts ne déclarent aucune largeur — ils suivent celle de leur conteneur. Toute valeur `width` fixe dans une configuration de graphique est proscrite.
* **Détection sur le chemin complet :** l'entrée active est déterminée par comparaison du chemin de la requête **complet**, obtenu via `Core\Router::currentPath()` (préfixe de base retiré, donc correct y compris en installation sous-dossier). Une comparaison segment par segment est proscrite : `/admin/parametres` et `/admin/utilisateurs` partagent le segment `admin` et deviennent indiscernables.
* **Sous-chemins :** une page de détail reste rattachée à son module (`/vehicules/voir` laisse « Véhicules » actif). En cas de chevauchement d'URL, l'URL la plus longue l'emporte.
* **Racine :** `/` est traitée comme `/dashboard`.
* **Règles d'affichage :** une page absente de la barre (dictionnaires, profil, documentation) ne met en évidence aucun item ; les entrées d'administration ne sont rendues que pour le rôle `administration`.
* **Titre de page :** la correspondance titre ↔ chemin est résolue sur le chemin complet, de sorte que chaque page porte son propre intitulé et non celui de sa section.

### 4.8 Module Échéancier des fins de contrat
* **Écran dédié `/echeances` :** l'échéancier quitte l'écran des paramètres pour devenir une page à part entière, `Controllers/EcheanceController` et `views/echeances/index.php`. Ce contenu était auparavant imbriqué dans l'administration, où il occupait une place disproportionnée et n'était visible que du rôle `administration`.
* **Profil d'accès :** rôle `lecture`, à l'instar du graphique des restitutions prévues du tableau de bord. L'échéancier est une information opérationnelle, pas un réglage.
* **Onglet de navigation :** « Échéances » s'insère dans la barre supérieure entre « Incidents » et « Paramètres », avec l'icône `fa-calendar-days` et la gestion habituelle de la classe `active`.
* **Tri :** les véhicules sont classés par urgence croissante — le plus proche d'échéance en tête — et non par palier, afin de servir directement l'ordre de traitement.
* **Synthèse :** quatre cartes KPI (échéances surveillées, sous 30 jours, sous 90 jours, prochaine échéance).
* **Filtrage :** par palier, sans JavaScript, chaque palier étant une URL porteuse du paramètre `palier`. L'état courant est signalé par la classe `active` et `aria-current="page"`.
* **Badge de compte :** chaque filtre affiche son effectif, de sorte que l'utilisateur sache s'il a des résultats avant de cliquer.
* **Préparation des données :** elle incombe au contrôleur (aplatissement des groupes, tri, effectifs, compteurs) ; la vue se limite à l'échappement et à la mise en forme.

### 4.9 Écran « Paramètres système »
* **Organisation en sections :** l'écran se parcourt par une navigation verticale — un quart de la largeur pour les sections, trois quarts pour le contenu — sur le modèle Tabler « Settings ». La section demandée est portée par la route (`/admin/parametres/{section}`), l'état courant étant signalé par la classe `active` et `aria-current="page"`.
* **Sections :** *Messagerie* (mode d'envoi, serveur SMTP, authentification, adresse expéditrice, test d'envoi) et *Alertes* (activation, paliers d'anticipation, destinataire, envoi manuel, tâche planifiée). `/admin/parametres` sans section sert la section par défaut.
* **Occupation :** `row g-4` avec `col-lg-3` pour la navigation et `col-lg-9` pour le contenu.
* **Unicité des classes :** la mise en page n'emploie que des utilitaires Tabler.io / Bootstrap 5 (`row`, `g-4`, `col-*`, `card-*`, `list-group`, `input-group`), sans classe personnalisée.
* **Un envoi par section :** chaque section porte son propre formulaire et n'écrit que ses propres clés (`ParamController::SECTIONS`). Cette contrainte est structurante : la répartition des clés entre sections est déclarée dans le contrôleur, un envoi partiel ne peut donc pas neutraliser une autre section.

### 4.10 Mode d'envoi et serveur SMTP
* **Deux transports :** `mail` délègue au frontal `mail()` du serveur ; `smtp` adresse directement le serveur de messagerie. Le choix est porté par le paramètre `mail_transport`, adossé à `Core\Mailer::TRANSPORTS`.
* **Client natif :** aucune bibliothèque tierce n'est introduite. Le dialogue SMTP est implémenté dans `Core\SmtpClient` au-dessus de `stream_socket_client()`, sans dépendance externe.
* **Chiffrement :** trois modes — `aucun` (texte en clair), `tls` (STARTTLS, RFC 3207, avec nouvel `EHLO` sur la session chiffrée) et `ssl` (TLS établi dès la connexion). Un mode inconnu est **rejeté** et ne se replie jamais sur du texte en clair : l'utilisateur ne doit pas croire ses messages chiffrés alors qu'ils partiraient en clair.
* **Certificats :** le certificat du serveur est vérifié (`verify_peer`, `verify_peer_name`, SNI). Un certificat auto-signé ou expiré est refusé, et l'erreur indique explicitement cette cause.
* **Authentification :** facultative, par `AUTH PLAIN`, engagée uniquement lorsque le serveur annonce cette méthode.
* **Secret :** le mot de passe n'est jamais renvoyé au navigateur. Un champ laissé vide conserve le mot de passe enregistré ; son effacement exige la case à cocher dédiée.
* **Test d'envoi :** action POST JSON (`/admin/parametres/messagerie/tester`) calquée sur `/api/install/tester`, qui éprouve le serveur avec les valeurs **actuellement saisies** et non celles enregistrées — la configuration peut ainsi être validée avant d'être persistée. Seul le mot de passe retombe sur la valeur enregistrée.

### 4.11 Référentiels (administration)
*Périmètre fonctionnel fixé au § 4.4 ; la présente section en précise la réalisation.*

* **Page générique :** un seul écran `/admin/dictionnaires/{type}` dessert l'ensemble. Le comportement de chaque table est déclaré en données dans `Models\Dictionary::TYPES` (table, champs, champs obligatoires, étiquettes, listes de référence), et non dupliqué dans le code.
* **Opérations :** listage, ajout, modification, suppression. L'ajout et l'édition passent par une **unique modale `modal-blur`** dont le titre bascule entre « Ajouter » et « Modifier » ; la suppression fait l'objet d'une confirmation Tabler. Aucune utilisation de `alert()`, `confirm()` ou `prompt()`.
* **Déclenchement de la modale :** les deux boutons d'entrée — « Ajouter » dans l'en-tête de carte et « Éditer » dans chaque ligne — portent `data-bs-toggle="modal"` et `data-bs-target="#modal-dictionnaire"`, et s'appuient donc sur l'API déclarative de Bootstrap. Seule la ligne éditée porte le marqueur supplémentaire `data-edition-dictionnaire`, qui permet à l'écouteur `show.bs.modal` de distinguer les deux entrées via `relatedTarget` et de recopier les valeurs de la ligne dans le formulaire. Le bouton « Éditer » ne doit jamais piloter la modale par une instanciation JavaScript manuelle : deux chemins de déclenchement divergeraient.
* **Onglets de table :** la barre de navigation entre tables s'appuie sur `nav nav-underline`, seule variante de Tabler qui produise le liseré inférieur (`.nav-underline .nav-link.active { border-bottom-color: currentcolor }`). Le type courant est signalé par la classe `active` posée **à la fois** sur `nav-item` et sur `nav-link`, complétée par `aria-current="page"`. La classe `nav-borders` n'existe pas dans la feuille Tabler livrée : elle ne produisait aucun liseré.
* **Icônes :** Font Awesome exclusivement, servies par `Core\Icon` et son sous-ensemble embarqué. Aucun SVG inline.
* **Dépendances :** un modèle exige une marque existante — le menu déroulant ne propose que les marques créées. Une catégorie de type d'intervention hors liste fermée est ramenée à « Autre ».
* **Intégrité :** la suppression d'une entrée encore référencée par un véhicule ou un entretien est refusée, avec un message explicite nommant la cause.
* **Accès :** niveau `administration`, et entrée de navigation « Référentiels » dans la barre supérieure — sans elle l'écran n'était atteignable que par saisie d'URL.

### 4.12 Documentation utilisateur et registres

Les quatre registres — **Guide utilisateur**, **Journal des modifications**, **Fonctionnalités**, **Recette QA** — sont des écrans applicatifs à part entière, et non des fichiers HTML diffusés en bloc.

* **Rendu dans le layout général.** `Controllers\DocsController` rend le registre demandé via `views/docs/index.php`, dans le gabarit `row g-4` du §4.11 : navigation des quatre registres à gauche (`col-lg-3`, `list-group`, `aria-current="page"` sur l'entrée courante) et document à droite (`col-lg-9`), le tout dans une `.card` à en-tête et pied de page. La barre supérieure, l'en-tête et le pied de page de l'application sont donc présents, comme sur les autres écrans. Ces pages ne doivent plus être servies par `readfile()` : un registre qui s'ouvre hors du layout perd la navigation, le fil d'Ariane visuel et les liens de pied de page.
* **Source unique de description.** `Models\Registre::DOCUMENTS` déclare les quatre documents (fichier Markdown, libellé court, titre, glyphe, résumé). Le contrôleur et le script de génération lisent tous deux cette table : un titre, un libellé ou une icône ne peut donc pas diverger entre la page vue dans l'application et le fichier compilé.
* **Moteur de rendu partagé.** `Core\Markdown` convertit le sous-ensemble Markdown utilisé. Il a été extrait de `scripts/build_docs.php` précisément pour être partagé : `scripts/build_docs.php` l'utilise pour produire les vues HTML autonomes exigées par le §5, `DocsController` pour composer la page. Deux implémentations distinctes seraient une garantie de dérive.
* **Conversion à la demande, sans cache.** `docs/` est hors de la racine servie par Apache ; le Markdown est donc converti au moment de la requête. Le coût mesuré est de 0,5 ms (guide) à 3,5 ms (recette), ce qui ne justifie aucune copie intermédiaire susceptible de périmer.
* **Pied de page.** Le pied de page lie les quatre registres par `/docs/{cle}` ; `/docs` sans registre redirige vers le guide utilisateur.
* **Vues HTML autonomes.** `php scripts/build_docs.php` produit `docs/user_guide.html`, `changelog.html`, `features.html` et `qa_recette.html`, documents complets ouvrables hors application. Le contenu produit est identique à celui affiché dans l'application, le moteur étant partagé.
* **Styles du corps.** Le HTML produit par le moteur est nu : il ne porte pas les classes utilitaires que Tabler applique à ses propres composants. La classe `.markdown` de `public/assets/css/flotteo.css` en rétablit la présentation (titres, listes, code, citations, tableaux), sans quoi un `<h2>` s'afficherait avec la taille par défaut du navigateur.

### 4.13 Utilisateurs — cartes de profil et avatars

#### A. Présentation

* **Grille de cartes :** une carte par compte dans `row row-cards` (`col-md-6 col-lg-4`) — trois cartes par ligne à partir de 1200 px, deux à partir de 768 px, une en dessous. Chaque carte porte un bandeau de teinte par rôle (`card-status-top` avec `bg-purple-lt` / `bg-blue-lt` / `bg-secondary-lt`), un avatar `avatar-xl avatar-rounded`, le nom, l'adresse email, le badge de rôle, l'état, la date de création et les actions.
* **Actions limitées à l'essentiel :** seule la carte du compte connecté est dépourvue du bouton « Supprimer » — on ne peut pas se supprimer soi-même. **Les boutons « Email » et « Call » de la maquette de référence sont proscrits** : ils ouvriraient un client de messagerie ou un composeur téléphonique, hors du périmètre d'un écran d'administration.
* **Modales uniquement :** l'édition passe par `data-bs-toggle="modal"` sur la modale Tabler ; la suppression par l'attribut `data-confirmer`, traité par `app.js`. Aucun `alert()`, `confirm()` ni `prompt()`.
* **Icônes :** Font Awesome via `Core\Icon` exclusivement. Les trois icônes SVG inline que portait cette vue — ajout, crayon, corbeille — sont supprimées.

#### B. Avatar

* **Dépôt :** JPG, PNG ou WEBP, 8 Mo maximum, à la création comme à l'édition. Le formulaire porte `enctype="multipart/form-data"` — sans lui le fichier n'est jamais transmis.
* **Contrôle de type :** la liste `User::MIMES_AVATAR` est transmise à `Core\Upload::store()`, qui l'intersecte avec `securite.mime_autorises` : elle ne peut donc jamais élargir la configuration générale. Un PDF est refusé **avant** toute écriture, d'après le MIME réellement constaté par `finfo` — jamais d'après l'extension annoncée.
* **Stockage :** `public/uploads/avatars/`, nom tiré au hasard (`bin2hex(random_bytes(16))`) et extension déduite du MIME constaté. Aucun nom d'utilisateur ne figure dans le chemin, et le dossier interdit toute interprétation de script (`public/uploads/.htaccess`).
* **Servi en direct :** l'image est référencée par `Core\Url::upload()`, qui déduit le préfixe de base comme le reste de l'application. Contrairement aux pièces jointes d'incident — privées, servies en téléchargement contrôlé par `IncidentController` — un avatar est une image affichée dans la page.
* **Repli par initiales :** `User::initiales()` produit les initiales affichées dans le cercle à défaut d'image. La coupure suit les espaces, les tirets et les apostrophes : « Jean-Pierre Dupont » donne JD, « Jean-Pierre » donne JP. Un nom d'un seul mot donne sa première lettre — « Marc » donne M, et non MA, qui se lirait comme une faute. La même règle est implémentée côté client, qui ne s'en sert que si la vue n'a pas fourni d'initiales.
* **Cycle de vie :** remplacer un avatar supprime l'ancien fichier ; supprimer un compte supprime le sien, le nom étant relu **avant** la suppression de la ligne — la valeur ne serait plus récupérable ensuite. Un fichier déposé puis non retenu est retiré du disque plutôt qu'orphelin.
* **Incohérence levée à la volée :** l'interface masque l'option de suppression dès qu'un fichier est choisi. Un dépôt l'emporte systématiquement sur la case à cocher, côté serveur comme côté client : la logique est donc déterministe dans les deux langages, et la contradiction ne peut pas être exprimée par l'utilisateur.
* **Aperçu :** `public/assets/js/utilisateurs.js` lit le fichier choisi par `FileReader` et l'affiche dans la modale avant enregistrement. Un format refusé est signalé dans l'aide sous le bouton — jamais par une alerte native — et l'aperçu revient à l'état précédent.
* **L'avatar ne dépend pas du module Profil :** `Core\Database::update()` écrit toutes les clés fournies, `null` compris. Inclure `avatar` dans `User::update()` effacerait donc l'image à chaque enregistrement de profil ; son traitement est isolé dans `User::setAvatar()`.

---
---

## 5. Arborescence Cible Recommendée

```
flotteo/
├── config/
│   ├── database.php
│   └── config.php
├── core/
│   ├── Database.php
│   ├── Router.php
│   └── Controller.php
├── controllers/
│   ├── AuthController.php
│   ├── DashboardController.php
│   ├── VehicleController.php
│   ├── MaintenanceController.php
│   └── AdminController.php
├── models/
│   ├── User.php
│   ├── Vehicle.php
│   ├── Maintenance.php
│   ├── Incident.php
│   └── Stat.php
├── views/
│   ├── layout/
│   │   ├── header.php
│   │   ├── footer.php
│   │   └── modals.php
│   ├── auth/
│   ├── dashboard/
│   ├── vehicles/
│   └── admin/
├── public/
│   ├── index.php
│   ├── .htaccess
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── tabler/
│   └── uploads/
├── docs/
│   ├── changelog.md
│   ├── changelog.html
│   ├── features.md
│   ├── features.html
│   ├── qa_recette.md
│   └── qa_recette.html
└── cahier_des_charges.md
```