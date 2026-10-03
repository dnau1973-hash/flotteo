# Journal des modifications — Flotteo

Format inspiré de *Keep a Changelog*. Les registres `changelog.md` et `changelog.html`
doivent être synchronisés à chaque livraison.

---

## [1.2.0] — 2026-10-03

### Évolutions

#### Page Utilisateurs : cartes de profil Tabler.io et avatars

* **La liste tabulaire devient une grille de cartes**, sur le modèle des cartes de
  profil Tabler : `row row-cards` en `col-md-6 col-lg-4`, donc trois comptes par
  ligne à partir de 1200 px, deux à partir de 768 px, un en dessous. Chaque carte
  porte un bandeau de teinte selon le rôle (`card-status-top`), un avatar
  `avatar-xl avatar-rounded`, le nom, l'email, le badge de rôle, l'état et la date
  de création. Mesuré au rendu : 3 + 1 cartes par ligne à 1920 et 1440 px, 2 + 2 à
  768 px, 1 + 1 + 1 + 1 à 500 px.
* **Boutons « Email » et « Call » retirés.** La maquette de référence les propose ;
  ils ouvriraient un client de messagerie ou un composeur téléphonique, hors du
  périmètre d'un écran d'administration. Seules les actions **Éditer** et
  **Supprimer** subsistent, par modales Tabler — `data-bs-toggle="modal"` pour la
  première, `data-confirmer` pour la seconde. La carte du compte connecté est
  dépourvue de « Supprimer » : on ne peut pas se supprimer soi-même.
* **Trois icônes SVG inline supprimées** (ajout, crayon, corbeille) au profit de
  Font Awesome via `Core\Icon`, conformément au §3 des règles. La vue ne contient
  plus aucun `<svg>`, et plus aucun script inline : la logique est passée dans
  `public/assets/js/utilisateurs.js`, chargée par `useScript()`.

### Corrections

#### Le bouton « Éditer » de la page Utilisateurs ne répondait pas

* Même défaut que sur la page Référentiels, non corrigé ici : le bouton portait
  `data-edition-utilisateur` mais **ni `data-bs-toggle="modal"` ni
  `data-bs-target`**. Aucun `show.bs.modal` n'était émis, l'écouteur de
  préremplissage ne s'exécutait pas — le clic n'avait aucun effet et aucune erreur
  console. Les deux attributs ont été ajoutés, et le discriminant du listener est
  désormais `data-edition-utilisateur` : `data-bs-target`, qui doit figurer sur les
  deux boutons depuis qu'ils déclenchent la même modale, ne distingue plus rien.

### Ajouts

#### Avatar de profil

* **Dépôt d'image** à la création et à l'édition : JPG, PNG ou WEBP, 8 Mo maximum.
  Le formulaire porte `enctype="multipart/form-data"` — sans lui le fichier n'est
  jamais transmis, un échec silencieux qui se serait manifesté par un avatar
  toujours vide.
* **Contrôle de type renforcé.** `Core\Upload::store()` accepte désormais un
  sous-ensemble de MIME, **intersecté avec `securite.mime_autorises`** : la liste
  ne peut donc jamais élargir la configuration générale. `User::MIMES_AVATAR` ne
  retient que `image/jpeg`, `image/png` et `image/webp`, de sorte qu'un PDF ne peut
  pas être déposé comme avatar. La décision porte sur le MIME réellement constaté
  par `finfo`, jamais sur l'extension annoncée.
* **Stockage** dans `public/uploads/avatars/`, nom tiré au hasard et extension
  déduite du MIME constaté ; aucun nom d'utilisateur ne figure dans le chemin, et
  `public/uploads/.htaccess` interdit toute interprétation de script.
* **Servi en direct** via `Core\Url::upload()`, nouveau helper qui déduit le
  préfixe de base comme le reste de l'application. Les pièces jointes d'incident
  restent, elles, servies en téléchargement contrôlé : un avatar est une image
  affichée dans la page, un document d'incident est privé.
* **Repli par initiales** via `User::initiales()` : « Sarah Kellner » donne SK,
  « Jean-Pierre Dupont » donne JD — la coupure suit espaces, tirets et apostrophes.
  Un nom d'un seul mot donne sa première lettre : « Marc » donne M, et non MA, qui
  se lirait comme une faute.
* **Aperçu avant enregistrement** par `FileReader` dans la modale. Un format
  refusé est signalé dans l'aide sous le bouton, **jamais par une alerte**, et
  l'aperçu revient à l'état précédent.
* **Cycle de vie complet :** remplacer un avatar supprime l'ancien fichier,
  supprimer un compte supprime le sien, et un fichier déposé puis non retenu est
  retiré du disque plutôt qu'orphelin.

### Corrections

#### Trois fragilités corrigées au passage

* **Un avatar était effacé à chaque enregistrement de profil.** `Core\Database::update()`
  écrit toutes les clés fournies, `null` compris : inclure `avatar` dans
  `User::update()` — dont se sert `AuthController` pour le module Profil — aurait
  fait disparaître l'image à chaque sauvegarde. Le traitement de l'avatar est
  isolé dans `User::setAvatar()`, et `User::update()` retrouve sa signature
  d'origine : le module Profil n'a nécessité aucune adaptation.
* **La suppression du compte ne retirait pas l'image.** Le nom du fichier est
  désormais relu **avant** la suppression de la ligne ; après, la valeur n'est plus
  récupérable et le fichier restait orphelin sur le disque.
* **Configuration morte retirée.** `uploads.url_base` valait `/flotteo/uploads`,
  un préfixe figé — exactement ce qu'INC-001 avait éliminé du reste de
  l'application. La clé était déclarée et **utilisée nulle part** ; elle est
  supprimée au profit de `Core\Url::upload()`. Vérifié : `Url::detect()` déduit
  bien `''`, `/flotteo` et `/apps/flotteo/public` selon le front controller, et
  aucune référence ne subsiste.
* **Contradiction impossible à exprimer.** Un dépôt l'emporte sur la case
  « supprimer l'image », côté serveur comme côté client, et l'interface masque
  l'option dès qu'un fichier est choisi. Les marqueurs d'aperçu sont aussi effacés
  au passage en mode création : sans cela, un format refusé après une édition
  réaffichait l'avatar de l'utilisateur précédent dans le formulaire de nouvel
  utilisateur.

### Vérifications

* **Barrière de type** : 6 cas éprouvés — PDF et GIF refusés comme avatar, PNG
  accepté, sous-ensemble vide refusant tout, un type absent de la configuration
  refusé même s'il est demandé explicitement, et PDF toujours possible sur le
  chemin pièce jointe. Aucune non-conformité.
* **JavaScript** rejoué sous jsdom : édition de deux comptes (avatar enregistré et
  compte sans image), bouton d'ajout et affichage programmatique, refus d'un PDF
  avec retour à l'aperçu précédent, acceptation d'un PNG. Sept cas conformes.
* **Grille** mesurée sous Chrome headless de 500 à 1920 px : aucun débordement
  horizontal, avatar à 5 rem et arrondi sur toutes les largeurs.
* **Non-régression** : page Référentiels (préremplissage jsdom, onglets), page
  Paramètres, quatre pages de registres ; `Upload::store()` conserve un appel à
  deux arguments pour les pièces jointes d'incident.

### Migration

Une installation existante doit ajouter la colonne manquante :

```sql
ALTER TABLE utilisateurs ADD COLUMN avatar VARCHAR(64) NULL DEFAULT NULL AFTER actif;
```

---

## [1.1.3] — 2026-10-03

