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

### Les trois niveaux d'accès

| Niveau | Peut |
| :--- | :--- |
| **Lecture** | Consulter les véhicules, révisions, incidents, échéances et les tableaux de bord. |
| **Modification** | Créer et éditer véhicules, révisions et incidents. |
| **Administration** | Tout ce qui précède, plus les utilisateurs, les référentiels et les paramètres. |

La barre supérieure n'affiche que les modules accessibles à votre niveau. Paramètres,
Référentiels et Utilisateurs n'apparaissent pas pour un profil « Modification ».

### La barre supérieure

Neuf entrées, de gauche à droite : **Tableau de bord**, **Agenda**, **Véhicules**,
**Révisions**, **Incidents**, **Échéances**, **Référentiels**, **Paramètres**,
**Utilisateurs**.

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

Signalement et suivi d'un sinistre, avec pièce jointe téléchargeable depuis sa fiche.

Les photographies (JPG, PNG, WEBP) s'affichent en **vignette** dans la liste des
pièces jointes : un clic sur la vignette ouvre l'image dans un nouvel onglet, un
clic sur le nom télécharge le fichier. Les PDF, qui n'ont pas d'aperçu possible,
sont signalés par une icône. Le cadre d'une vignette reste vide si le fichier
n'est plus présent sur le serveur — l'icône indique alors « Fichier absent du
stockage ».

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

La page se parcourt par une **navigation verticale** à gauche. La section choisie
s'affiche à droite ; l'entrée courante est signalée.

Chaque section a son propre bouton **Enregistrer** et n'enregistre que ses propres
réglages : enregistrer une section ne touche pas les autres.

### Section « Messagerie »

Détermine comment l'application envoie ses emails.

**Mode d'envoi.** Deux choix :

* **Frontal PHP natif** — délègue l'envoi à la configuration PHP du serveur. C'est le
  choix par défaut, sans réglage supplémentaire.
* **Serveur SMTP** — l'application adresse elle-même votre serveur de messagerie.

**Réglages du serveur SMTP** (toujours affichés, mais utilisés uniquement si le mode
« Serveur SMTP » est sélectionné) :

| Champ | À quoi ça sert | Exemple |
| :--- | :--- | :--- |
| Serveur | Nom d'hôte du serveur | `smtp.exemple.fr` |
| Port | Port de connexion | `587` |
| Chiffrement | Mode de chiffrement | `STARTTLS` |
| Identifiant | Compte de connexion | `alerte@exemple.fr` |
| Mot de passe | Secret du compte | — |

**Le certificat du serveur est vérifié.** Un certificat auto-signé ou expiré sera
refusé, et le message d'erreur le dira explicitement. Si votre hébergeur utilise un
certificat interne, installez son autorité de certification sur le serveur plutôt que
de contourner la vérification.

**Adresse expéditrice.** L'adresse présentée aux destinataires. La plupart des
serveurs exigent qu'elle appartienne au domaine que vous déclarez.

> **Mot de passe.** Le mot de passe enregistré n'est **jamais** renvoyé au navigateur.
> Si le champ est laissé vide, la valeur enregistrée est conservée. Pour la changer,
> saisissez une nouvelle valeur ; pour l'effacer, cochez la case dédiée.

**Vérifier la configuration.** Un bouton **Envoyer un message de test** envoie un
courriel à l'adresse de votre choix.

> Le test porte sur les valeurs **actuellement saisies**, pas sur celles enregistrées.
> Vous pouvez donc valider une configuration avant de l'enregistrer. Seul le mot de
> passe fait exception : il retombe sur la valeur enregistrée, puisqu'il n'est jamais
> affiché.

Le résultat s'affiche sous le bouton. En cas d'échec, le message indique la cause
réelle : port fermé, certificat non reconnu, identifiants refusés, adresse refusée.

### Section « Alertes »

**Activation.** L'interrupteur suspend ou réactive l'envoi des rappels. Suspendre
n'efface aucun réglage.

**Paliers d'anticipation.** Trois délais, exprimés en jours avant la date de sortie
prévue. Un palier à 0 est ignoré. Les paliers actifs sont rappelés sous les champs.

**Destinataire des rappels.** Adresse unique qui reçoit le récapitulatif des véhicules
concernés. Laisser vide suspend l'envoi.

**Exécution.** Le bouton **Envoyer le récapitulatif** déclenche un envoi immédiat,
désactivé lorsque les alertes sont suspendues. La commande `cron` affichée permet
d'automatiser un envoi hebdomadaire.

---

## 9. Utilisateurs

Création et édition des comptes, avec attribution du niveau d'accès.

