# Guide de l'utilisateur — Flotteo

Flotteo suit un parc de véhicules d'entreprise : entrées et sorties, révisions,
incidents, échéances de fin de contrat, et la messagerie qui prévient de tout cela.

Ce guide décrit les écrans tels qu'ils sont réellement implémentés. Pour le détail
technique, consultez le [cahier des charges](../cahier_des_charges_flotteo.md).

---

## 1. Prise en main

### Se connecter

Rendez-vous sur l'adresse de l'application. L'assistant d'installation ne s'affiche
qu'une fois, tant que l'application n'est pas configurée. Après installation, il se
referme automatiquement et son adresse devient inaccessible.

L'accès à `/install` est refusé dès qu'une installation est constatée : impossible
d'écraser par accident une base déjà en service.

### Les niveaux d'accès

La matrice des rôles de Flotteo permet une gestion fine des droits :

| Niveau | Peut |
| :--- | :--- |
| **Lecture Seule** | Consulter l'ensemble des modules d'exploitation (véhicules, révisions, incidents, échéances, kilométrage et tableaux de bord) en lecture seule. |
| **Comptabilité** | Navigation globale en lecture seule, dépôt de pièces jointes sur les incidents, et gestion financière complète des sinistres (saisie franchise, montants d'assurance, encaissement effectif et clôture des dossiers). Création et suppression de dossiers interdites. |
| **Documentaliste** | Consulter l'application et téléverser des documents et pièces jointes sur les véhicules, révisions et incidents. |
| **Modification** | Créer et éditer l'ensemble des données d'exploitation (véhicules, révisions, relevés kilométriques, déclaration d'incidents). |
| **Administration** | Accès total : paramétrage système, gestion des référentiels, comptes utilisateurs, journal d'audit et sauvegardes. |

La barre supérieure n'affiche que les modules accessibles à votre niveau. Paramètres et Référentiels n'apparaissent pas pour les profils « Lecture Seule », « Comptabilité », « Documentaliste » et « Modification ».

### La barre supérieure

Neuf entrées, de gauche à droite : **Tableau de bord**, **Agenda**, **Véhicules**,
**Kilométrage**, **Révisions**, **Incidents**, **Échéances**, **Référentiels**, **Paramètres**.

> **Organisation de l'administration.** Pour préserver la lisibilité de la barre supérieure,
> les modules **Utilisateurs** (gestion des comptes) et **Journal d'audit** (traçabilité de sécurité)
> sont désormais réunis dans la page **Paramètres**, au sein du bloc latéral « Sécurité & Contrôle ».

L'entrée courante est signalée par un liseré bleu en dessous de son nom. **À partir
de 1200 px de largeur d'écran**, la barre affiche toutes ses entrées d'un seul tenant.
**En dessous de 1200 px** — tablette, petit portable, fenêtre partagée — elle se
replie derrière le bouton de menu : cliquez dessus pour ouvrir la liste, où
l'entrée courante apparaît en gras. Ce repli évite que les entrées débordent de
l'écran et forcent un défilement latéral.

### La largeur des pages

Les pages occupent toute la largeur de la fenêtre, avec une petite marge de chaque
côté. Sur un grand écran, les cartes, les tableaux et les graphiques profitent donc
de toute la place disponible.

* Un **tableau** trop large pour la place disponible se comporte bien : il défile
  de l'intérieur de sa carte, et la page entière ne bouge pas.
* **Aucun défilement latéral** de la page n'est nécessaire, quelle que soit la
  largeur de la fenêtre.

---

## 2. Tableau de bord

Synthèse chiffrée du parc : indicateurs clés, coût d'entretien, répartition par
modèle, incidents ouverts, échéancier. Chaque graphique dispose d'un équivalent
numérique lisible, pour ne rien dépendre de la seule représentation graphique.

---

## 3. Véhicules

Liste filtrable et fiche détaillée par véhicule : caractéristiques, historique
d'entretien, incidents, modèle, entité propriétaire, organisme loueur et lieu
d'exploitation.

La création et l'édition passent par une modale. La suppression est refusée tant que
le véhicule est rattaché à des données qui le référencent.

---

## 4. Révisions & entretien

Suivi des interventions : date, type, montant, kilométrage. Le lien avec le véhicule
et le type d'intervention provient des référentiels.

---

## 5. Incidents & sinistres

Signalement, instruction et suivi administratif et financier des sinistres de la flotte.

### Informations déclaratives & Entité

Chaque incident consigne la date de survenue, le véhicule impliqué, le type de sinistre (panne, bris de glace, accident matériel, etc.), le lieu, le tiers éventuel et la responsabilité du conducteur.
Une **Entité** (filiale, établissement ou centre de coût) est rattachée au dossier — héritée automatiquement du véhicule concerné ou sélectionnée manuellement. La colonne Entité apparaît dans le tableau récapitulatif pour faciliter l'analyse analytique.

### Volet Assurance & Suivi financier

Pour chaque dossier, le bloc **Assurance & Prise en charge** permet de piloter la relation avec la compagnie :
* **Prise en charge :** Indique si le sinistre fait l'objet d'une déclaration et d'une prise en charge par l'assureur.
* **Remboursement attendu :** Case à cocher précisant qu'une indemnisation de la compagnie est prévue.
* **Montants financiers :** Deux champs numériques alignés pour le montant de l'indemnité remboursée (€) et le montant de la franchise restant à charge (€).
* **Remboursement perçu :** Case à cocher matérialisant l'encaissement effectif des fonds sur le compte bancaire. Dès que cette case est cochée, la saisie d'un montant remboursé supérieur à 0 € est obligatoire.

> **Règle de gestion bloquante (Intégrité financière).** Un dossier d'incident ne peut pas être
> clôturé (statut « Clôturé ») si un remboursement est attendu sans que la case « Remboursement perçu »
> ne soit cochée et validée. Cette sécurité empêche d'archiver prématurément un dossier dont l'indemnisation
> reste en souffrance. Le blocage intervient dynamiquement dans l'interface ainsi que côté serveur.

### Procédure pas-à-pas pour le profil Comptabilité

Les utilisateurs dotés du profil **Comptabilité** disposent des droits nécessaires pour instruire financièrement les sinistres :
1. **Identifier les dossiers non soldés :** Dans la liste des incidents, utiliser le sélecteur **Remboursement assurance** et choisir **« En attente de remboursement assurance »**.
2. **Consulter la fiche :** Cliquer sur **Dossier** sur la ligne de l'incident pour ouvrir sa vue détaillée.
3. **Joindre les pièces comptables :** Déposer les quittances de règlement, décomptes d'assurance ou factures acquittées dans la section « Pièces jointes » (formats PDF, JPG, PNG, WEBP acceptés).
4. **Mettre à jour les montants :** Cliquer sur **Modifier**, renseigner la franchise et l'indemnité perçue, puis cocher **Remboursement perçu**.
5. **Clôturer le dossier :** Positionner le statut sur **Clôturé** puis enregistrer.

### Pièces jointes & Visionneuse intégrée

Les photographies (JPG, PNG, WEBP) et documents PDF s'affichent dans la liste des pièces jointes :
* Un clic sur un document ouvre la **visionneuse modale intégrée** (zoom, lecture pleine page de PDF ou photo).
* Un bouton dédié permet le téléchargement direct du fichier original.
* Les profils **Comptabilité**, **Documentaliste**, **Modification** et **Administration** peuvent ajouter des pièces jointes. La suppression est strictement réservée aux administrateurs et modificateurs pour garantir la traçabilité.

---

## 6. Échéances

Suivi des fins de contrat : échéancier trié par urgence croissante, filtre par palier
d'anticipation. Le nombre de véhicules de chaque filtre est affiché en badge.

---

## 6 bis. Agenda

Un calendrier qui réunit les trois informations datées du parc, deuxième entrée de
la barre. Il s'ouvre sur le mois en cours.

**Les trois familles d'événements**, figurées par des pastilles de couleur dans
l'en-tête de la carte :

| Famille | Ce qu'elle montre | Comment elle est datée |
| :--- | :--- | :--- |
| **Échéances** (orange, rouge si proche) | la fin de contrat prévue d'un véhicule encore dans le parc | date réelle |
| **Révisions** (bleu) | les interventions d'entretien **réalisées**, avec leur type et le kilométrage | date réelle |
| **Immobilisations** (rouge) | les véhicules **immobilisés aujourd'hui**, avec le motif | **aucune date** |

> **À savoir sur les révisions.** Flotteo enregistre les interventions au moment
> où elles sont faites ; elle ne connaît pas la périodicité d'un véhicule et ne
> peut donc pas annoncer une prochaine révision. Les événements bleus sont des
> révisions **passées**.

> **À savoir sur les immobilisations.** Une immobilisation est un état, pas une
> date : Flotteu ne sait pas depuis quand un véhicule est immobilisé, ni quand il
> remettra en service. Ces événements apparaissent donc **le jour où vous
> consultez l'agenda**, et le pied de la carte le rappelle. Ne les lisez pas comme
> un début d'immobilisation.

**Se déplacer.** Les deux flèches de la barre d'outils passent au mois ou à la
semaine précédente et suivante ; « Aujourd'hui » revient au jour courant.

**Changer de vue.** Les trois boutons de droite bascule entre le **mois**, la
**semaine** et la **liste**. La semaine commence le lundi.

**Filtrer une famille.** Les trois boutons de l'en-tête de carte, avec leur nombre
d'événements, masquent ou ré-affichent une famille. Le dernier bouton encore actif
ne peut pas être masqué : le calendrier resterait vide sans moyen de le remplir à
nouveau.

**Ouvrir un véhicule.** Cliquez un événement : la fiche du véhicule s'ouvre. Comme
il s'agit d'un lien, `Ctrl` + clic l'ouvre dans un nouvel onglet.

**Ce que montre la couleur d'une échéance.** Rouge sous 30 jours, orange jusqu'à
90, ambre jusqu'à 180, bleu ardoise au-delà. Survolez un événement pour connaître le
nombre de jours restants et le loueur.

---

## 7. Référentiels

Les tables de paramétrage qui alimentent le reste de l'application. Six référentiels,
accessibles par l'onglet correspondant. **L'onglet du référentiel courant est marqué
d'un trait bleu en dessous de son nom** — repère utile pour savoir d'un coup d'œil
quelle table est affichée.

| Référentiel | Champs |
| :--- | :--- |
| **Marques** | Nom de la marque |
| **Modèles** | Marque, modèle |
| **Entités propriétaires** | Raison sociale, code interne |
| **Organismes loueurs** | Raison sociale, email de contact, téléphone |
| **Lieux d'exploitation** | Site ou dépôt, ville |
| **Types d'intervention** | Libellé de la prestation, catégorie |

### Ajouter une entrée

Le bouton **Ajouter** ouvre une modale. Les champs marqués d'un astérisque sont
obligatoires. **Enregistrer** valide puis ferme.

Si une valeur est refusée, le message s'affiche dans la page : un champ obligatoire
vide, une adresse email mal formée, ou une catégorie inconnue.

### Modifier une entrée

Le bouton crayon de la ligne concernée ouvre la même modale, pré-remplie avec les
valeurs enregistrées. Le titre passe de « Ajouter » à « Modifier ».

Si la modale s'ouvre vide alors que vous veniez de cliquer sur le crayon, faites un
rechargement forcé de la page (Ctrl+Maj+R) : une version ancienne du script est
probablement encore en cache dans le navigateur.

### Supprimer une entrée

Le bouton corbeille demande confirmation avant d'agir — une confirmation explicite,
jamais une boîte de dialogue native du navigateur.

**Une suppression peut être refusée** : c'est le cas lorsqu'un véhicule ou un entretien
référence encore l'entrée. Le message l'indique explicitement. Supprimez ou
réaffectez d'abord les données qui en dépendent.

### Bon à savoir

* Les modèles dépendent des marques : une marque doit exister avant d'être affectée à
  un modèle. Le menu déroulant de la modale ne propose que les marques existantes.
* Les catégories de type d'intervention sont une liste fermée. Une valeur hors liste
  est ramenée à « Autre ».

---

## 8. Paramètres

Le centre névralgique de l'administration de Flotteo. La page se parcourt par une **navigation verticale** à gauche, organisée en deux ensembles distincts :

### 1. Configuration système
* **Messagerie :** Mode d'envoi (frontal PHP natif ou serveur SMTP sécurisé), identifiants, adresse expéditrice et test d'envoi en direct avec validation TLS/STARTTLS.
* **Alertes :** Paliers d'anticipation avant fin de contrat (90, 180, 270 jours), destinataire unique des alertes et déclenchement planifié par tâche `cron`.
* **Relevés kilométriques :** Fréquence des relevés, délais de grâce et relances automatiques par courriel.
* **Sauvegardes :** Rétention locale des archives SQL et synchronisation automatique vers un partage réseau distant Samba/SMB.
* **Mises à jour :** Dépôt GitHub de référence et vérification des versions publiées.
* **Base de données :** Métriques d'occupation des tables et maintenance.

### 2. Sécurité & Contrôle
Ce volet réunit les deux fonctions fondamentales de gouvernance :
* **Utilisateurs :** Accès direct à la gestion des comptes, des rôles applicatifs et des politiques de renouvellement de mot de passe.
* **Journal d'audit :** Consultation et recherche multi-critères des événements, modifications et suppressions intervenues sur l'application.

---

## 9. Utilisateurs & Journal d'audit

Accessibles depuis le menu **Paramètres** (rubrique « Sécurité & Contrôle »).

### Gestion des utilisateurs

Les comptes sont organisés en grille de cartes Tabler. Chaque carte affiche le nom, l'email, la photo de profil (ou initiales), le statut de validité du mot de passe et le badge du rôle attribué :
* **Lecture Seule** (badge gris / secondaire) : consultation générale.
* **Comptabilité** (badge sarcelle / teal) : suivi financier des sinistres et justificatifs.
* **Documentaliste** (badge azur) : archivage documentaire technique.
* **Modification** (badge bleu) : exploitation courante du parc.
* **Administration** (badge violet / purple) : gestion globale et sécurité.

Chaque compte peut être forcé à renouveler son mot de passe lors de sa prochaine connexion, et une règle automatique prévient l'utilisateur un mois avant l'échéance semestrielle de renouvellement.
Le dernier compte administrateur actif est strictement protégé contre toute rétrogradation ou suppression.

### Journal d'audit & Traçabilité

Permet de suivre en temps réel chaque opération d'écriture (création, modification, suppression logique, restauration ou téléversement de fichier) sur l'ensemble des modules (véhicules, entretiens, incidents, documents, utilisateurs).
Le journal consigne l'auteur, l'adresse IP, la date/heure exacte, l'entité visée, la description humaine et le différentiel avant/après (`diff`).
Un export CSV et une modale de purge manuelle sont mis à disposition des administrateurs.

---

## 10. Documentation

Les quatre registres s'ouvrent **dedans l'application**, depuis les liens du pied de
page de chaque écran. Comme les autres pages, ils affichent la barre supérieure, le
fil de navigation et le pied de page : vous pouvez donc revenir au tableau de bord
ou passer à un autre écran sans repasser par votre navigateur.

* **Guide utilisateur** — ce document.
* **Fonctionnalités** — ce que la version courante sait faire.
* **Journal des modifications** — ce qui a changé, et quand.
* **Recette QA** — les cas de test et leur résultat.

La colonne de gauche liste les quatre registres ; le registre courant y est
signalé par un fond bleu. Le contenu est affiché dans une carte, au format et aux
polices du reste de l'application.

**Une astuce :** les quatre pages sont également disponibles en fichiers autonomes,
ouvrables sans connexion ni application en service — utile pour les conserver ou
les transmettre. Elles sont régénérées par `php scripts/build_docs.php`.

---

## 11. Résoudre un problème courant

| Symptôme | Cause probable | Solution |
| :--- | :--- | :--- |
| Impossible de clôturer un incident | Un remboursement est attendu mais n'a pas été marqué comme perçu | Ouvrir le formulaire d'édition, cocher « Remboursement perçu » et renseigner le montant perçu (> 0 €) |
| Le bouton « Signaler un incident » est absent | Profil insuffisant (Comptabilité ou Lecture Seule) | La déclaration d'incidents est réservée aux profils Modification et Administration |
| Aucun email ne part | Alertes suspendues, ou destinataire vide | Section « Alertes » : activez les alertes et renseignez le destinataire |
| Le test d'envoi échoue sur le certificat | Certificat auto-signé du serveur de messagerie | Installez l'autorité de certification sur le serveur |
| Le test d'envoi échoue sur l'authentification | Identifiant ou mot de passe erroné, ou champ identifiant laissé vide alors qu'il est exigé | Vérifiez les deux champs ; le mot de passe conservé est celui enregistré |
| « Suppression impossible » sur un référentiel | L'entrée est encore utilisée par un véhicule ou un entretien | Réaffectez ou supprimez d'abord ces données |
| Un module manque dans la barre | Profil insuffisant | Référentiels et Paramètres exigent le niveau Administration |
| Le bouton « Éditer » n'ouvre rien | Script en cache | Rechargement forcé de la page (Ctrl+Maj+R) |
| « Type de fichier refusé » sur un avatar | Le fichier n'est pas une image, ou son extension ne correspond pas à son contenu | Déposez un JPEG, PNG ou WEBP ; un PDF ou un GIF sont refusés à cet endroit |
| L'image n'apparaît pas après l'enregistrement | La modale avait été ouverte avant le rechargement forcé | Rechargez la page, rouvrez la modale, puis enregistrez |
| « Erreur de jeton » après avoir choisi une image | L'envoi a dépassé la limite de taille du serveur | Choisissez une image plus légère, ou demandez à l'administrateur d'augmenter `post_max_size` |