### Corrections

#### Le layout occupe enfin toute la largeur de l'écran

* **Le contenu était plafonné.** Le corps de page et le pied de page utilisaient
  `container-xl`, dont le `max-width` atteint 1320 px à partir de 1400 px de
  fenêtre : au-delà, la page restait centrée avec de larges marges vides de part et
  d'autre. Les deux passent en `container-fluid`, seule variante Tabler dépourvue
  de plafond. Le plafond de `.layout-boxed .page` ne pesait pas : la classe
  `layout-boxed` n'est employée nulle part dans le projet.

* **Marges latérales.** `px-4 px-lg-5` — 1,5 rem, puis 2 rem à partir de 992 px —
  appliqué au corps et au pied de page. Ces utilitaires portent `!important` et
  l'emportent donc sur le padding par défaut de `.container-fluid`, sans une
  ligne de CSS maison. **La barre supérieure reçoit le même padding** : sans cela,
  l'identité et la première entrée de menu seraient désalignées du bord des
  cartes. Vérifié au rendu : 32 px de chaque côté au-dessus de 992 px, 24 px en
  dessous, sur la barre comme sur le corps.

* **La barre causait un défilement horizontal — défaut préexistant, aggravé.**
  Mesuré sous Chrome headless, la page débordait de **101 px à 1024 px** avant
  ce changement, et de **117 px après** : la pleine largeur et le padding accru
  avaient réduit l'espace disponible sans traiter la cause. Les sept modules,
  l'identité et le menu utilisateur exigent environ 1130 px, et la barre impose
  `flex-nowrap` sur sa rangée interne : entre 992 et 1200 px, `navbar-expand-lg`
  maintenait une barre à une ligne plus large que la fenêtre. La barre passe en
  **`navbar-expand-xl`** et se replie donc sous 1200 px. `flotteo.css` suit avec
  `@media (max-width: 1199.98px)` au lieu de `991.98px`. Après correction, aucun
  débordement de 500 px à 1920 px.

* **Repérage du module courant préservé.** Le liseré d'onglet actif suit le point
  de rupture via `.navbar-expand-xl .nav-item.active:after` : filet de 2 px
  vérifié de 1200 à 1920 px. Sous 1200 px, le menu étant replié, Tabler ne dessine
  aucun repère — la variante `.navbar-collapse .nav-item.active:after` ne fixe pas
  de `content` et ne produit rien. **Ce comportement n'est pas nouveau** : il a été
  mesuré à l'identique avec `navbar-expand-lg` sous 992 px. Le module courant reste
  identifiable par son gras (600 contre 400), que porte
  `.barre-superieure .navbar-nav .nav-link.active`, lui aussi vérifié.

* **Tableaux et graphiques.** Un tableau à 12 colonnes a été mis à l'épreuve dans
  le layout réel : il remplit la largeur disponible à 1920 et 1440 px, puis défile
  dans son `.table-responsive` (`overflow-x: auto`) de 1280 px à 500 px, sans
  jamais faire déborder la page. Les graphiques ApexCharts ne déclarent aucune
  largeur et suivent celle de leur conteneur : mesurés de 866 px à 1920 px de
  fenêtre jusqu'à 418 px à 1024 px, sans débordement. Toutes les tables du projet
  étaient déjà dans un `.table-responsive`.

* **Vérifications.** Chrome headless, six largeurs (1920, 1440, 1280, 1024, 768,
  500), trois pages rendues dans le layout complet — Référentiels, Paramètres et
  le registre Fonctionnalités, qui compte à lui seul 10 tableaux. Aucune page en
  débordement. Classes exclusivement natives Tabler/Bootstrap ; `container-xl` a
  disparu du layout.

---

## [1.1.2] — 2026-10-03

### Corrections

#### Les quatre registres documentaires sortent du HTML brut pour le layout général

* **Les pages s'ouvraient hors de l'application.** `/docs/user_guide`,
  `/docs/changelog`, `/docs/features` et `/docs/qa_recette` renvoyaient le fichier
  compilé **en bloc** via `readfile()` : le visiteur atterrissait sur un document
  autonome, avec sa propre barre verticale et son propre pied de page — donc
  **sans la barre supérieure de Flotteo, sans son en-tête, sans son pied de page et
  sans son fil de navigation**. Les quatre liens du pied de page sortaient
  précisément de l'application pour proposer un retour manuel.