### Les cartes de profil

Les comptes ne sont pas présentés dans un tableau mais dans une **grille de cartes**,
sur le modèle des cartes de profil de Tabler. Chaque carte porte un bandeau coloré
dont la teinte dépend du rôle, une photo ou des initiales, le nom, l'adresse email, le
badge du niveau d'accès, l'état du compte et sa date de création.

La grille se réorganise selon la largeur de la fenêtre : **trois cartes par ligne** sur
un grand écran, **deux** à partir de 768 px, **une seule** en dessous. Rien n'est
masqué, la page entière défile comme les autres.

Deux actions seulement sont proposées sur chaque carte : le bouton **Éditer** et le
bouton **Supprimer**. Sur votre propre carte, **Supprimer est absent** — on ne peut pas
se retirer soi-même l'accès à l'application.

### Ajouter un compte

Le bouton **Ajouter** ouvre une modale. Les champs marqués d'un astérisque sont
obligatoires : nom d'utilisateur, adresse email, mot de passe et niveau d'accès.

Vous pouvez **déposer une photo** depuis votre ordinateur. Trois formats sont acceptés,
jusqu'à 8 Mo : **JPEG, PNG et WEBP**.

### Modifier un compte

Le bouton crayon ouvre la même modale, pré-remplie. Le titre passe de « Ajouter » à
« Modifier ».

Deux points méritent attention :

* **La photo n'est pas touchée** si vous ne faites rien à son sujet. Renommer un compte
  ou changer son adresse email conserve l'illustration existante.
* **Pour retirer la photo**, cochez la case « Supprimer l'avatar » puis enregistrez. Si
  vous cochez cette case *et* désignez un fichier, **c'est le fichier qui gagne** : la
  case est alors ignorée.

Si la modale s'ouvre vide alors que vous veniez de cliquer sur le crayon, faites un
rechargement forcé de la page (Ctrl+Maj+R) : une version ancienne du script est
probablement encore en cache dans le navigateur.

### Supprimer un compte

Le bouton corbeille demande confirmation avant d'agir — une confirmation explicite,
jamais une boîte de dialogue native du navigateur.

**Une suppression peut être refusée** : c'est le cas du dernier compte administrateur.
La règle protège l'application d'une perte d'accès totale. Réaffectez le niveau
d'accès à un autre compte avant de supprimer celui-ci.

### Bon à savoir

* **La photo n'est pas un document.** Seuls les formats d'image sont acceptés : un PDF
  ou un fichier GIF déposé à cet endroit sera refusé. Les pièces jointes des incidents
  acceptent, elles, le PDF.
* **Le type réel du fichier est contrôlé**, pas son extension. Renommer un fichier en
  `.png` ne le fait pas passer pour une image.
* **Le dernier administrateur est protégé**, quelle que soit la voie utilisée : ni la
  suppression ni la rétrogradation du dernier compte administrateur ne sont possibles.
* Les photos sont stockées sous un **nom aléatoire**, sans votre nom d'utilisateur
  dedans, dans un dossier où l'exécution de programmes est interdite.

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
| Aucun email ne part | Alertes suspendues, ou destinataire vide | Section « Alertes » : activez les alertes et renseignez le destinataire |
| Le test d'envoi échoue sur le certificat | Certificat auto-signé du serveur de messagerie | Installez l'autorité de certification sur le serveur |
| Le test d'envoi échoue sur l'authentification | Identifiant ou mot de passe erroné, ou champ identifiant laissé vide alors qu'il est exigé | Vérifiez les deux champs ; le mot de passe conservé est celui enregistré |
| « Suppression impossible » sur un référentiel | L'entrée est encore utilisée par un véhicule ou un entretien | Réaffectez ou supprimez d'abord ces données |
| Un module manque dans la barre | Profil insuffisant | Référentiels, Paramètres et Utilisateurs exigent le niveau Administration |
| Le bouton « Éditer » n'ouvre rien | Script en cache | Rechargement forcé de la page (Ctrl+Maj+R) |
| « Type de fichier refusé » sur un avatar | Le fichier n'est pas une image, ou son extension ne correspond pas à son contenu | Déposez un JPEG, PNG ou WEBP ; un PDF ou un GIF sont refusés à cet endroit |
| L'image n'apparaît pas après l'enregistrement | La modale avait été ouverte avant le rechargement forcé | Rechargez la page, rouvrez la modale, puis enregistrez |
| « Erreur de jeton » après avoir choisi une image | L'envoi a dépassé la limite de taille du serveur | Choisissez une image plus légère, ou demandez à l'administrateur d'augmenter `post_max_size` |