* **Rendu dans le layout.** `DocsController` rend désormais
  `views/docs/index.php`, sur le gabarit `row g-4` déjà en usage pour la page
  Paramètres : navigation des quatre registres à gauche (`col-lg-3`, `list-group`,
  `aria-current="page"` sur l'entrée courante), document à droite (`col-lg-9`), le
  tout dans une `.card` à en-tête et pied de page. `readfile()` et la liste blanche
  `DocsController::AUTORISES` ont disparu du contrôleur — le garde-fou n'est plus
  un chemin de fichiers mais un contrôle de clé, plus sûr.

* **Moteur de rendu partagé (`Core\Markdown`).** Les huit fonctions de conversion
  Markdown vivaient dans `scripts/build_docs.php`, donc **inaccessibles à
  l'application** : impossible d'y rendre un registre sans dupliquer le moteur,
  c'est-à-dire sans garantir deux implémentations divergentes. Elles sont extraites
  telles quelles dans `Core\Markdown`, consommé par le script **et** par le
  contrôleur. L'identité du rendu a été vérifiée octet par octet sur les quatre
  registres avant et après extraction.

* **Description centralisée (`Models\Registre`).** Les quatre documents étaient
  décrits à deux endroits — `REGLES` dans le script, `AUTORISES` dans le
  contrôleur — sans titre ni icône, donc sans risque de divergence de part et
  d'autre. Le tableau `Models\Registre::DOCUMENTS` porte désormais fichier,
  libellé, titre, glyphe et résumé ; les deux consommateurs le lisent. Le schéma
  reprend celui de
  `Models\Dictionary::TYPES`, conformément au principe de déclaration en données.

* **Icônes.** Les quatre pages portaient des **emoji** (📖 📋 🗂️ ✅) à la fois dans
  leur barre et dans leur titre, en violation du §3 des règles qui impose Font
  Awesome. Remplacés par `fa-book`, `fa-file-lines`, `fa-list` et `fa-check`,
  tous déjà présents dans le sous-ensemble embarqué.

* **Styles du corps.** La classe `markdown` portée par le conteneur du document
  **n'existait pas** dans `public/assets/css/flotteo.css` — elle était déjà inerte
  dans les vues autonomes. Les titres, listes, codes, citations et tableaux
  s'affichaient donc avec les styles par défaut du navigateur, sans la
  présentation Tabler attendue. La feuille applicative définit désormais `.markdown`
  à partir des variables `--tblr-*`, bornant le débordement des tableaux larges et
  des longs blocs de code.

* **Entrée sans registre.** `/docs` redirected vers `/dashboard` en « Document
  introuvable » ; la route redirige désormais vers le guide utilisateur, qui est
  la destination du lien imposé par le §3 des règles. Quatre titres de page ont
  été déclarés dans `header.php`, un par registre.

* **Vérifications.** Routage éprouvé pour `/docs` et les quatre registres
  (`{doc}` correctement extrait) ; clés inconnues et tentatives de traversée
  (`hack`, `../../../config/config`, `user_guide/../changelog`) refusées par
  `Models\Registre`. Les quatre pages rendues dans le layout complet comportent
  chacune un seul `<!doctype>`, un seul `<html>`, la barre supérieure, le pied de
  page, deux `.card` et quatre entrées de navigation dont une active. Les liens du
  pied de page pointent bien vers `/docs/{cle}`. Aucun `readfile` résiduel.

---

## [1.1.1] — 2026-10-03

### Ajouts

#### Module « Échéances » — l'aperçu des fins de contrat devient une page

* **Extraction.** Le bloc « Aperçu des échéances » quittait la page Paramètres
  pour une page autonome `/echeances` : `controllers/EcheanceController.php` et
  `views/echeances/index.php`. L'écran d'administration était le lieu impropre —
  l'échéancier y occupait une place disproportionnée et n'était atteignable que
  du rôle `administration`.
* **Profil d'accès abaissé en lecture.** Le graphique des restitutions prévues du
  tableau de bord exposait déjà ces mêmes données à tous les lecteurs ; l'écran
  dédié aligne le niveau d'accès sur ce constat.
* **Onglet de navigation** « Échéances » inséré entre « Incidents » et
  « Paramètres », icône `fa-calendar-days`, avec la détection d'item actif déjà en
  place — aucune adaptation n'a été nécessaire, la comparaison portant sur le
  chemin complet.
* **Synthèse par cartes KPI** : échéances surveillées, sous 30 jours, sous
  90 jours, prochaine échéance.
* **Filtrage par palier sans JavaScript** : chaque palier est une URL porteuse du
  paramètre `palier`, l'état courant étant signalé par la classe `active` et
  `aria-current="page"`. Chaque filtre affiche son effectif.
* **Préparation des données remontée dans le contrôleur** (aplatissement des
  groupes, tri par urgence, effectifs et compteurs) : la vue ne fait plus que
  l'échappement et la mise en forme.
* **Nettoyage de la page Paramètres** : 82 lignes retirées (230 → 148), le
  tableau, son état vide et la logique de tri devenue morte incluse ; plus aucun
  `apercu` ni `echeances` dans la vue, et `ParamController` ne transmet plus
  `AlertService::echeancier()`.

### Améliorations

#### Référentiels atteignables, icônes conformes et guide utilisateur

* **Le bloc de réglages SMTP ne se déployait pas.** Sur `/admin/parametres`, le
  formulaire SMTP était bien présent dans le HTML, mais le bloc restait masqué et
  **ne se déployait pas au changement de mode d'envoi**. Cause : `ParamController`
  déclarait `useScript('js/parametres.js')` alors que le pied de page applique déjà
  le préfixe (`Url::asset('js/' . $script)`). La page demandait donc
  `/assets/js/js/parametres.js` et recevait un **404** ; le script qui pilote le
  basculement n'était jamais exécuté. Les tests de rendu ne l'avaient pas vu : ils
  vérifiaient le HTML produit, jamais l'URL d'asset réellement demandée. Nom corrigé
  en `parametres.js`, soit `/assets/js/parametres.js`, servi en 200.
  Une invite a été ajoutée sous le sélecteur pour préciser que les réglages
  s'utilisent uniquement en mode « Serveur SMTP ».

* **Le masquage du bloc SMTP a été supprimé.** Le bloc dépendait désormais du
  JavaScript : sans script (cache navigateur, extension, proxy, 404), la page
  s'affichait sans aucun réglage SMTP et l'utilisateur ne pouvait pas les saisir.
  Le bloc est maintenant **toujours rendu visible**, quel que soit le mode affiché,
  et `parametres.js` ne pilote plus que le test d'envoi. Le mode `mail` est
  simplement indiqué comme n'utilisant pas ces valeurs. Un formulaire visible par
  défaut reste soumettable et lisible même si le JavaScript est indisponible.

### Corrections

#### Dictionnaire — bouton « Éditer » inerte et liseré d'onglet absent

* **Le bouton « Éditer » ne déclenchait rien.** Le bouton de chaque ligne
  (dictionnaire.php:73) portait `data-edition-dictionnaire` mais **ni
  `data-bs-toggle="modal"` ni `data-bs-target`**. L'API déclarative de Bootstrap
  n'ayant rien à quoi se raccrocher, aucun `show.bs.modal` n'était émis et
  l'écouteur de préremplissage ne s'exécutait jamais : le clic n'avait aucun
  effet, sans message d'erreur. Les deux attributs ont été ajoutés, ce qui raccorde
  le bouton au même mécanisme que le bouton « Ajouter » et fournit le
  `relatedTarget` dont le préremplissage a besoin.

* **Le discriminant de mode était devenu faux.** Le listener distinguait l'ajout de
  la modification par `declencheur.hasAttribute('data-bs-target')` — attribut que
  porte désormais **aussi** le bouton « Éditer », puisqu'il est précisément ce qui
  déclenche la modale. Le test aurait renvoyé les deux boutons en mode création :
  même après correction de l'URL, le formulaire se serait ouvert vide. Le
  discriminant est désormais `data-edition-dictionnaire`, présent sur le seul bouton
  « Éditer ». La pose du marqueur par la vue et sa consommation par le script
  demeurent ainsi le seul point de contact entre les deux.

* **Le liseré d'onglet ne pouvait pas s'afficher.** La barre des six tables portait
  `nav nav-borders` : **`nav-borders` n'existe pas dans la feuille Tabler livrée**
  (vérifié dans `public/assets/tabler/css/tabler.min.css`), et ne produit donc
  aucun style. La classe `active` était pourtant bien posée sur le `nav-link` du type
  courant — elle n'avait aucun effet faute de règle applicable. La barre bascule sur
  `nav nav-underline`, seule variante de Tabler qui dessine un liseré inférieur
  (`.nav-underline .nav-link.active { border-bottom-color: currentcolor }`, épaisseur
  `0.125rem`). Conformément à la convention appliquée à la barre supérieure (§4.7),
  la classe `active` est désormais portée **à la fois** par le `nav-item` et le
  `nav-link`, avec `aria-current="page"` en complément.

* **Vérifications.** Rendu de la page complète (`View::render` avec layout) sur
  `/admin/dictionnaires/modeles` : bouton Éditer porteur des trois attributs, six
  onglets dont exactement un actif sur les deux éléments. Logique de préremplissage
  rejouée sous jsdom sur le HTML servi — quatre cas conformes : édition de deux
  lignes distinctes (identifiant, titre « Modifier — Modèles », `marque_id` résolu
  vers la bonne option du menu déroulant, libellé recopié), bouton « Ajouter » et
  affichage programmatique ouvrant tous deux un formulaire vide en mode création.
  Aucune alerte native : `alert()`, `confirm()` et `prompt()` absents de la vue, la
  suppression passant par `flotteoConfirmer()` de `app.js`.

* **Le générateur de registres tronquait deux incidents.** Constatée en
  régénérant les vues HTML dans le cadre de la clôture ci-dessus. La découpe des
  cellules (`scripts/build_docs.php:246`) reposait sur `explode('|', $ligne)` sans
  traiter les pipes littéraux échappés : toute cellule citant une expression
  régulière comportant un `\|` — c'est-à-dire écrit `\|` — était coupée en deux colonnes.
  Deux lignes du registre des incidents en souffraient, `INC-013` perdait la fin de
  sa description et **`INC-016` perdait sa colonne « Correctif » entière**. La
  découpe passe désormais par `preg_split('/(?<!\\\\)\|/', …)` suivi d'un
  `str_replace('\\|', '|', …)` : un pipe échappé ne délimite plus de colonne et
  redevient un littéral dans la cellule. Les 27 lignes du registre sont vérifiées à
  5 colonnes après régénération.

* **Les référentiels étaient inatteignables.** Les six tables de paramétrage
  (marques, modèles, entités propriétaires, organismes loueurs, lieux
  d'exploitation, types d'intervention) disposaient déjà d'un CRUD complet, mais
  `/admin/dictionnaires` n'était référencé par **aucun lien** : la barre supérieure
  ne contenait qu'une correspondance de titre. L'écran ne s'atteignait que par saisie
  d'URL, alors que `features.md` le présentait comme un module de la barre. Entrée
  **« Référentiels »** ajoutée entre « Échéances » et « Paramètres », icône `fa-list`,
  niveau `administration` — la détection d'item actif, qui compare par préfixe et
  retient la correspondance la plus longue, la marque correctement y compris sur les
  sous-chemins `/admin/dictionnaires/marques`.
* **Icônes : passage de Font Awesome.** La vue `admin/dictionnaire.php` contenait
  encore **trois SVG inline** — ajouter, modifier, supprimer — en violation du §3 des
  règles de développement, alors que le reste de l'application avait déjà été converti.
  Remplacés par `fa-plus`, `fa-pen` et `fa-trash-can` via `Core\Icon`. Deux
  `aria-label` complètent les boutons, dont la cible n'était qu'implicite.
* **Échappement JavaScript.** Le titre de la modale était injecté avec
  `addslashes()`, inadapté à un contexte JavaScript et incohérent avec le
  `json_encode()` employé dix lignes plus loin. Remplacé par `json_encode()` assorti
  de `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT`, qui rend explicite
  la neutralisation d'une éventuelle `</script>` — jusqu'ici assurée par l'échappement
  implicite du `/`. La modale résiste aussi à un affichage sans déclencheur.
* **Guide utilisateur.** `docs/user_guide.md` et `docs/user_guide.html` étaient
  **absents** alors que le §5 des règles les exige et que le §6.4 en fait une étape
  du protocole de clôture — la clôture était donc impossible à franchir. Le guide
  couvre les écrans, les trois niveaux d'accès, les deux sections de Paramètres, les
  référentiels, et un tableau de diagnostic des pannes courantes. Déclaré dans
  `scripts/build_docs.php` et dans la liste blanche de `DocsController` — `docs/` étant
  hors de la racine servie par Apache, la diffusion passe nécessairement par le
  contrôleur. Lien ajouté en tête du pied de page.

#### Navigation verticale et section « Messagerie » sur « Paramètres système »

* **Organisation en sections.** L'écran adopte le modèle Tabler « Settings » :
  navigation verticale à gauche (`col-lg-3`, `list-group`) et contenu contextuel à
  droite (`col-lg-9`). La section est portée par la route
  `/admin/parametres/{section}`, l'état courant étant signalé par `active` et
  `aria-current="page"` ; `/admin/parametres` sert la section par défaut. Deux
  titres de page ont été ajoutés à la table de `header.php`, sans quoi les
  sous-chemins se seraient affichés « Administration ».
* **Envoi unique par section.** La contrainte qui motivait l'attribut HTML5
  `form` a disparu avec sa cause : la répartition des clés entre sections
  est désormais déclarée dans `ParamController::SECTIONS` et `save()` n'écrit que
  les clés de la section visée. Un envoi partiel ne peut donc plus neutraliser une
  autre section. Chaque section conserve un formulaire propre — les formulaires
  imbriqués restent proscrits.
* **Nouveau mode d'envoi SMTP.** `mail_transport` choisit entre le frontal `mail()`
  et un serveur SMTP externe. `Core\SmtpClient` implémente le dialogue sans aucune
  bibliothèque tierce, conformément à la règle d'or : `STARTTLS` (avec nouvel
  `EHLO` sur la session chiffrée, RFC 3207), TLS direct pour le port 465,
  `AUTH PLAIN`, et vérification du certificat du serveur. Un mode de chiffrement
  inconnu est rejeté plutôt que de retomber en texte en clair. Les erreurs sont
  traduites en français actionnable — un certificat auto-signé est nommé comme
  tel au lieu d'un « Unknown error ».
* **Test d'envoi.** Action POST JSON calquée sur `/api/install/tester`, qui
  éprouve le serveur avec les valeurs **actuellement saisies** : la configuration
  se valide avant d'être persistée. Le mot de passe seul retombe sur la valeur
  enregistrée, puisqu'il n'est jamais renvoyé au navigateur.
* **Mot de passe.** Jamais renvoyé au navigateur. Un champ laissé vide conserve la
  valeur enregistrée — dont l'existence est signalée par une invitation sur le
  champ — et son effacement passe par une case à cocher explicite.
* **Base.** Les six paramètres `mail_transport`, `smtp_host`, `smtp_port`,
  `smtp_chiffrement`, `smtp_user` et `smtp_password` ont été ajoutés aux trois
  sources qui portent le jeu de référence : `sql/schema.sql`, `sql/demodata.sql`
  et `scripts/seed/Data.php`. Le projet n'a pas de système de migration ; une base
  déjà installée doit recevoir l'`INSERT … ON DUPLICATE KEY` correspondant.

#### Refonte de la mise en page de « Paramètres système »

* **Grille.** Les trois blocs de réglage occupent désormais les 12 colonnes avec
  une gouttière uniforme (`row g-3`, soit 1rem en X et en Y) : **Paramétrage**
  sur `col-lg-6`, **Destination des notifications** et **Exécution et
  automatisation** sur `col-lg-3` chacun. Les trois cartes portent `h-100` et
  sont filles directes de leur colonne — Bootstrap étirant les colonnes d'une
  ligne flex au niveau de la plus haute, les deux cartes de droite ont donc
  exactement la même hauteur. Uniquement des classes Tabler/Bootstrap.
* **Point d'attention — envoi unique.** Scinder la page en trois blocs
  impliquait trois formulaires, or `ParamController::save()` parcourt les six
  clés et écrit chacune : un formulaire partiel aurait **effacé les réglages non
  soumis**. Les six champs restent donc rattachés à un formulaire unique par
  l'attribut HTML5 `form`, ce qui évite tout formulaire imbriqué — invalide — tout
  en laissant chaque carte libre de sa structure interne.
* **Contenu des blocs.**
  * Paramétrage : l'interrupteur *Alertes actives* est en-tête, son libellé
    bascule entre « Alertes actives » et « Alertes suspendues » ; les trois seuils
    sont en ligne sur `col-4` avec l'unité « j » accolée ; l'enregistrement passe
    en `card-footer`.
  * Destination des notifications : les deux adresses en `input-group` avec
    icône, et un message d'avertissement quand les alertes sont suspendues.
  * Exécution et automatisation : le bouton d'envoi plein largeur et la commande
    cron copiable en un clic, séparés par un `hr`.
* **Aperçu réorganisé.** Un tableau unique remplace les trois tableaux
  (un par palier). Les véhicules sont triés par urgence croissante — le plus
  proche d'échéance en tête, et non plus par palier — avec la colonne *Palier*,
  la colonne *Lieu* et un compteur dans l'en-tête. Le badge « reste » se colore
  désormais selon quatre seuils (rouge ≤ 30 j, orange ≤ 90 j, jaune ≤ 180 j,
  bleu au-delà) au lieu de deux.
* **États mieux explicités.** Alertes suspendues : message d'alerte dans la
  carte des destinataires. Aucune échéance : état vide conservé, sans tableau.
* **Icônes.** Le SVG inline du bouton d'envoi est remplacé par Font Awesome
  (`Core\Icon`), conformément au §3 des règles de développement.
* **Chemin cron plus robuste.** Le champ est en lecture seule et accompagné
  d'un bouton de copie ; le toast de retour passe par la pile existante, jamais
  par une alerte native.

### Corrections

#### Pile de toasts et bouton de copie (`app.js`)

* Le bouton « copier » n'avait aucun gestionnaire : il était inerte. Ajout de
  `initBoutonsCopie()`, branché sur `DOMContentLoaded`.
* La condition `navigator.clipboard && window.isSecureContext` était trop
  restrictive : `isSecureContext` peut valoir `undefined` alors que l'API est
  utilisable, ce qui faisait dégrader la copie vers le repli alors que
  l'écriture était possible. L'API n'étant de toute façon exposée qu'en
  contexte sécurisé, la seconde condition est superflue.
* `toast()` référençait le global `bootstrap` sans garde : une exception non
  rattrapée était levée dans la console si Tabler JS n'était pas chargé. La
  référence est désormais vérifiée.
* Les icônes de la pile de toasts étaient des **SVG inline** construits en
  JavaScript, en contradiction avec le §3. Elles passent par Font Awesome
  (`fa-check`, `fa-xmark`, `fa-triangle-exclamation`, `fa-circle-info`).

#### Item « Paramètres » inactif dans la barre supérieure (INC-010)

* **Symptôme :** sur `/admin/parametres`, le liseré bleu inférieur de l'item actif
  ne s'affichait pas, alors qu'il fonctionne sur les autres modules.
* **Cause :** `views/layout/header.php` ne retenait que le **premier segment** du
  chemin (`strtok(…, '/')`) pour déterminer l'entrée active. Or
  `/admin/parametres` et `/admin/utilisateurs` ont tous deux `admin` comme premier
  segment, et aucune clé de module ne porte ce nom : **aucun des deux items
  d'administration ne pouvait donc jamais recevoir la classe `active`.** La page
  « Utilisateurs » était touchée par le même défaut — « Paramètres » était
  simplement la première des deux visitée.
* **Correction :** la détection porte désormais sur le **chemin complet**, via
  `Core\Router::currentPath()` — et non plus sur un `parse_url()` manuel de
  `$_SERVER['REQUEST_URI']`. L'abstraction existante retire le préfixe de base,
  ce qui préserve la détection quand Flotteo est installé dans un sous-dossier.
  Un sous-chemin reste rattaché à son module (`/vehicules/voir` laisse
  « Véhicules » actif) ; en cas de chevauchement d'URL, la plus longue l'emporte.
* **Structure Tabler inchangée :** la classe `active` est posée sur `nav-item`
  **et** `nav-link`, avec `aria-current="page"`, exactement comme pour les autres
  modules. C'est bien `li.nav-item.active` qui porte le liseré
  (`.navbar-expand-lg .nav-item.active:after` → bordure inférieure 2 px dans
  `--tblr-navbar-active-border-color`, à partir de 992 px).
* **Titre de page :** la table de correspondance est désormais indexée sur le
  chemin complet (`/admin/parametres` → « Paramètres système »,
  `/admin/utilisateurs` → « Utilisateurs », `/admin/dictionnaires` →
  « Dictionnaires »). L'ancienne table ne connaissait que `admin`, ce qui
  affichait « Administration » pour les deux pages d'administration.

## [1.1.0] — 2026-10-02

### Ajouts

#### Assistant d'installation web (`/install`)

* **Livrable :** parcours d'installation en trois étapes, servi par le front
  controller — `controllers/InstallController.php`, `views/install/index.php`.
  Aucun `install.php` distinct dans `public/` : le bootstrap, `Core\Url`, le CSRF
  et les messages flash sont donc conservés à l'identique.
* **Étape 1 — base de données :** hôte, port, nom de base, utilisateur et mot de
  passe MySQL, validés côté serveur. Le test de connexion est un appel
  `POST /api/install/tester` qui répond en JSON (`success`, `message`, `detail`),
  jamais une redirection, afin de rester exploitable par le formulaire.
* **Étape 2 — administrateur :** nom, email, mot de passe et confirmation, avec
  jauge de robustesse purement indicative côté client. La règle serveur fait foi.
* **Étape 3 — initialisation :** affichage de l'avancement des quatre opérations
  (création de la base, application du schéma, compte administrateur, écriture de
  la configuration) avant redirection vers `/login`.
* **Service métier** : `services/Installer.php`_factorise la logique, désormais
  partagée avec la ligne de commande. `core/InstallState.php` porte l'état
  d'installation et le verrou.

#### Verrou d'installation et garde-fou de déploiement

* **Témoin `storage/install.lock`**, hors racine servie par Apache, écrit en toute
  fin d'installation réussie et en écriture atomique (`rename()`).
* **Trois états distincts** (`core/InstallState::status()`) :
  * `a_installer` — aucun verrou : toute route applicative est redirigée vers
    `/install`, ce qui interdit d'utiliser l'application sur une base vide ;
  * `installe` — verrou présent et configuration exploitable : `/install` est
    lui-même verrouillé, la réinitialisation est donc impossible par le web ;
  * `defaillant` — verrou présent mais `config/database.php` absent ou invalide :
    page **503** dédiée (`views/erreur/503.php`) plutôt que la réouverture de
    l'assistant, qui risquerait d'écraser une base déjà en service.
* **Protection contre l'écrasement** : `Services\Installer` refuse d'exécuter le
  schéma si la base contient déjà des tables, `sql/schema.sql` comportant des
  `DROP TABLE`.

#### Jeu de données de démonstration

* **Générateur de fixtures** (`scripts/seed_demo.php` + `scripts/seed/`), en
  remplacement de `sql/demodata.sql` qui était **cassé** :
  * `type_intervention_id = 7` référencé alors que le schéma n'en définit que 6 ;
  * `vehicule_id = 16` référencé alors que le parc n'en compte que 15 ;
  * aucune ligne dans `incidents_fichiers` ;
  * aucune désactivation des clés étrangères autour de la purge ;
  * **zéro immatriculation au format SIV** — le dernier groupe portait des
    lettres (`FT-101-AA`) au lieu du code département.
* **Deux sorties, une seule source de vérité.** `scripts/seed/Data.php` décrit les
  données ; l'injecteur PDO et le fichier SQL statique en découlent, ils ne
  peuvent donc pas diverger.
  * `php scripts/seed_demo.php` injecte en base (requêtes préparées, transaction).
  * `php scripts/seed_demo.php --sql` régénère `sql/demodata.sql`, exécutable
    d'un bloc avec `FOREIGN_KEY_CHECKS` désactivé puis réactivé autour de la purge.
  * `php scripts/seed_demo.php --verifier` contrôle la cohérence **sans base** :
    intégrité référentielle, valeurs d'ENUM, format SIV et cohérence du code
    département avec le lieu, monotonie des kilométrages, TVA, présence des
    paliers d'alerte. Il a détecté 10 anomalies sur la première version du jeu.
* **Comptes de démonstration** — mot de passe commun `Password123!`, haché par
  `password_hash()` en **Argon2id** avec repli bcrypt, condensat calculé à
  l'exécution : aucune valeur n'est stockée dans le dépôt, et le fichier SQL
  publié vérifie bien le mot de passe annoncé.
* **Volume** : 3 utilisateurs, 8 marques, 14 modèles, 3 entités, 4 loueurs,
  6 lieux, 6 types d'intervention, 15 véhicules, 23 opérations d'entretien,
  7 incidents, 14 pièces jointes.
* **Cas limites couverts** : sorties à 90, 180 et 270 jours exactement (les
  paliers de `parametres`), échéance dépassée avec restitution bloquée par un
  litige, restitution en retard, alerte immédiate sous 30 jours, deux véhicules
  immobilisés, un véhicule restitué, un véhicule non immatriculé, un véhicule
  loué à un client tiers.
* **Immatriculations SIV réelles** : le dernier groupe est le code département
  du lieu d'exploitation — `FT-427-75` à Paris, `EK-304-13` à Marseille,
  `HN-119-2A` en Corse (2A).
* **Apostrophes doublées** dans le SQL généré plutôt que préfixées d'une barre
  oblique : la forme normalisée reste correcte même si le serveur tourne en
  `NO_BACKSLASH_ESCAPES`.
* Une configuration `config/database.php` illisible produit désormais un message
  d'erreur clair au lieu d'une trace PHP brute.

### Injection réelle : SQL incompatible avec le schéma (INC-009)

* **Défaut trouvé à l'exécution, pas à la génération.** L'import a été refusé par
  MySQL : `INSERT INTO types_intervention` annonçait 4 colonnes pour 3 valeurs.
  `lignesValeurs()` n'itère que sur sa liste `$colonnes`, donc la constante
  `['actif' => 1]` était ignorée en silence. Mes contrôles précédents — orientés
  expressions régulières — ne pouvaient pas voir l'écart : seule l'exécution
  réelle l'a révélé.
* **Correctif** : `actif` figure désormais dans la liste des colonnes.
* **Garde-fou à la racine** : `lignesValeurs()` lève une `LogicException` si une
  constante est fournie pour une colonne absente de la liste. La classe entière
  de défauts ne peut plus se reproduire en silence. `seed_demo.php` récupère
  cette exception et affiche un message lisible, sans trace PHP.
* **Contrôle préalable à l'écriture.** Les `TRUNCATE` valident implicitement :
  un `INSERT` refusé plus tard laisse la base **à moitié vidée** — c'est
  exactement ce qui s'est produit, les tables de référentiel restant videes.
  `Injecteur::controlerSchema()` confronte désormais chaque liste de colonnes et
  le nombre de valeurs de chaque ligne à `information_schema` **avant** toute
  écriture, et interrompt sans rien modifier si le SQL est incompatible.
* **Sens opposed de deux colonnes homonymes** — relevé, non modifié :
  `vehicules.immatricule = 1` signifie « **non** immatriculé » (usage interne
  sur site fermé) tandis que `incidents.immatricule = 1` signifie
  « immatriculé ». Chaque vue est cohérente avec son propre formulaire, donc
  l'affichage est correct, mais la lecture du schéma seule induit en erreur.

### Assistant d'installation — verrouillage en état « défaillant » (INC-007)

* **Faille.** L'assistant n'était fermé que pour l'état `installe`. En état
  `defaillant` (témoin présent mais `config/database.php` illisible) aucune
  branche du garde ne s'appliquait à `/install` : **un visiteur non authentifié
  pouvait relancer l'assistant et écraser une base de données déjà en service**,
  ce que le commentaire du code affirmait précisément empêcher.
* **Correction** : l'assistant est désormais ouvert dans le seul état
  `a_installer`. La matrice des 6 cas (3 états × 2 types de route) est vérifiée.

### Font Awesome — suppression du CSS d'animation inerte (INC-008)

* 55 lignes (2 118 octets, soit un tiers du fichier) de règles d'animation
  `fa-spin`, `fa-spin-snap-*`, `fa-pulse`, `fa-shake`… referencing des
  `animation-name` **sans le moindre `@keyframes`** : strictement inertes.
* La règle `.fa-spinner` qui les suivait était de surcroît collée au bloc
  précédent (`}.fa-spinner {`, absent de retour à la ligne) et absente de
  `Core\Icon::GLYPHES`, donc non appelable.
* Aucune vue n'utilise ces classes. Bloc supprimé : `fontawesome.css` passe de
  6 355 à **4 277 octets**, et **toutes** ses règles produisent désormais un effet.
* Contrôle de synchronisation : `Core\Icon::GLYPHES` et la feuille CSS
  déclarent **37 glyphes de part et d'autre**, sans écart dans aucun sens.

### Bandeau supérieur et icônes Font Awesome

* **Barre de navigation reconçue en horizontale, sur 100 % de la largeur.**
  * Le sidebar vertical (`navbar-vertical`) est supprimé : il dupliquait les
    mêmes entrées que la barre. `Core\Auth` n'y est plus appelé qu'une fois.
  * `<header class="navbar navbar-expand-lg barre-superieure">` en `container-fluid`
    — plus aucune limite de largeur fixe. Le titre de page, jusque-là affiché
    dans la barre, devient `d-print-block` : la barre porte désormais l'identité
    et le module actif, le doublon visuel n'apportait rien.
  * Trois zones : identité à gauche, modules dans la barre, profil et
    déconnexion à droite. Repli en `collapse` sous le point de rupture `lg`.
* **Icônes Font Awesome, auto-hébergées.** `.antigravityrules` §1.4 interdisait
  toute bibliothèque tierce sans accord explicite ; l'accord est donné ici.
  * Font Awesome Free **7.3.1** : `fa-solid-900.woff2` (117 Ko) et
    `fa-brands-400.woff2` (113 Ko) dans `public/assets/fonts/`. **Aucun CDN** :
    la règle « assets 100 % locaux » reste tenue.
  * `public/assets/css/fontawesome.css` est **élagué aux 37 glyphes réellement
    utilisés** au lieu des ~2 000 du paquet d'origine : la feuille pèse 6 Ko.
  * Nouveau helper `Core\Icon` (`core/Icon.php`) : `Icon::solid()` et
    `Icon::brands()` valident le glyphe contre le sous-ensemble embarqué et
    lèvent une exception explicite si l'icône manque, plutôt que d'afficher un
    carré vide indiagnosticable.
  * `views/layout/header.php` et `views/layout/modals.php` sont migrés. Les
    autres vues conservent leurs SVG Tabler en ligne : la migration y reste à
    faire et n'était pas l'objet de cette demande.
* **Correction d'un bug bloquant sur la connexion (INC-006).** `AuthController`
  appelait `Core\Logger::error()` sans importer la classe ; dans le namespace
  `Controllers`, la résolution tombait sur `Controllers\Core\Logger` et levait
  `Class not found` — **toute tentative de connexion échouait en HTTP 500**.
  * Cause plus large : 7 contrôleurs sur 11 appelaient `Logger::` sans
    `use Core\Logger`. Tous ont reçu l'import manquant ; `AuthController` a été
    normalisé sur `Logger::`.
  * Constat : le contrôleur est un `final class` sans `Logger` dans sa portée ;
    le cas est désormais couvert par un test de non-régression.

### Ergonomie et sécurité de l'assistant

* **Largeur de frame corrigée.** La carte du formulaire était bridée par
  `.container-tight` de Tabler.io, plafonné à **30rem (480px)**. À cette
  largeur, une colonne `col-md-3` ne disposait que d'une centaine de pixels :
  les libellés « Utilisateur MySQL » et « Mot de passe MySQL » passaient à la
  ligne et les champs paraissaient décalés les uns par rapport aux autres.
  * `.container-tight` est remplacé par `.container install-container`, fluide
    sous 576 px puis élargi par paliers — 40rem, 48rem, 54rem, 56rem — selon le
    point de rupture Bootstrap.
  * Grille de l'étape 1 rééquilibrée : hôte `col-6 col-md-8` et port
    `col-6 col-md-4` sur une même ligne, puis nom de la base et utilisateur MySQL
    en `col-12 col-md-6`, mot de passe MySQL en `col-12 col-md-6`.
  * Liste du schéma passant de 3 à 4 tables par ligne (`col-6 col-md-3`).
* **Alignement des actions.** Les deux pieds de carte utilisent `.install-actions`
  (`flex-wrap` + gouttière) au lieu d'un `d-flex` rigide : le bouton d'action
  principale reste à droite grâce à `.install-action-final`, la note d'appoint
  passe en dessous dès 768 px, et sous 768 px les boutons s'empilent en
  `column-reverse` sur toute la largeur — la validation reste l'action la plus
  proche du pouce.
* **Aide de champ stabilisée.** `.form-hint` réserve désormais une hauteur
  minimale, ce qui supprime les décalages verticaux entre deux colonnes voisines
  dont l'une porte une indication et l'autre non.
* **Adaptabilité mobile préservée** : `max-width: 100%` et marges internes de
  1rem conservées sous 768 px, `col-6` pour les champs les plus courts.
* La page 503 de configuration verrouillée (`views/erreur/503.php`) profite du
  même conteneur élargi, pour un rendu homogène avec l'assistant.
* **Temporisateur de session** à fenêtre glissante de 5 minutes et 12 tentatives
  au plus, pour freiner le brute-force des identifiants MySQL.
* **Jeton CSRF** exigé sur `POST /install` et sur l'endpoint de test, y compris
  pour les requêtes AJAX.
* **Aucun mot de passe MySQL en dur** : le script `scripts/install.php` exige
  désormais `FLOTTEO_ADMIN_EMAIL` et `FLOTTEO_ADMIN_PASSWORD` en variables
  d'environnement, et délègue à `Services\Installer` — la logique CLI et web ne
  peut plus diverger.
* **Rôle administrateur** porté par l'`ENUM role` avec la valeur `administration`,
  et non par un drapeau `isAdmin`.

### Écarts assumés

#### Icônes — Font Awesome écarté

* Font Awesome n'est pas embarqué. Tabler.io fournit déjà un jeu complet
  d'icônes **SVG inline**, plus léger (aucune police de 400 Ko) et cohérent avec
  le reste de l'interface. La charte est respectée sans dépendance supplémentaire.
* La police de titraille **Dela Gothic One** est, elle, auto-hébergée
  (`public/assets/fonts/dela-gothic-one-latin-400.woff2`, 12 Ko, sous-ensemble
  latin). Aucun appel réseau externe n'est effectué au rendu.

#### Écart de version

* La version applicative déclarée reste `1.0.1`. L'assistant est une
  fonctionnalité de livraison, pas un changement de schéma ni de modèle de
  données : incrémenter `app.version` aurait faussé le contrôle de version du
  schéma déjà écrit. Le journal fait foi sur la version *livrée*.

* **Champ « nom d'utilisateur » renommé.** Le premier identifiant de l'étape 2
  porte désormais `name="nom_d_utilisateur"` et `id="nom_d_utilisateur"`, avec le
  libellé « Nom d'utilisateur » et un `placeholder` homonyme. Le contrôleur lit
  `Request::input('nom_d_utilisateur', '')` et `install.js` cible
  `#nom_d_utilisateur` pour le focus. La valeur est réaffichée après un re-rendu
  serveur ; l'ancienne clé `admin_username` est sans effet.
  * **Colonne SQL visée :** `utilisateurs.nom`. Le schéma de `sql/schema.sql` ne
    comporte **ni colonne `username` ni colonne `login`** — l'identifiant de
    connexion reste l'e-mail, garanti unique par `uq_utilisateurs_email`. Il n'y a
    donc pas lieu d'ajouter une colonne : le nom d'utilisateur alimente la
    colonne `nom`, ce que l'installeur écrivait déjà.

### Correctifs

* **Message d'erreur de connexion affiché en double (INC-005).** En cas d'échec du
  test de connexion à l'étape 1, le message apparaissait deux fois : dans l'alerte
  rouge Tabler, puis immédiatement en dessous dans un bloc gris.
  * **Cause :** `afficherRetour()` composait l'alerte avec le message dans un
    `<div class="small">`, et y concaténait ensuite une variable `detail` qui
    recopiait **le même message** dans un `<div class="text-secondary small">`.
    Le doublon n'était donc pas un second composant, mais une seconde injection du
    texte à l'identique — d'où l'apparence d'un message gris répété.
  * **Correctif :** `afficherRetour(etat, message, detail)` n'ajoute le bloc gris
    que si `detail` est non vide **et différent de `message`**. Le message reste
    rendu une seule fois, dans l'alerte. La signature devient
    explicite : le `detail` transmis par le serveur n'est plus perdu.
  * **Vérification :** quatre cas rejoués sur le DOM réellement servi, sous jsdom
    — échec PDO, `detail` identique au message, `detail` distinct, succès.
    Occurrences du message : 1 dans les quatre cas.
* **Valeurs de l'étape 1 conservées après un re-rendu serveur.** Une erreur
  renvoyée depuis l'étape 2 réinitialisait les champs de connexion à leurs
  valeurs par défaut et obligeait à tout ressaisir. `InstallController` transmet
  désormais les valeurs soumises (`saisieBdd()`), réaffichées par la vue.
  Le mot de passe MySQL reste volontairement absent : un secret n'est jamais
  réémis dans une réponse HTML.
* **Étape 2 de l'assistant affichée vide (INC-004, bloquant).** Au clic sur
  « Tester la connexion », la carte de l'étape 2 ne s'affichait pas : aucun champ
  de saisie n'était visible et la procédure restait bloquée.
  * **Cause :** le `d-none` initial était posé sur le `<form id="formulaire-admin">`
    alors que `install.js` bascule `d-none` sur les blocs `#etape-1`, `#etape-2`
    et `#etape-3`. L'étape 2 retirait donc `d-none` de la carte `#etape-2` — mais
    celle-ci restait enveloppée dans un formulaire en `display: none`. Le conteneur
    de bascule et le conteneur réellement masqué n'étaient pas le même nœud.
  * **Correctif :** le `d-none` initial est déplacé sur la carte `#etape-2`, seul
    nœud piloté par `install.js`. Le formulaire n'est plus masqué.
  * **Vérification :** bascule d'étape rejouée sur le DOM réellement servi, sous
    jsdom — `formulaire-admin` visible, `#etape-2` visible, `#etape-1` et
    `#etape-3` masqués, quatre champs présents. Aucune exception PHP ni variable
    non définie n'était en cause.
* **Contrat de noms de champs de l'étape 2 aligné sur la spécification** :
  `admin_username`, `admin_email`, `admin_password` et `admin_password_confirm`,
  cohérents entre la vue, le contrôleur et `install.js`. Les libellés deviennent
  « Nom du Shogun / Administrateur » et « Confirmation du mot de passe ».
  Le nom devient facultatif côté installateur : l'e-mail reste l'identifiant de
  connexion et seule la reprise de compte s'appuie sur le nom.
* `Router::currentPath()` devient statique : le bootstrap évalue désormais le
  chemin avant d'instancier le routeur.
* `Controller::render()` expose le paramètre `$layout`, déjà supporté par
  `View::render()`, ce qui évite de dépendre d'un argument superflu.
* Après affichage d'une erreur dans l'assistant, l'exécution continuait et
  tentait un `header()` tardif (`Cannot modify header information`). Le flux est
  désormais explicitement interrompu.

---

## [1.0.1] — 2026-10-02

### Correctifs

#### Chemins d'actifs de la mire de connexion (INC-001, bloquant)

* **Symptôme :** `/login` s'affichait sans style, avec des 404 en console sur
  Tabler.io (CSS/JS), la feuille de style applicative, `app.json` et le manifeste.
* **Cause :** `config/config.php` imposait `'base_url' => '/flotteo'` alors que le
  vhost sert l'application à la racine du domaine (`DocumentRoot .../public`).
  Toutes les URL générées étaient préfixées d'un segment inexistant. Le défaut
  affectait l'ensemble de l'application, pas seulement la mire de connexion.
* **Correctif :**
  * Nouveau helper `core/Url.php` : le préfixe est déduit de `SCRIPT_NAME`.
    Fonction pure `Url::detect()` vérifiée sur 6 topologies de déploiement.
    Aucune modification de code n'est plus requise entre deux environnements.
  * `config/config.php` : `'base_url' => null` (auto-détection).
    Une chaîne reste acceptée pour forcer un préfixe particulier.
  * Toutes les `<link>`, `<script>` et `action` de formulaire passent désormais
    par `Url::to()`, `Url::asset()` ou `Url::manifest()`.
  * `Router::currentPath()`, `Controller::baseUrl()` et la redirection de
    connexion utilisent le même helper : routage et génération d'URL ne peuvent
    plus diverger.
* **Note Tabler.io :** `tabler-vendors.min.css` n'existe pas dans Tabler 1.0.0
  (nom hérité des versions 0.x). `tabler.min.css` est un bundle autonome, sans
  police externe : aucune dépendance supplémentaire n'est requise.

#### Ressources statiques et manifeste (INC-001)

* Création de `public/app.json` (manifeste d'application) avec `start_url`,
  `scope` et `id` relatifs au préfixe détecté, et 2 icônes SVG
  (standard et maskable).
* Création de `public/assets/img/flotteo.svg` : supprime le 404 implicite sur
  `favicon.ico`.
* `public/.htaccess` :
  * court-circuit explicite de `assets/`, `uploads/`, `app.json` et
    `favicon.svg` avant toute condition de réécriture ;
  * types MIME explicites (`application/json`, `image/svg+xml`, `text/css`,
    `application/javascript`, `font/woff2`, `application/pdf`) ;
  * cache `mod_expires` : 7 jours sur les assets, 0 seconde sur PHP.
* Les 6 ressources de `/login` répondent en `200` avec le type MIME correct.

#### Registres documentaires inaccessibles (INC-002)

* `docs/` est hors de la racine servie par Apache : les liens du pied de page
  renvoyaient 404.
* Nouveau `controllers/DocsController.php` : route `/docs/{doc}` en liste blanche
  stricte (`changelog`, `features`, `qa_recette`), authentification requise,
  `Content-Type` explicite et `X-Content-Type-Options: nosniff`.
* Une requête hors liste blanche, tel `/docs/changelog.md`, est refusée sans
  divulgation de contenu.
* `docs/aligne donc plus sur sa source Markdown unique : aucune duplication,
  aucune dérive documentaire possible.

#### Ergonomie du bandeau (INC-003)

* Suppression de la variable morte `$page` et de la closure `$lien` dans le
  layout.
* Le titre de l'onglet et de la barre de page suit désormais la route active
  au lieu d'afficher toujours « Tableau de bord ».

### Vérifications

* Lint PHP : **57/57** fichiers sans erreur de syntaxe.
* Déploiement racine : 6/6 ressources de `/login` en `200`, type MIME correct.
* Déploiement sous-répertoire (`base_url = '/flotteo'`) : URLs préfixées et
  routage résolu, configuration restaurée en auto-détection ensuite.
* Répertoires sensibles : 8/8 toujours en `403`.
* Ressource statique déposée dans `assets/` : servie telle quelle, PHP non exécuté.
* Registres de recette mis à jour : 14 contrôles non fonctionnels et 20 cas
  dédiés aux chemins d'actifs, tous conformes.

---

## [1.0.0] — 2026-10-02

### Version initiale — socle complet de l'application

#### Modules livrés

* **Authentification & rôles**
  * Écran de connexion (email + mot de passe) avec affichage masqué du secret.
  * Hachage `PASSWORD_DEFAULT` (BCRYPT/ARGON2ID) et rehash automatique à la connexion.
  * Sessions durcies : `httponly`, `samesite=Strict`, `secure` en HTTPS, régénération d'identifiant.
  * Matrice à trois niveaux : `lecture_seule`, `modification`, `administration`.
  * Profil personnel : identité, email, changement de mot de passe avec double saisie.

* **Tableau de bord & pilotage visuel**
  * Cartes KPI : flotte active, taux d'immobilisation, échéances < 90 jours, coût d'entretien du mois et de l'année.
  * Échéancier des sorties de flotte sur 12 mois (barres).
  * Répartition de la flotte en donut interactif à onglets : entité propriétaire, organisme loueur, lieu d'exploitation.
  * Évolution des dépenses d'entretien, histogramme empilé par typologie de prestation.
  * TCO moyen d'entretien par modèle de véhicule (barres horizontales).
  * Matrice des incidents : radar de répartition par type + courbe de tendance annuelle.
  * Listes d'action rapide : sorties les plus proches, véhicules immobilisés.

* **Gestion de flotte**
  * Liste filtrable (recherche, entité, loueur, lieu, statut, échéance < 90 jours).
  * Fiche véhicule : cycle d'exploitation complet, jauge de détention, historique d'entretien, incidents liés.
  * CRUD avec validation métier et garde-fou d'unicité d'immatriculation.

* **Suivi du cycle de vie**
  * Historique des révisions : type paramétrable, date, kilométrage, coûts HT/TTC, commentaire.
  * Incidents & sinistres : type, date, lieu, descriptif, responsabilité, immatriculation, statut.
  * Pièces jointes : téléversement multi-format (PDF, JPG, PNG, WEBP), nommage unique côté serveur,
    contrôle MIME réel, téléchargement contrôlé, suppression liée à l'incident.

* **Administration**
  * CRUD utilisateurs avec contrôle du dernier administrateur actif.
  * Tables de paramétrage génériques : marques, modèles, entités, loueurs, lieux, types d'intervention.
  * Paramètres système : email gestionnaire, email expéditeur, paliers d'alerte, activation des alertes.
  * Aperçu des échéances groupées par palier avant envoi.

* **Alertes de fin de contrat**
  * Paliers d'anticipation paramétrables (90 / 180 / 270 jours par défaut).
  * Mail condensé récapitulatif généré par `services/AlertService.php`.
  * Tâche CLI `scripts/alert_cron.php` exploitable en crontab, avec code de sortie exploitable.
  * Déclenchement manuel depuis l'interface d'administration.

#### Choix techniques structurants

* Architecture **VMVC stricte** en PHP natif, sans framework tiers.
* Autoloader à table de correspondance explicite (`core/`, `controllers/`, `models/`, `services/`).
* Règle d'or appliquée à **100 % des 54 fichiers PHP** : `declare(strict_types=1);`.
* PDO exclusively, requêtes préparées systématiques, aucune concaténation SQL.
* Journalisation interne sur disque (`storage/logs/`) ; aucune trace technique exposée à l'écran.
* Protection CSRF par jeton de session sur la totalité des actions `POST`.
* **Aucune alerte native** : confirmations en modale Tabler, retours en toast.
* Assets Tabler.io et ApexCharts servis en local, aucune dépendance CDN à l'exécution.

---

## Versions antérieures

* Aucune. Il s'agit de la première livraison de Flotteo.
