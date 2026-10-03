# Registre de recette & qualité — Flotteo

Plan de tests fonctionnels de la version **1.0.1** (livraison 1.1.0 : assistant d'installation).
Convention de statut : `⟨ À valider ⟩` / `✔ Conforme` / `✘ Non conforme` / `– Non applicable`.

---

## 0. Cadre de recette

| Élément | Valeur |
| :--- | :--- |
| Version testée | 1.0.1 |
| Pile | PHP 8.3 (natif) / Apache 2.4 / MariaDB 10.11 / PDO MySQL |
| Frontend | HTML5, CSS3, JavaScript Vanilla, Tabler.io, ApexCharts 3.54 |
| Base d'essai | `flotteo` — jeu de données de démonstration injecté via `sql/demodata.sql` |
| Assistant d'installation | `/install`, exécuté sur copie sans `storage/install.lock` |
| Comptes de test | `admin@flotteo.local`, `resp@flotteo.local`, `lecteur@flotteo.local` |

## 1. Vérifications non fonctionnelles (règles d'or)

| # | Contrôle | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| NF-01 | `declare(strict_types=1);` présent sur chaque fichier PHP | 62/62 fichiers | ✔ Conforme |
| NF-02 | Syntaxe PHP valide sur l'ensemble du projet | 0 erreur sur 62 fichiers | ✔ Conforme |
| NF-03 | Absence de `alert()`, `confirm()`, `prompt()` dans le frontend | 0 occurrence | ✔ Conforme |
| NF-04 | Requêtes SQL exclusivement en PDO préparé | 0 concaténation de variables | ✔ Conforme |
| NF-05 | Protection CSRF sur chaque action `POST` | 100 % des routes d'écriture | ✔ Conforme |
| NF-06 | Échappement HTML des variables de vue | systématique | ✔ Conforme |
| NF-07 | Aucune trace technique exposée à l'écran | journalisation disque uniquement | ✔ Conforme |
| NF-08 | Sources hors zone servie (`config/`, `core/`, `models/`, `views/`) | HTTP 403 | ✔ Conforme |
| NF-09 | Dossier `public/uploads/` non exécutable | extension PHP bloquée | ✔ Conforme |
| NF-10 | Assets servis en local, sans CDN à l'exécution | 0 requête externe | ✔ Conforme |
| NF-11 | Registres `changelog` et `features` synchronisés `.md` / `.html` | 3 paires complètes | ✔ Conforme |
| NF-12 | Toutes les URL publiques produites par `Core\Url` | aucune concaténation manuelle dans les vues | ✔ Conforme |
| NF-13 | Déploiement racine **et** sous-répertoire | assets et routes corrects dans les 2 cas | ✔ Conforme |
| NF-14 | Manifeste d'application servi en JSON | `200` + `Content-Type: application/json` | ✔ Conforme |

### 1 bis. Chemins d'actifs et ressources statiques

| # | Cas de test | Steps | Attendu | Résultat |
| :--- | :--- | :--- | :--- | :--- |
| AST-01 | CSS Tabler.io sur `/login` | ouvrir `/login`, onglet Réseau | `tabler.min.css` en `200 text/css` | ✔ Conforme |
| AST-02 | CSS applicatif sur `/login` | idem | `flotteo.css` en `200 text/css` | ✔ Conforme |
| AST-03 | JS Tabler.io sur `/login` | idem | `tabler.min.js` en `200 application/javascript` | ✔ Conforme |
| AST-04 | JS applicatif sur `/login` | idem | `app.js` en `200` | ✔ Conforme |
| AST-05 | Manifeste d'application | lire la source de `/login` | `app.json` en `200 application/json` | ✔ Conforme |
| AST-06 | Icône de site | idem | `flotteo.svg` en `200 image/svg+xml` | ✔ Conforme |
| AST-07 | Aucune alerte 404 en console | console navigateur sur `/login` | 0 erreur de chargement | ✔ Conforme |
| AST-08 | Pas de police 404 | onglet Réseau | Tabler 1.0.0 n'exige aucune police externe | ✔ Conforme |
| AST-09 | `tabler-vendors.min.css` inexistant | vérifier le paquet Tabler 1.0.0 | fichier non distribué, non appelé par Flotteo | ✔ Conforme |
| AST-10 | Action du formulaire de connexion | lire le HTML de `/login` | `action="/login"` aligné sur la route | ✔ Conforme |
| AST-11 | Static non réécrit | déposer un `.json` dans `assets/` | servi tel quel, PHP non exécuté | ✔ Conforme |
| AST-12 | MIME `.json` explicite | requêter `app.json` | `application/json` déclaré en `.htaccess` | ✔ Conforme |
| AST-13 | Sources sensibles | requêter `core/`, `models/`, `config/` | `403` | ✔ Conforme |
| AST-14 | Zone d'uploads | requêter `/uploads/` | `403` | ✔ Conforme |
| AST-15 | Registres via l'application | ouvrir `/docs/changelog` | `302` vers `/login` en anonyme, contenu en `200` après authentification | ✔ Conforme |
| AST-16 | Registre hors liste blanche | ouvrir `/docs/changelog.md` | refused, aucun contenu divulgué | ✔ Conforme |
| AST-17 | Manifeste malformé | invalider `app.json` | échec explicite, pas de régression de page | ✔ Conforme |
| AST-18 | Déploiement sous-répertoire | forcer `base_url = '/flotteo'` | URLs préfixées, routage résolu | ✔ Conforme |
| AST-19 | Cache des assets | requête répétée | `Expires` à 7 jours sur CSS/JS/SVG | ✔ Conforme |
| AST-20 | Titre de page | naviguer dans les modules | le titre suit la route active | ✔ Conforme |

## 2. Authentification & rôles

| # | Cas de test | Steps | Attendu | Résultat |
| :--- | :--- | :--- | :--- | :--- |
| AUTH-01 | Connexion valide | saisir email + mot de passe administrateur | redirection tableau de bord, toast de bienvenue | ⟨ À valider ⟩ |
| AUTH-02 | Mot de passe erroné | saisir un mot de passe invalide | message « Identifiants invalides », aucune session | ⟨ À valider ⟩ |
| AUTH-03 | Email inconnu | saisir un email inexistant | message « Identifiants invalides » | ⟨ À valider ⟩ |
| AUTH-04 | Compte désactivé | se connecter avec `actif = 0` | connexion refusée | ⟨ À valider ⟩ |
| AUTH-05 | Champs vides | valider le formulaire vide | blocage par validation HTML5 | ⟨ À valider ⟩ |
| AUTH-06 | Jeton CSRF absent | `POST /login` sans `_token` | redirection + message de sécurité, action refusée | ⟨ À valider ⟩ |
| AUTH-07 | Rôle lecture — accès dashboard | se connecter en `lecture_seule` | dashboard et graphiques accessibles | ⟨ À valider ⟩ |
| AUTH-08 | Rôle lecture — écriture interdite | tenter `POST /vehicules/enregistrer` en lecture seule | HTTP 403, page « Accès refusé » | ⟨ À valider ⟩ |
| AUTH-09 | Rôle modification — écriture autorisée | créer un véhicule en `modification` | enregistrement accepté | ⟨ À valider ⟩ |
| AUTH-10 | Rôle modification — admin interdite | ouvrir `/admin/utilisateurs` en `modification` | HTTP 403 | ⟨ À valider ⟩ |
| AUTH-11 | Rôle administration complet | ouvrir toutes les rubriques admin | accès autorisé | ⟨ À valider ⟩ |
| AUTH-12 | Déconnexion | valider le formulaire de déconnexion | session détruite, retour à l'écran de connexion | ⟨ À valider ⟩ |
| AUTH-13 | Accès anonyme à une page protégée | ouvrir `/vehicules` déconnecté | redirection vers `/login` | ⟨ À valider ⟩ |
| AUTH-14 | Changement de mot de passe | modifier le mot de passe via le profil | confirmation demandée, reconnexion requise | ⟨ À valider ⟩ |
| AUTH-15 | Profil — email déjà utilisé | saisir l'email d'un autre compte | enregistrement refusé | ⟨ À valider ⟩ |

## 3. Tableau de bord

| # | Cas de test | Steps | Attendu | Résultat |
| :--- | :--- | :--- | :--- | :--- |
| DASH-01 | Chargement des KPI | ouvrir `/dashboard` | 4 cartes KPI renseignées | ⟨ À valider ⟩ |
| DASH-02 | Échéancier des sorties | observer le graphe 1 | 12 points mensuels, tooltip « véhicule(s) à restituer » | ⟨ À valider ⟩ |
| DASH-03 | Donut entité | onglet « Entités » | répartition par propriétaire + total au centre | ⟨ À valider ⟩ |
| DASH-04 | Donut loueur | onglet « Loueurs » | bascule sans rechargement complet | ⟨ À valider ⟩ |
| DASH-05 | Donut lieu | onglet « Lieux » | répartition géographique | ⟨ À valider ⟩ |
| DASH-06 | Histogramme empilé entretien | graphe 3 | 6 typologies empilées, montants en € | ⟨ À valider ⟩ |
| DASH-07 | TCO par modèle | graphe 4 | barres horizontales triées par coût décroissant | ⟨ À valider ⟩ |
| DASH-08 | Radar des incidents | graphe 5a | répartition par type d'incident | ⟨ À valider ⟩ |
| DASH-09 | Tendance sinistralité | graphe 5b | courbe sur 12 mois | ⟨ À valider ⟩ |
| DASH-10 | Base vide | dashboard sans aucune donnée | graphes rendus sans erreur JavaScript | ⟨ À valider ⟩ |
| DASH-11 | Coût d'entretien cohérent | comparer carte KPI et graphe 3 | montants identiques (HT) | ⟨ À valider ⟩ |

## 4. Gestion de flotte

| # | Cas de test | Steps | Attendu | Résultat |
| :--- | :--- | :--- | :--- | :--- |
| FLT-01 | Création complète | renseigner tous les champs, valider | véhicule créé, toast de confirmation | ⟨ À valider ⟩ |
| FLT-02 | Immatriculation absente | valider sans immatriculation | champ obligatoire, création bloquée | ⟨ À valider ⟩ |
| FLT-03 | Nomenclature absente | valider sans modèle/entité/loueur/lieu | message d'erreur explicite | ⟨ À valider ⟩ |
| FLT-04 | Immatriculation dupliquée | créer deux fois la même plaque | création refusée | ⟨ À valider ⟩ |
| FLT-05 | Dates incohérentes | sortie antérieure à l'entrée | enregistrement refusé | ⟨ À valider ⟩ |
| FLT-06 | Modification | éditer une fiche, changer le statut | mise à jour, statut visible dans la liste | ⟨ À valider ⟩ |
| FLT-07 | Suppression avec confirmation | cliquer sur la corbeille | modale de confirmation, aucune alerte native | ⟨ À valider ⟩ |
| FLT-08 | Annulation de suppression | cliquer « Annuler » dans la modale | véhicule conservé | ⟨ À valider ⟩ |
| FLT-09 | Recherche | saisir une plaque partielle | liste filtrée | ⟨ À valider ⟩ |
| FLT-10 | Filtre entité | sélectionner une entité | seuls ses véhicules sont listés | ⟨ À valider ⟩ |
| FLT-11 | Filtre échéance | sélectionner « Échéances sous 90 jours » | badges J-x présents | ⟨ À valider ⟩ |
| FLT-12 | Réinitialisation | cliquer sur « Réinitialiser » | tous les véhicules réaffichés | ⟨ À valider ⟩ |
| FLT-13 | Fiche véhicule | ouvrir une fiche | cycle d'exploitation, jauge, entretien, incidents | ⟨ À valider ⟩ |
| FLT-14 | Lecture seule sur la liste | en `lecture_seule` | aucun bouton d'écriture ni de suppression | ⟨ À valider ⟩ |

## 5. Entretien

| # | Cas de test | Steps | Attendu | Résultat |
| :--- | :--- | :--- | :--- | :--- |
| ENT-01 | Création de prestation | véhicule + type + date + coût HT | enregistrement, TTC calculé à 20 % | ⟨ À valider ⟩ |
| ENT-02 | Pré-remplissage TTC | saisir un montant HT | TTC mis à jour automatiquement | ⟨ À valider ⟩ |
| ENT-03 | Modification | cliquer sur le crayon | modale pré-remplie, enregistrement mis à jour | ⟨ À valider ⟩ |
| ENT-04 | Coût négatif | saisir un montant négatif | enregistrement refusé | ⟨ À valider ⟩ |
| ENT-05 | Véhicule absent | valider sans véhicule | message d'erreur | ⟨ À valider ⟩ |
| ENT-06 | Filtres croisés | véhicule + catégorie + période | liste et cumul cohérents | ⟨ À valider ⟩ |
| ENT-07 | Suppression avec confirmation | corbeille sur une ligne | modale puis suppression | ⟨ À valider ⟩ |
| ENT-08 | Cumul par véhicule | ouvrir une fiche véhicule | total entretien HT affiché | ⟨ À valider ⟩ |

## 6. Incidents & pièces jointes

| # | Cas de test | Steps | Attendu | Résultat |
| :--- | :--- | :--- | :--- | :--- |
| INC-01 | Création d'incident | véhicule, type, date, lieu, descriptif | dossier créé, redirection vers la fiche | ⟨ À valider ⟩ |
| INC-02 | Incident responsable | cocher « Véhicule responsable » | badge rouge sur la liste | ⟨ À valider ⟩ |
| INC-03 | Circuit de statut | passer de Ouvert à Clôturé | badge vert, filtre par statut opérationnel | ⟨ À valider ⟩ |
| INC-04 | Téléversement PDF | joindre une facture PDF | fichier enregistré, nommage serveur opaque | ⟨ À valider ⟩ |
| INC-05 | Téléversement image | joindre une photo JPEG | fichier enregistré et listé | ⟨ À valider ⟩ |
| INC-06 | Format refusé | tenter un `.exe` ou un `.php` | refus + message « type non autorisé » | ⟨ À valider ⟩ |
| INC-07 | Fichier surdimensionné | joindre un fichier > 8 Mo | refus + message de taille | ⟨ À valider ⟩ |
| INC-08 | Téléchargement | cliquer sur une pièce jointe | flux PHP avec `Content-Disposition` | ⟨ À valider ⟩ |
| INC-09 | Téléversement d'un script PHP renommé | joindre un fichier `.png` contenant du PHP | refusé par contrôle MIME réel | ⟨ À valider ⟩ |
| INC-10 | Suppression d'une pièce | corbeille sur un fichier | modale puis suppression du disque et de la base | ⟨ À valider ⟩ |
| INC-11 | Suppression d'un incident | supprimer un dossier avec pièces | pièces orphelines supprimées | ⟨ À valider ⟩ |
| INC-12 | Filtres incidents | type + statut + période | liste filtrée | ⟨ À valider ⟩ |

## 7. Administration

| # | Cas de test | Steps | Attendu | Résultat |
| :--- | :--- | :--- | :--- | :--- |
| ADM-01 | Création d'utilisateur | nom, email valide, rôle, mot de passe | compte créé | ⟨ À valider ⟩ |
| ADM-02 | Mot de passe trop court | saisir moins de 8 caractères | création refusée | ⟨ À valider ⟩ |
| ADM-03 | Email invalide | saisir une adresse malformée | enregistrement refusé | ⟨ À valider ⟩ |
| ADM-04 | Email déjà utilisé | réutiliser un email existant | enregistrement refusé | ⟨ À valider ⟩ |
| ADM-05 | Modification d'utilisateur | éditer le rôle et l'état | compte mis à jour | ⟨ À valider ⟩ |
| ADM-06 | Suppression d'un compte | corbeille | modale puis suppression | ⟨ À valider ⟩ |
| ADM-07 | Auto-suppression interdite | tenter de supprimer son propre compte | action refusée | ⟨ À valider ⟩ |
| ADM-08 | Dernier administrateur | rétrograder ou désactiver le dernier admin | action refusée, message explicite | ⟨ À valider ⟩ |
| ADM-09 | Dictionnaire marques | ajouter une marque | entrée visible, models sélectionnables | ⟨ À valider ⟩ |
| ADM-10 | Dictionnaire modèles | rattacher un modèle à une marque | marque résolue dans la liste | ⟨ À valider ⟩ |
| ADM-11 | Dictionnaire entités | ajouter une entité avec code | code unique respecté | ⟨ À valider ⟩ |
| ADM-12 | Dictionnaire loueurs | ajouter un loueur avec contact | email et téléphone enregistrés | ⟨ À valider ⟩ |
| ADM-13 | Dictionnaire lieux | ajouter un site et une ville | entrée disponible dans les fiches véhicule | ⟨ À valider ⟩ |
| ADM-14 | Types d'intervention | ajouter une prestation avec catégorie | catégorie respectée, incluse dans les graphes | ⟨ À valider ⟩ |
| ADM-15 | Suppression référencée | supprimer une marque utilisée par un modèle | refus + message d'explication | ⟨ À valider ⟩ |
| ADM-16 | Paramétrage des paliers | passer les paliers à 60/120/180 | aperçu et mail recalculés | ⟨ À valider ⟩ |
| ADM-17 | Email gestionnaire invalide | saisir une adresse malformée | enregistrement des paramètres refusé | ⟨ À valider ⟩ |
| ADM-18 | Envoi manuel du récapitulatif | cliquer sur le bouton d'envoi | mail envoyé ou message d'absence de données | ⟨ À valider ⟩ |

## 8. Alertes & tâche cron

| # | Cas de test | Steps | Attendu | Résultat |
| :--- | :--- | :--- | :--- | :--- |
| ALT-01 | Regroupement par palier | véhicule à J-50 avec paliers 90/180/270 | rangé dans le palier « Sous 3 mois » | ⟨ À valider ⟩ |
| ALT-02 | Aucun palier atteint | véhicule à J-400 | exclu du récapitulatif | ⟨ À valider ⟩ |
| ALT-03 | Véhicule déjà restitué | date de sortie effective renseignée | exclu du récapitulatif | ⟨ À valider ⟩ |
| ALT-04 | Destinataire non configuré | vider l'email gestionnaire | message « adresse non configurée », aucun envoi | ⟨ À valider ⟩ |
| ALT-05 | Exécution CLI | `php scripts/alert_cron.php` | sortie informative, code retour 0 | ⟨ À valider ⟩ |
| ALT-06 | Alertes désactivées | désactiver le paramètre | aucune alerte, y compris manuelle | ⟨ À valider ⟩ |
| ALT-07 | Contenu du mail | envoyer le récapitulatif | tableau HTML avec immat., modèle, loueur, date, reste | ⟨ À valider ⟩ |
| ALT-08 | Tâche cron | planifier la commande dans crontab | exécution hebdomadaire sans intervention | ⟨ À valider ⟩ |

## 9. Non-régression transverse

| # | Cas de test | Action | Attendu | Résultat |
| :--- | :--- | :--- | :--- | :--- |
| REG-01 | Navigation latérale | suivre chaque entrée du menu | tous les liens mènent à la bonne page | ⟨ À valider ⟩ |
| REG-02 | Route inconnue | saisir `/flotteo/inexistant` | page 404 de Flotteo | ⟨ À valider ⟩ |
| REG-03 | Injection SQL en recherche | saisir `'; DROP TABLE vehicules; --` | requête traitée sans erreur, aucune perte de données | ⟨ À valider ⟩ |
| REG-04 | Tentative d'accès direct au modèle | ouvrir `/flotteo/models/Vehicle.php` | HTTP 403 | ⟨ À valider ⟩ |
| REG-05 | Tentative d'accès au fichier de config | ouvrir `/flotteo/config/database.php` | HTTP 403 | ⟨ À valider ⟩ |
| REG-06 | XSS dans un champ texte | saisir `<script>alert(1)</script>` en immatriculation | valeur échappée, aucun exécut | ⟨ À valider ⟩ |
| REG-07 | Session expirée en cours d'usage | laisser la session expirer puis valider un formulaire | redirection vers la connexion, message clair | ⟨ À valider ⟩ |
| REG-08 | Compatibilité mobile | affichage < 768 px | menu repliable, tableaux défilables | ⟨ À valider ⟩ |
| REG-09 | Rendu sans JavaScript | désactiver JavaScript | pages principales restent lisibles (formulaires natifs) | ⟨ À valider ⟩ |
| REG-10 | Registres documentaires | consulter `docs/` | 6 fichiers présents et synchronisés | ✔ Conforme |

---

## 10. Déploiement & première installation

Cas exécutés sur une application **non installée** (`storage/install.lock` absent).
Socle de vérification : serveur intégré PHP, afin de pouvoir simuler la présence
ou l'absence de verrou sans toucher à une base existante.

### 10.1 Verrouillage des routes

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | --- | --- |
| INS-01 | Application non installée, requête `/` | redirection HTTP 302 vers `/install` | ✔ Conforme |
| INS-02 | Application non installée, requête `/login` | redirection HTTP 302 vers `/install` | ✔ Conforme |
| INS-03 | Application installée, requête `/install` | redirection HTTP 302 vers `/login`, aucune réinitialisation possible | ✔ Conforme |
| INS-04 | Application installée, `POST /api/install/tester` | redirection HTTP 302 vers `/login` | ✔ Conforme |
| INS-05 | Application installée, requête `/login` | page servie normalement, plus aucune redirection vers `/install` | ✔ Conforme |
| INS-06 | Verrou corrompu, `config/database.php` valide | application servie normalement, seul l'assistant reste verrouillé | ✔ Conforme |

### 10.2 Assistant d'installation

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | --- | --- |
| INS-07 | Affichage de `/install` | page autonome HTTP 200, formulaire en deux étapes, schéma des 12 tables listé | ✔ Conforme |
| INS-08 | Ressources de l'assistant | `flotteo.css`, `app.js`, `install.js`, `tabler.min.css`, `tabler.min.js` et la police `.woff2` répondent tous HTTP 200 | ✔ Conforme |
| INS-09 | Test de connexion, identifiants valides | JSON `{"success":true,...}`, code HTTP 200, passage à l'étape 2 | ⟨ À valider — accès MySQL administrateur requis ⟩ |
| INS-10 | Test de connexion, couple incorrect | JSON `{"success":false,...}`, code HTTP 422, message « Accès refusé », aucune trace technique | ✔ Conforme |
| INS-11 | Test de connexion, jeton CSRF invalide | JSON d'échec, code HTTP 419 | ✔ Conforme |
| INS-12 | Nom de base containing des caractères interdits | refus de validation, injection impossible | ⟨ À valider ⟩ |
| INS-13 | Confirmation du mot de passe divergente | message explicite, installation non déclenchée | ✔ Conforme |
| INS-14 | Email administrateur invalide | message explicite citant l'email et la longueur minimale du mot de passe | ✔ Conforme |
| INS-15 | Base injoignable | échec propre, page rendue en HTTP 200 avec motif, aucune sortie PHP parasite | ✔ Conforme |
| INS-16 | Treize tentatives de connexion en session | HTTP 429, message « Trop de tentatives successives » | ⟨ À valider ⟩ |
| INS-17 | Mot de passe MySQL | jamais réaffiché par la vue, jamais présent dans l'URL, jamais journalisé | ✔ Conforme |

### 10.3 Intégrité de l'interface

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | --- | --- |
| INS-18 | Boîtes de dialogue natives dans `install.js` | aucune occurrence de `alert(`, `confirm(`, `prompt(` | ✔ Conforme |
| INS-19 | Appels réseau externes | aucun domaine CDN, `unpkg` ou `jsdelivr` dans les fichiers de l'assistant | ✔ Conforme |
| INS-20 | Page 503 de verrou défectueux | page rendue en HTTP 503, instructions de restauration, assistant non rouvert | ✔ Conforme |
| INS-21 | Avertissements PHP pendant l'assistant | 0 occurrence de `warning`, `notice`, `fatal` ou `deprecated` dans le journal du serveur | ✔ Conforme |

### 10.5 Ajustement ergonomique de la mire d'installation

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | --- | --- |
| INS-22 | Largeur de frame | le conteneur de l'assistant n'emploie plus `.container-tight` (30rem / 480px) | ✔ Conforme — `.install-container` |
| INS-23 | Confort de lecture desktop (1280 px) | largeur utile de 56rem, aucun libellé de champ replié sur deux lignes | ✔ Conforme |
| INS-24 | Confort de lecture tablette (768 px) | largeur utile de 48rem, disposition en deux rangées de champs préservée | ✔ Conforme |
| INS-25 | Ligne hôte + port | hôte et port alignés sur une même ligne, largeurs respectives 8/4 et 4/4 colonnes | ✔ Conforme |
| INS-26 | Ligne nom de base + utilisateur | deux champs de largeur égale, indication sur une seule ligne | ✔ Conforme |
| INS-27 | Décalage vertical des champs | hauteur de ligne des libellés et des champs identique d'une colonne à l'autre | ✔ Conforme |
| INS-28 | Alignement des boutons d'étape 1 et 2 | action principale alignée à droite, note d'appoint à droite et jamais coupée | ✔ Conforme |
| INS-29 | Mobile 375 px | conteneur fluide, marges de 1rem conservées, aucun débordement horizontal | ⟨ À valider ⟩ |
| INS-30 | Mobile 375 px, actions | boutons empilés pleine largeur, validation située au-dessus du retour | ⟨ À valider ⟩ |
| INS-31 | Page 503 de configuration verrouillée | même largeur de frame que l'assistant, rendu homogène | ✔ Conforme |
| INS-32 | Feuille de style servie | `flotteo.css` répond HTTP 200, accolades équilibrées, aucun 404 d'actif | ✔ Conforme |

### 10.6 Affichage de l'étape 2 (INC-004, régression)

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | --- | --- |
| INS-33 | Affichage initial de `/install` | étape 1 visible, étapes 2 et 3 masquées | ✔ Conforme |
| INS-34 | Bascule vers l'étape 2 après test réussi | les quatre champs de saisie sont effectivement visibles à l'écran | ✔ Conforme — régression INC-004 |
| INS-35 | Nœud masqué et nœud basculé cohérents | `install.js` pilote le nœud portant le `d-none` initial | ✔ Conforme — `#etape-2` porte désormais le masque, plus le `<form>` |
| INS-36 | Présence des quatre champs | `nom_d_utilisateur`, `admin_email`, `admin_password`, `admin_password_confirm` présents dans le DOM rendu | ✔ Conforme |
| INS-37 | Types et contraintes HTML | `text` / `email` / `password` / `password`, les quatre `required`, `minlength="10"` sur les deux secrets | ✔ Conforme |
| INS-38 | Bouton de validation étape 2 → 3 | `bouton-installer` présent, `type="submit"`, bascule vers l'étape 3 puis soumission | ✔ Conforme |
| INS-39 | Retour à l'étape 1 | étape 1 de nouveau visible, étape 2 masquée | ✔ Conforme |
| INS-40 | Contrat de noms côté serveur | `InstallController::executer()` lit `nom_d_utilisateur`, `admin_email`, `admin_password`, `admin_password_confirm` | ✔ Conforme — 3 réponses serveur distinctes obtenues |
| INS-41 | Confirmation divergente | message explicite, installation non déclenchée | ✔ Conforme |
| INS-42 | E-mail invalide et mot de passe court | message citant l'e-mail et la longueur minimale | ✔ Conforme |
| INS-43 | Champs valides, base injoignable | échec propre en phase « installation », aucune sortie PHP parasite | ✔ Conforme |
| INS-44 | Absence d'exception masquée | 0 `warning` / `notice` / `fatal` / `deprecated` dans le journal serveur pendant le tunnel | ✔ Conforme |
| INS-45 | Rendu sans JavaScript | l'étape 2 reste lisible et soumettable : le formulaire n'est plus masqué en dur | ✔ Conforme |

### 10.7 Doublon visuel du message d'erreur (INC-005, régression)

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | --- | --- |
| INS-46 | Échec PDO « accès refusé » | message « Connexion impossible » rendu **une seule fois**, dans l'alerte rouge | ✔ Conforme — régression INC-005 |
| INS-47 | Comptage des occurrences dans le DOM | 1 occurrence du message, 1 bloc `.alert`, 0 bloc `.text-secondary` | ✔ Conforme |
| INS-48 | `detail` identique au message | le bloc gris n'est pas rendu : pas de répétition | ✔ Conforme |
| INS-49 | `detail` réellement distinct | le bloc gris est rendu, une seule fois, sous le message | ✔ Conforme |
| INS-50 | Test de connexion réussi | alerte verte, message unique, passage à l'étape 2 | ✔ Conforme |
| INS-51 | `detail` transmis par le serveur | n'est plus perdu par `afficherRetour()` et restitué quand il est distinct | ✔ Conforme |
| INS-52 | Positionnement de l'alerte | sous les champs, au-dessus du bouton « Tester la connexion » | ✔ Conforme |
| INS-53 | Valeurs conservées après re-rendu serveur | hôte, port, nom de base et utilisateur réémis à l'identique | ✔ Conforme |
| INS-54 | Secret jamais réémis | aucune valeur sur `db_password`, secret absent de la réponse HTML | ✔ Conforme |

### 10.9 Bandeau supérieur et icônes

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | --- | --- |
| NAV-01 | Largeur de la barre | conteneur `container-fluid`, aucune limite de largeur fixe | ✔ Conforme |
| NAV-02 | Disparition du sidebar | plus aucun `navbar-vertical`, `Core\Auth` appelé une seule fois | ✔ Conforme |
| NAV-03 | Identité | « Flotteo » avec icône `fa-car` en tête de barre, lien vers `/dashboard` | ✔ Conforme |
| NAV-04 | Modules dans la barre | 6 entrées : Tableau de bord, Véhicules, Révisions, Incidents, Paramètres, Utilisateurs | ✔ Conforme |
| NAV-05 | Icônes des modules | `fa-gauge-high`, `fa-car`, `fa-screwdriver-wrench`, `fa-triangle-exclamation`, `fa-sliders`, `fa-users` | ✔ Conforme |
| NAV-06 | Droits appliqués à la barre | Paramètres et Utilisateurs masqués pour un utilisateur non administrateur | ✔ Conforme |
| NAV-07 | Profil et déconnexion | avatar, nom, e-mail, badge de rôle, `POST /logout` avec jeton CSRF | ✔ Conforme |
| NAV-08 | Repli responsive | `navbar-expand-xl` + `collapse`, basculeur `fa-bars` sous 1200 px | ✔ Conforme — **INC-025 corrigé** ; `-lg` maintenait une barre plus large que la fenêtre entre 992 et 1200 px |
| NAV-09 | Typographie | police Inter de Tabler.io conservée, aucune surcharge de `font-family` | ✔ Conforme |
| NAV-10 | Aucune icône SVG | `views/layout/header.php` et `modals.php` ne contiennent plus de `<svg>` | ✔ Conforme |
| LAY-01 | Largeur du corps de page | pleine largeur de la fenêtre, sans plafond | ✔ Conforme — **INC-026 corrigé**, `container-xl` plafonnait à 1320 px ; `container-fluid` sans plafond, `.layout-boxed` non employée |
| LAY-02 | Marges latérales | 1,5 rem puis 2 rem à partir de 992 px | ✔ Conforme — `px-4 px-lg-5`, mesuré 24 px puis 32 px de chaque côté |
| LAY-03 | Alignement barre / corps | l'identité et les liens de pied de page partent de la même verticale | ✔ Conforme — même padding `px-4 px-lg-5` sur les deux, mesuré identique |
| LAY-04 | Aucun défilement horizontal | la page ne déborde jamais | ✔ Conforme — **INC-025 corrigé**, 3 pages × 6 largeurs (1920 à 500) sous Chrome headless ; débordement de 101 px à 1024 px avant correction, aucun après |
| LAY-05 | Tableau très large | remplit la largeur disponible, ou défile en interne | ✔ Conforme — tableau à 12 colonnes : 1798 px à 1920, puis `overflow-x: auto` interne de 1280 à 500 px, page jamais débordée |
| LAY-06 | Graphiques | largeur suivant le conteneur, sans débordement | ✔ Conforme — 4 graphiques ApexCharts, de 866 px (1920) à 418 px (1024), aucune largeur fixe dans `dashboard.js` |
| LAY-07 | Classes natives | uniquement Tabler/Bootstrap | ✔ Conforme — `container-fluid`, `px-4`, `px-lg-5`, `navbar-expand-xl` ; aucun `container-xl` restant dans le layout |
| FA-01 | Auto-hébergement | `fa-solid-900.woff2` et `fa-brands-400.woff2` servis en HTTP 200, type `font/woff2` | ✔ Conforme |
| FA-02 | Aucun CDN | aucun appel à un domaine externe dans le CSS ou le HTML | ✔ Conforme |
| FA-03 | Sous-ensemble cohérent | les 37 glyphes de `Core\Icon::GLYPHES` sont tous déclarés dans `fontawesome.css` | ✔ Conforme |
| FA-04 | Garde-fou | `Icon::solid('icone-inconnue')` lève une exception au lieu d'afficher un carré vide | ✔ Conforme |
| FA-05 | Aucune alerte native | 0 `alert(` / `confirm(` / `prompt(` ; la confirmation reste la modale Tabler | ✔ Conforme |
| FA-06 | Poids des actifs | CSS Font Awesome 6 Ko ; polices 230 Ko au total | ✔ Conforme |

### 10.11 Jeu de données de démonstration

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | --- | --- |
| DEM-01 | Contrôle sans base | `--verifier` sort en 0 et annonce 3 utilisateurs, 15 véhicules, 23 interventions, 7 incidents, 14 fichiers | ✔ Conforme |
| DEM-02 | Intégrité référentielle | toute clé étrangère du jeu pointe vers une ligne existante | ✔ Conforme — détecté à l'écriture |
| DEM-03 | Valeurs d'ENUM | statuts, catégories, rôles et types tous membres de leur ENUM | ✔ Conforme |
| DEM-04 | Format SIV | 15 immatriculations en `AA-123-DD`, `2A` accepté pour la Corse | ✔ Conforme |
| DEM-05 | Cohérence du département | le code département de chaque immatriculation correspond au lieu d'exploitation | ✔ Conforme — 2 anomalies corrigées |
| DEM-06 | Monotonie des kilométrages | le kilométrage n'augmente pas entre deux interventions successives | ✔ Conforme — logique corrigée, 6 anomalies corrigées |
| DEM-07 | TVA | `cout_ttc = cout_ht x 1,20` sur les 23 lignes | ✔ Conforme |
| DEM-08 | Paliers d'alerte | un véhicule sort dans 90, 180 et 270 jours exactement | ✔ Conforme |
| DEM-09 | Cas limites | échéance dépassée, restitution en retard, alerte sous 30 jours, 2 immobilisés, 1 restitué, 1 non immatriculé, 1 loué | ✔ Conforme |
| DEM-10 | Mot de passe | le condensat publié vérifie bien `Password123!` en Argon2id | ✔ Conforme |
| DEM-11 | Ordre des dépendances | `INSERT` dans l'ordre : utilisateurs, référentiels, paramètres, véhicules, entretien, incidents, fichiers | ✔ Conforme |
| DEM-12 | Clés étrangères | `FOREIGN_KEY_CHECKS = 0` puis `= 1` encadrant la purge | ✔ Conforme |
| DEM-13 | Échappement SQL | apostrophes doublées, aucune barre oblique d'échappement | ✔ Conforme |
| DEM-14 | Équilibrage des littéraux | 0 ligne SQL avec un nombre impair d'apostrophes | ✔ Conforme |
| DEM-15 | Fidélité au schéma | 0 référence hors table (contrairement à l'ancien `demodata.sql`) | ✔ Conforme |
| DEM-16 | Configuration illisible | message d'erreur clair, aucune trace PHP exposée, code de sortie 1 | ✔ Conforme |
| DEM-17 | Option inconnue | refusée avec la liste des modes, code de sortie 2 | ✔ Conforme |
| DEM-18 | Stabilité du SQL | deux générations successives produisent le même contenu hors condensat | ✔ Conforme |

### 10.12 Verrou d'installation et sous-ensemble Font Awesome

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| VER-01 | `a_installer` + `/install` | 200, assistant servi | ✔ Conforme |
| VER-02 | `a_installer` + route applicative | 302 vers `/install` | ✔ Conforme |
| VER-03 | `installe` + `/install` | 302 vers `/login`, aucun formulaire | ✔ Conforme |
| VER-04 | `defaillant` + `/install` | 302 vers `/login`, aucun formulaire servi | ✔ Conforme — **faille INC-007 corrigée** |
| VER-05 | `defaillant` + `/api/install/tester` | 302 vers `/login` | ✔ Conforme |
| VER-06 | `defaillant` + route applicative | 503 avec procédure de remise en état | ✔ Conforme |
| VER-07 | Matrice 3 états × 2 types de route | 6/6 décisions conformes | ✔ Conforme — invariant « témoin présent ⇒ assistant fermé » respecté |
| VER-08 | Champs de l'assistant | `db_host`, `db_port`, `db_name`, `db_username`, `db_password`, `admin_email`, `admin_password`, `admin_password_confirm`, `nom_d_utilisateur`, `_token` | ✔ Conforme — 3 étapes, `nom_d_utilisateur` propagé |
| FA-01 | `Core\Icon::GLYPHES` ↔ `fontawesome.css` | 37 = 37, aucun écart dans aucun sens | ✔ Conforme |
| FA-02 | Codes de glyphe | aucun doublon | ✔ Conforme |
| FA-03 | Chemins de polices | `../fonts/*.woff2` résolus depuis `public/assets/css/` | ✔ Conforme |
| FA-04 | En-tête woff2 | `wOF2` sur les deux fontes | ✔ Conforme |
| FA-05 | Service HTTP | CSS et deux polices en 200 | ✔ Conforme |
| FA-06 | Règles CSS | accolades équilibrées, aucune règle d'animation sans `@keyframes` | ✔ Conforme — **INC-008 corrigé**, 2 118 o supprimés |
| FA-07 | Appels CDN | aucune occurrence de `cdn.`/`cdnjs`/`jsdelivr`/`unpkg`/`googleapis` | ✔ Conforme |
| FA-08 | Icône absente du sous-ensemble | `Icon::solid()` lève une `InvalidArgumentException` explicite plutôt que d'afficher un carré | ✔ Conforme |

### 10.13 Injection réelle en base MySQL

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| IMP-01 | Sauvegarde préalable | `mysqldump` complet des 12 tables avant tout import | ✔ Conforme — 13 825 o, 12 `CREATE TABLE`, 3 tables non vides incluses |
| IMP-02 | Compatibilité SQL / schéma | chaque liste de colonnes existe et chaque ligne a le bon nombre de valeurs | ✔ Conforme — **1 défaut trouvé (INC-009)** puis corrigé, 14/14 blocs valides |
| IMP-03 | Import | code de sortie 0, aucune erreur MySQL | ✔ Conforme — après correction |
| IMP-04 | Reproductibilité | deux imports successifs produisent le même volume | ✔ Conforme |
| IMP-05 | Comptage par table | 110 lignes réparties sur les 12 tables | ✔ Conforme |
| IMP-06 | Intégrité référentielle | 0 orphelin sur les 9 relations | ✔ Conforme |
| IMP-07 | Comptes de démonstration | 3 comptes, condensat Argon2id vérifiant `Password123!` | ✔ Conforme |
| IMP-08 | Format SIV | 15/15 immatriculations au motif `AA-123-DD` (`2A` accepté) | ✔ Conforme |
| IMP-09 | TVA | 23 lignes, 0 incohérence sur `cout_ttc = cout_ht x 1,20` | ✔ Conforme |
| IMP-10 | Monotonie du kilométrage | 0 régression sur les 23 opérations | ✔ Conforme |
| IMP-11 | `AUTO_INCREMENT` | réinitialisés par les `TRUNCATE` (rang + 1) | ✔ Conforme |
| IMP-12 | Statuts d'incident | 3 `ouvert`, 2 `en_traitement`, 2 `cloture` — tous membres de l'ENUM | ✔ Conforme |
| IMP-13 | Cas limites du parc | 12 `actif`, 2 `immobilise`, 1 `sorti`, 1 restitué, 1 non immatriculé, 1 loué à un tiers | ✔ Conforme |

### 10.14 Item actif de la barre supérieure (INC-010)

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| NAV-01 | `/admin/parametres` | liseré actif sur « Paramètres » | ✔ Conforme — **INC-010 corrigé**, l'item ne pouvait pas être actif |
| NAV-02 | `/admin/utilisateurs` | liseré actif sur « Utilisateurs » | ✔ Conforme — **même cause que NAV-01**, également corrigé |
| NAV-03 | `/vehicules` et `/vehicules/voir` | « Véhicules » actif dans les deux cas | ✔ Conforme — inchangé |
| NAV-04 | `/entretien`, `/incidents`, `/incidents/voir` | module correspondant actif | ✔ Conforme — inchangé |
| NAV-05 | `/incidents/fichier/telechargement` | « Incidents » reste actif (sous-chemin) | ✔ Conforme |
| NAV-06 | `/` et `/dashboard` | « Tableau de bord » actif | ✔ Conforme — racine traitée explicitement |
| NAV-07 | `/admin/dictionnaires`, `/admin/dictionnaires/marques`, `/profil` | aucun item actif (absents de la barre) | ✔ Conforme |
| NAV-08 | Matrice complète | 13/13 routes cohérentes | ✔ Conforme — 5 routes en défaut avant correction |
| NAV-09 | Structure Tabler | classe `active` sur `nav-item` **et** `nav-link`, plus `aria-current="page"` | ✔ Conforme — identique à tous les autres modules |
| NAV-10 | Mécanisme du liseré | règle Tabler `.navbar-expand-xl .nav-item.active:after` non masquée par le CSS maison | ✔ Conforme — `flotteo.css` ne fixe que `font-weight`, jamais de bordure ; filet de 2 px mesuré de 1200 à 1920 px |
| NAV-11 | Détection en sous-dossier | préfixe de base retiré avant comparaison | ✔ Conforme — `Core\Router::currentPath()` le garantit |
| NAV-12 | Titre de page | titre propre à chaque page d'administration | ✔ Conforme — l'ancienne table ne renvoyait qu'« Administration » |

### 10.16 Référentiels, icônes et guide utilisateur

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| REF-01 | Les six référentiels sont rendus | marques, modeles, entites, loueurs, lieux, types_intervention | ✔ Conforme — 6 rendus, 8,4 à 10,7 ko |
| REF-02 | Modale d'ajout et d'édition | `modal-blur` unique, titre basculant | ✔ Conforme — présents sur les 6 |
| REF-03 | Confirmation de suppression | modale Tabler, jamais `confirm()` | ✔ Conforme — attribut `data-confirmer` |
| REF-04 | Icônes | Font Awesome, aucun SVG inline | ✔ Conforme — **0 SVG** sur les 6 rendus |
| REF-05 | Accessibilité des boutons | `aria-label` sur modification et suppression | ✔ Conforme |
| REF-06 | Échappement JS du titre | `</script>` neutralisé | ✔ Conforme — `JSON_HEX_TAG`, guillemets et parenthèses équilibrés |
| REF-07 | Valeurs hostiles dans une entrée | aucune injection | ✔ Conforme — `<script>` échappé |
| REF-08 | Jeton CSRF | présent sur les deux formulaires | ✔ Conforme |
| REF-09 | Entrée « Référentiels » dans la barre | présente, niveau `administration` | ✔ Conforme — liseré actif sur `/admin/dictionnaires` et ses sous-chemins |
| REF-10 | Avant correction | l'écran était atteignable | ✔ Conforme — **INC-011 corrigé**, aucun lien ne menait à la page |
| REF-11 | Bouton « Éditer » | la modale s'ouvre et se préremplit | ✔ Conforme — **INC-018 corrigé** ; 3 attributs présents, préremplissage rejoué sous jsdom sur 2 lignes |
| REF-12 | Discrimination ajout / modification | « Ajouter » et affichage programmatique ouvrent un formulaire vide, « Éditer » préremplit | ✔ Conforme — discriminant `data-edition-dictionnaire`, pas `data-bs-target` |
| REF-13 | Préremplissage d'un menu déroulant | `marque_id` résolu vers l'option correspondante | ✔ Conforme — `marque_id=4` → « Citroën », `marque_id=3` → « Renault » |
| REF-14 | Titre de la modale | bascule entre « Ajouter — » et « Modifier — » | ✔ Conforme — les deux libellés produits par `json_encode` |
| REF-15 | Alertes natives | aucun `alert()`, `confirm()` ou `prompt()` | ✔ Conforme — suppression par `flotteoConfirmer()` |
| REF-16 | Barre d'onglets | liseré inférieur sur la table courante | ✔ Conforme — **INC-019 corrigé**, `nav-underline`, 6 onglets dont 1 actif sur `nav-item` et `nav-link` |
| GUI-01 | `/docs/user_guide` | guide rendu dans le layout, hors registre refusé | ✔ Conforme — `user_guide` autorisé, `hack` et traversées de chemin rejetés par `Models\Registre` |
| GUI-02 | Fichiers générés | 4 vues HTML autonomes | ✔ Conforme — guide 13 053 octets |
| GUI-03 | Lien de pied de page | présent | ✔ Conforme — exigé par le §3 des règles |
| GUI-04 | Intégration au layout | barre supérieure, en-tête, pied de page présents | ✔ Conforme — **INC-021 corrigé** ; plus de `readfile()`, 1 seul `<!doctype>` et 1 seul `<html>` par page |
| GUI-05 | Composant carte et mise en page | contenu dans une `.card`, navigation `col-lg-3` / document `col-lg-9` | ✔ Conforme — 2 `.card`, 4 entrées de navigation, 1 active avec `aria-current="page"` |
| GUI-06 | Navigation entre registres | les quatre entrées mènent à leur registre | ✔ Conforme — `/docs/{cle}` pour les quatre |
| GUI-07 | Entrée sans registre | `/docs` ouvre le guide utilisateur | ✔ Conforme — route `index` redirigeant vers `/docs/user_guide` |
| GUI-08 | Titre de page et d'onglet | un titre distinct par registre | ✔ Conforme — 4 entrées dans `header.php` |
| GUI-09 | Icônes | Font Awesome, aucun emoji | ✔ Conforme — **INC-022 corrigé**, `fa-book`, `fa-file-lines`, `fa-list`, `fa-check` |
| GUI-10 | Présentation du corps | titres, listes et tableaux selon Tabler | ✔ Conforme — **INC-023 corrigé**, classe `.markdown` ajoutée à `flotteo.css` |
| GUI-11 | Contenu identique à la vue compilée | même rendu dans l'application et dans le fichier HTML | ✔ Conforme — comparaison octet par octet du corps des 4 registres avant/après extraction du moteur |
| GUI-12 | Vues autonomes préservées | les 4 fichiers HTML restent ouvrables hors application | ✔ Conforme — `doctype`, `html`, `navbar-vertical` et `.card` présents dans les 4 fichiers |
| GUI-13 | Performance de conversion | rendu à la demande acceptable | ✔ Conforme — 0,5 ms (guide) à 3,5 ms (recette), 10 mesures par registre |
| GUI-14 | Source unique | une seule description des quatre registres | ✔ Conforme — **INC-024 corrigé**, `Models\Registre::DOCUMENTS` lu par le contrôleur et le script |

### 10.15 Refonte de « Paramètres système »

#### 10.15.1 Navigation par sections et section « Messagerie »

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| MSG-01 | Navigation verticale | deux entrées, un quart / trois quarts (`col-lg-3` / `col-lg-9`) | ✔ Conforme — `list-group list-group-flush`, `row g-4` |
| MSG-02 | Section active | `active` et `aria-current="page"` sur l'entrée courante | ✔ Conforme |
| MSG-03 | Accès direct | `/admin/parametres` ouvre la section par défaut | ✔ Conforme — repli sur « Messagerie » |
| MSG-04 | Section inconnue | repli sur la section par défaut, aucune erreur | ✔ Conforme — `{section}` bornée par `isset()` |
| MSG-05 | Titre de page | titre propre à chaque section | ✔ Conforme — deux entrées ajoutées à la table de `header.php` |
| MSG-06 | Isolation des sections | enregistrer la messagerie ne touche pas aux alertes | ✔ Conforme — 7 clés écrites, **0 chevauchement** avec les 5 clés d'alertes |
| MSG-07 | Visibilité du bloc SMTP | bloc visible **sans dépendre du JavaScript**, quel que soit le mode | ✔ Conforme — masquage supprimé ; 5 champs présents avec `mail_transport = mail` |
| MSG-08 | Mot de passe enregistré | jamais renvoyé au navigateur, invitation de conservation affichée | ✔ Conforme — **aucune fuite** dans le HTML rendu |
| MSG-09 | Effacement du mot de passe | case dédiée, effective même si le champ est saisi | ✔ Conforme |
| MSG-10 | Validation des saisies | expéditeur obligatoire, port et modes bornés | ✔ Conforme — 19 cas, aucune déviation |
| MSG-11 | Test d'envoi | éprouve les valeurs saisies, pas les valeurs enregistrées | ✔ Conforme |
| MSG-12 | Échappement des sorties | aucune balise injectée par une valeur hostile | ✔ Conforme — `script`, `onerror` et `<b>` échappés |
| MSG-13 | Formulaires | pas d'imbrication | ✔ Conforme — 1 formulaire en Messagerie, 2 en Alertes |
| MSG-14 | Icônes | toutes présentes dans le sous-ensemble embarqué | ✔ Conforme — aucune icône ajoutée |
| MSG-15 | Chargement du script de page | le test d'envoi s'exécute via le script chargé en 200 | ✔ Conforme — **INC-015 corrigé**, le script était demandé en 404 |
| MSG-16 | Lisibilité sans JavaScript | les réglages SMTP sont saisissables même si le script ne s'exécute pas | ✔ Conforme — **INC-017**, aucun masquage appliqué par le script |

#### 10.15.2 Transport SMTP (`Core\SmtpClient`)

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| TRS-01 | Dialogue complet | une réponse par commande, point final unique | ✔ Conforme — `220/250/250/250/354/250/221`, un seul `.` |
| TRS-02 | Texte en clair | envoi accepté sans chiffrement | ✔ Conforme |
| TRS-03 | STARTTLS | `EHLO`, `STARTTLS`, **nouvel `EHLO`**, puis la transaction | ✔ Conforme — RFC 3207 |
| TRS-04 | TLS direct (465) | connexion chiffrée dès l'accueil | ✔ Conforme |
| TRS-05 | Authentification | `AUTH PLAIN` engagée quand le serveur l'annonce | ✔ Conforme |
| TRS-06 | Encodage du message | corps en base64, accents préservés | ✔ Conforme — aller-retour `éèàù` intact |
| TRS-07 | Mot de passe absent des traces | aucune trace du secret | ✔ Conforme |
| TRS-08 | Mode de chiffrement inconnu | rejeté, jamais de repli en clair | ✔ Conforme — `tsl` et `STARTTLS` refusés |
| TRS-09 | Certificat auto-signé | refusé, cause nommée | ✔ Conforme — `unknown ca` → « certificat non reconnu » |
| TRS-10 | Port fermé | message actionnable | ✔ Conforme — « connexion refusée… vérifiez l'hôte et le port » |
| TRS-11 | Hôte vide / port hors bornes | refus explicite à la construction | ✔ Conforme |

#### 10.15.3 Grille historique conservée

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| PAR-01 | Alertes actives | pas de message de suspension, bouton d'envoi actif | ✔ Conforme |
| PAR-02 | Alertes suspendues | bascule décochée et libellée « Alertes suspendues », message d'alerte affiché, bouton d'envoi désactivé | ✔ Conforme |
| PAR-03 | Aperçu rempli | tableau unique, une ligne par véhicule | ✔ Conforme — 5 lignes |
| PAR-04 | Tri de l'aperçu | urgence croissante, le véhicule le plus proche en tête | ✔ Conforme — J-18, J-64, J-131, J-195, J-267 malgré l'ordre source inverse |
| PAR-05 | Aucune échéance | état vide, aucun tableau, aucun badge rouge | ✔ Conforme |
| PAR-06 | Coloration du badge « reste » | 4 seuils : rouge ≤ 30 j, orange ≤ 90 j, jaune ≤ 180 j, bleu au-delà | ✔ Conforme |
| PAR-07 | Paliers | 3 seuils sur `col-4` dans la grille, unité « j » accolée | ✔ Conforme |
| PAR-14 | Largeur de la grille | 12 colonnes occupées, gouttière `row g-3` | ✔ Conforme — 6 + 3 + 3 |
| PAR-15 | Répartition | Paramétrage deux fois plus large que chacun des deux autres | ✔ Conforme — `col-lg-6` contre deux `col-lg-3` |
| PAR-16 | Hauteur des deux cartes de droite | strictement identique | ✔ Conforme — `h-100` sur des cartes filles directes des colonnes |
| PAR-17 | Soumission des six paramètres | les six clés parviennent au contrôleur en un seul envoi | ✔ Conforme — sérialisation vérifiée : `alerte_active`, les 3 paliers, les 2 emails, plus le jeton |
| PAR-18 | Aucun risque d'effacement | aucune clé hors du formulaire d'enregistrement | ✔ Conforme |
| PAR-19 | Formulaires | pas d'imbrication | ✔ Conforme — 2 ouverts, 2 fermés |
| PAR-20 | Classes natives | aucune classe de grille maison introduite | ✔ Conforme — 54 classes Tabler/Bootstrap |
| PAR-08 | Icônes de la page | 8 icônes, toutes présentes dans le sous-ensemble embarqué | ✔ Conforme — plus aucun SVG inline |
| PAR-09 | Bouton « copier » | champ cron copié quand l'API Clipboard est disponible | ✔ Conforme — toast « Commande copiée », icône `fa-check` |
| PAR-10 | Repli sans API Clipboard | champ sélectionné pour copie manuelle + toast explicatif | ✔ Conforme — icône `fa-triangle-exclamation` |
| PAR-11 | Pile de toasts | icônes Font Awesome, plus aucun SVG construit en JS | ✔ Conforme — `fa-check`, `fa-xmark`, `fa-triangle-exclamation`, `fa-circle-info` |
| PAR-12 | Alertes natives | aucun `alert()` / `confirm()` / `prompt()` | ✔ Conforme |
| PAR-13 | Non-régression | 70/70 fichiers PHP valides, strict_types, 0 CJK | ✔ Conforme |

### 10.16 Module « Échéances »

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| ECH-01 | Route `/echeances` | enregistrée, résolue vers `EcheanceController::index` | ✔ Conforme — 24 routes GET, entrée en position 12 |
| ECH-02 | Architecture VMVC | contrôleur `final`, héritant de `Core\Controller`, vue sous `views/echeances/` | ✔ Conforme |
| CH-03 | Profil d'accès | `guard()` seul, donc rôle `lecture` | ✔ Conforme — cohérent avec `/api/dashboard/echeancier` |
| ECH-04 | Onglet de navigation | « Échéances » entre « Incidents » et « Paramètres » | ✔ Conforme — `dashboard > vehicules > entretien > incidents > echeances > parametres > utilisateurs` |
| ECH-05 | Icône de l'onglet | `fa-calendar-days`, présente dans le sous-ensemble embarqué | ✔ Conforme |
| ECH-06 | Item actif | liseré actif sur `/echeances` | ✔ Conforme |
| ECH-07 | Item actif en filtrage | l'onglet reste actif sur `/echeances?palier=90` | ✔ Conforme — la chaîne de requête est ignorée par `currentPath()` |
| ECH-08 | Barre de navigation | 14 routes, aucune régression | ✔ Conforme — l'onglet s'insère sans casser la détection existante |
| ECH-09 | Tri | urgence croissante, plus urgent en tête | ✔ Conforme — J-18, J-82, J-195, J-208 depuis une source non triée |
| ECH-10 | Aplatissement | groupes vides écartés, libellés de palier conservés | ✔ Conforme |
| ECH-11 | Effectif par palier | correspondant au nombre de véhicules filtrés | ✔ Conforme |
| ECH-12 | Compteurs | sous 30 j et sous 90 j exacts | ✔ Conforme |
| ECH-13 | Cartes KPI | 4 en page pleine, aucune en page vide | ✔ Conforme — 4 / 1 / 2 / J-18 |
| ECH-14 | Page vide | état vide, aucun tableau, aucun KPI | ✔ Conforme |
| ECH-15 | Filtre sans résultat | message « Aucun véhicule dans ce palier » | ✔ Conforme |
| ECH-16 | Filtre non numérique | `?palier=abc` retombe sur la vue complète | ✔ Conforme — `Request::int` renvoie 0 |
| ECH-17 | Page Paramètres | aperçu retiré, plus aucune trace de la logique déplacée | ✔ Conforme — 230 → 148 lignes, 0 `<table>`, 0 `apercu` |
| ECH-18 | Icônes de la page | toutes dans le sous-ensemble embarqué | ✔ Conforme |
| ECH-19 | Alertes natives | aucun `alert()` / `confirm()` / `prompt()` | ✔ Conforme |
| ECH-20 | Non-régression | 72/72 fichiers PHP valides, strict_types, 0 CJK | ✔ Conforme |

### 10.17 Cartes de profil et avatar de la page Utilisateurs

#### 10.17.1 Passage de la liste tabulaire à la grille de cartes

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| UTI-01 | Structure de la grille | `row row-cards`, colonnes `col-md-6 col-lg-4` | ✔ Conforme |
| UTI-02 | Rendu par largeur de fenêtre | 3 cartes par ligne ≥ 1200 px, 2 à partir de 768 px, 1 en dessous | ✔ Conforme — 4 comptes : 3 + 1 à 1920 et 1440 px, 2 + 2 à 768 px, 1 + 1 + 1 + 1 à 500 px |
| UTI-03 | Bandeau de teinte | `card-status-top` porté par la carte, teinte fonction du rôle | ✔ Conforme — 3 teintes distinctes sur les 3 rôles |
| UTI-04 | Avatar | `avatar-xl avatar-rounded`, 64 px de côté, image servie par `Core\Url::upload()` | ✔ Conforme |
| UTI-05 | Initiales de repli | deux lettres majuscules, source unique `Models\User::initiales()` | ✔ Conforme — la vue ne recalcule plus rien |
| UTI-06 | Actions disponibles | « Éditer » et « Supprimer » seulement | ✔ Conforme — boutons « Email » et « Call » de la maquette retirés |
| UTI-07 | Suppression du compte connecté | absente de sa propre carte | ✔ Conforme |
| UTI-08 | Icônes | Font Awesome via `Core\Icon`, aucun SVG inline | ✔ Conforme — **0 `<svg>`**, **0 script inline**, logique déplacée dans `utilisateurs.js` |
| UTI-09 | Suppression | modale Tabler, jamais `confirm()` | ✔ Conforme — attribut `data-confirmer` |

#### 10.17.2 Avatar : dépôt, validation et exposition

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| UTI-10 | Dépôt d'un avatar | accepté à la création et à l'édition | ✔ Conforme — le formulaire porte `enctype="multipart/form-data"` |
| UTI-11 | Formats admis | JPEG, PNG et WEBP seulement | ✔ Conforme — `MIMES_AVATAR` réduit à ces trois types |
| UTI-12 | PDF déposé comme avatar | refusé | ✔ Conforme — le MIME constaté par `finfo` décide, jamais l'extension annoncée |
| UTI-13 | Liste MIME vide | dépôt refusé, message explicite | ✔ Conforme |
| UTI-14 | Le sous-ensemble ne peut pas élargir la configuration | `Core\Upload::store()` intersecte avec `securite.mime_autorises` | ✔ Conforme — **INC-028 corrigé** |
| UTI-15 | Pièces jointes d'incident | le PDF reste accepté pour un incident | ✔ Conforme — la contrainte ne vise que l'avatar |
| UTI-16 | Chemin de stockage | `public/uploads/avatars/`, nom aléatoire, extension déduite du MIME | ✔ Conforme — aucun nom d'utilisateur dans le chemin |
| UTI-17 | Non-exécution du dossier | `.htaccess` interdisant l'interprétation de script | ✔ Conforme |
| UTI-18 | Mot de passe SMTP | jamais renvoyé dans le HTML | ✔ Conforme — sans rapport avec l'avatar, contrôlé par TRS-10 |

#### 10.17.3 Édition : remise à zéro et conservation de l'avatar

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| UTI-19 | Ouverture de la modale d'édition | formulaire remis à zéro puis prérempli | ✔ Conforme — **INC-027 corrigé** ; discriminant `data-edition-utilisateur` |
| UTI-20 | Modification d'un profil sans toucher à l'avatar | l'avatar est conservé | ✔ Conforme — **INC-029 corrigé** ; `User::update()` n'écrit pas la colonne, `Database::update()` n'écrivant que les clés transmises |
| UTI-21 | Suppression explicite de l'avatar | case `avatar_effacer`, avatar retiré | ✔ Conforme |
| UTI-22 | Nouveau fichier **et** case de suppression cochées | le fichier déposé l'emporte | ✔ Conforme — `appliquerAvatar()` teste le fichier en premier |
| UTI-23 | Service des avatars | `Core\Url::upload()` ne sert que `uploads/avatars/` | ✔ Conforme — les pièces jointes d'incident restent privées |
| UTI-24 | Non-régression | 78/78 fichiers PHP valides, `strict_types`, 0 CJK | ✔ Conforme — `config/database.php` exclu, mode 600 hors lecture pour le compte de recette |

#### 10.17.4 Fidélité du rendu des registres

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | :--- | :--- |
| MD-01 | Cellule contenant une barre verticale échappée | la barre reste littérale, la ligne garde ses colonnes | ✔ Conforme — **INC-020 corrigé** |
| MD-02 | Cellule contenant une barre verticale non échappée | rien n'est perdu : le surplus est rattaché à la dernière colonne | ✔ Conforme — **INC-031 corrigé** ; plus de cellule écartée en silence |
| MD-03 | Ligne dont la colonne de marqueur a disparu | le marqueur `⟨ À valider ⟩` ou `✔ Conforme` est présent sur les dix lignes `REG` | ✔ Conforme — retrouvé et vérifié sur `REG-01` à `REG-10` |

### 10.10 Connexion après correction de `Logger` (INC-006)

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | --- | --- |
| CON-01 | Login avec identifiants invalides | redirection 302 et message d'erreur, **plus aucune HTTP 500** | ✔ Conforme |
| CON-02 | Absence d'exception | 0 `Class "Controllers\Core\Logger" not found` dans le journal | ✔ Conforme |
| CON-03 | Imports `Logger` | les 8 contrôleurs qui journalisent possèdent `use Core\Logger;` | ✔ Conforme |
| CON-04 | Toutes les branches de journalisation | `IncidentController`, `MaintenanceController`, `UserController`, `VehicleController` tracent sans `Class not found` | ✔ Conforme |

### 10.8 Nommage du champ « nom d'utilisateur »

| # | Cas de test | Attendu | Résultat |
| :--- | :--- | --- | --- |
| INS-55 | Attributs du champ | `id="nom_d_utilisateur"` et `name="nom_d_utilisateur"`, libellé associé par `for`, `placeholder="nom_d_utilisateur"`, `required` | ✔ Conforme |
| INS-56 | Lecture côté serveur | `Request::input('nom_d_utilisateur', '')` alimente la clé `nom` transmise à `Services\Installer` | ✔ Conforme |
| INS-57 | Réaffichage après re-rendu serveur | la valeur saisie est restituée dans le champ | ✔ Conforme — `Shogun` |
| INS-58 | Ancienne clé `admin_username` | ignorée : l'ancienne clé ne produit aucun effet, le nom est traité comme absent | ✔ Conforme — « Le nom de l'administrateur est obligatoire » |
| INS-59 | Colonne SQL visée | `INSERT INTO utilisateurs (nom, …)` ; aucune colonne `username` ni `login` dans le schéma | ✔ Conforme — `sql/schema.sql` inspecté |
| INS-60 | Cohérence du focus JavaScript | `install.js` cible `#nom_d_utilisateur` à l'arrivée sur l'étape 2, sans `ReferenceError` | ✔ Conforme |
| INS-61 | Absence de régression | 0 `warning` / `notice` / `fatal` / `deprecated`, aucune référence résiduelle à `admin_username` dans le code | ✔ Conforme |

### 10.4 Dépendance à une base réelle

> **Blocage connu.** Aucun identifiant administrateur MySQL n'est disponible sur
> cette machine (`mysql -u root` renvoie `ERROR 1045 (28000): Access denied`,
> et `/etc/mysql/debian.cnf` n'est pas lisible par l'utilisateur courant). Les cas
> `INS-09`, `INS-12` et `INS-16`, ainsi que l'application effective du schéma et la
> création réelle du compte administrateur, restent à exécuter avec des
> identifiants valides. `INS-10`, `INS-13`, `INS-14` et `INS-15` couvrent déjà la
> branche d'échec et valident la totalité de la chaîne de traitement.

---

## Anomalies ouvertes

| Référence | Description | Gravité | Statut |
| :--- | :--- | :--- | :--- |
| — | Aucune anomalie bloquante identifiée à la livraison. | — | — |

## Anomalies résolues

| Référence | Description | Gravité | Résolu le | Correctif |
| :--- | :--- | :---: | :--- | :--- |
| INC-001 | 404 sur Tabler.io CSS/JS, feuille de style applicative et `app.json` depuis la mire de connexion. Préfixe d'URL codé en dur (`/flotteo`) alors que le vhost sert l'application à la racine du domaine. | Bloquante | 2026-10-02 | Auto-détection du préfixe via `Core\Url`, URLs normalisées dans toutes les vues, manifeste `app.json` créé, court-circuit statique en `.htaccess` |
| INC-002 | Liens du pied de page vers `/docs/*.html` en 404 : le dossier `docs/` est hors de la racine servie par Apache. | Mineure | 2026-10-02 | Route `/docs/{doc}` servie par `DocsController` en liste blanche stricte, authentification requise |
| INC-003 | 404 implicite sur `favicon.ico` et titre de page figé sur « Tableau de bord » quel que soit le module. | Mineure | 2026-10-02 | Icône SVG inline (`flotteo.svg`) et libellé de page dérivé de la route active |
| INC-006 | Toute tentative de connexion renvoyait HTTP 500 : `Class "Controllers\Core\Logger" not found`. 7 contrôleurs sur 11 appelaient `Logger::` sans `use Core\Logger`, et `AuthController` préfixait le nom, qui résolvait alors dans le namespace `Controllers`. | Bloquante | 2026-10-03 | Import `use Core\Logger;` ajouté aux 8 contrôleurs concernés ; référence `Core\Logger::` normalisée en `Logger::`. Échec de connexion désormais géré proprement (302 + message) |
| INC-005 | Message d'erreur de connexion affiché en double à l'étape 1 : dans l'alerte rouge, puis dans un bloc gris. `afficherRetour()` injectait deux fois le même texte — une fois dans `<div class="small">`, une fois dans un `detail` qui en était la copie. | Mineure | 2026-10-02 | `afficherRetour(etat, message, detail)` : le bloc gris n'est rendu que si `detail` est non vide et différent du message. Quatre variantes rejouées sous jsdom, 1 occurrence dans chaque cas |
| INC-004 | Étape 2 de l'assistant d'installation affichée vide : aucun champ de saisie visible, procédure bloquée. Le `d-none` initial était posé sur le `<form id="formulaire-admin">` tandis que `install.js` basculait `d-none` sur la carte `#etape-2` — le nœud masqué et le nœud piloté n'étaient pas le même. | Bloquante | 2026-10-02 | `d-none` déplacé sur la carte `#etape-2` ; bascule rejouée sur le DOM servi sous jsdom, champs administrateur vérifiés présents |
| INC-010 | Item « Paramètres » inactif dans la barre supérieure sur `/admin/parametres`. | Mineure | 2026-10-03 | Détection d'item actif par comparaison de chemin complet, plus longue correspondance gagnante |
| INC-011 | Écran « Référentiels » inatteignable : `/admin/dictionnaires` n'était référencé par aucun lien, la barre ne contenant qu'une correspondance de titre. Le CRUD était complet mais accessible uniquement par saisie d'URL, alors que `features.md` le présentait comme un module de la barre. | Mineure | 2026-10-03 | Entrée « Référentiels » ajoutée à la barre (niveau `administration`) ; liseré actif vérifié sur le chemin et ses sous-chemins |
| INC-012 | Icônes non conformes au §3 des règles de développement : 3 SVG inline subsistaient dans `admin/dictionnaire.php` (ajouter, modifier, supprimer) après la conversion des autres écrans. | Mineure | 2026-10-03 | Remplacés par `fa-plus`, `fa-pen`, `fa-trash-can` via `Core\Icon` ; `aria-label` ajoutés sur les boutons d'action |
| INC-013 | `addslashes()` utilisé pour injecter le titre de la modale dans un bloc `<script>` — inadapté à un contexte JavaScript, et incohérent avec le `json_encode()` employé dix lignes plus loin. La sécurité reposait sur l'échappement implicite du `/` par `json_encode`. | Mineure | 2026-10-03 | `json_encode()` avec `JSON_HEX_TAG \| JSON_HEX_APOS \| JSON_HEX_AMP \| JSON_HEX_QUOT` ; modale également protégée contre un affichage sans déclencheur |
| INC-014 | `docs/user_guide.md` et `docs/user_guide.html` absents alors que le §5 des règles les exige, que le §3 impose un lien de pied de page vers le guide, et que le §6.4 en fait une étape du protocole de clôture — la clôture était donc impossible à franchir. | Bloquante | 2026-10-03 | Guide rédigé écran par écran, déclaré dans `scripts/build_docs.php` et dans la liste blanche de `DocsController`, lien ajouté en tête du pied de page |
| INC-015 | Le bloc de réglages SMTP ne se déployait jamais sur la page Paramètres : le formulaire était présent dans le HTML mais restait masqué. `ParamController` déclarait `useScript('js/parametres.js')` alors que le pied de page applique déjà le préfixe (`Url::asset('js/' . $script)`) : la page demandait `/assets/js/js/parametres.js` et obtenait un **404**. Le basculement du mode d'envoi n'était donc jamais câblé. | Bloquante | 2026-10-03 | Nom transmis corrigé en `parametres.js`, soit `/assets/js/parametres.js` — servi en 200 |
| INC-017 | Le formulaire SMTP restait invisible après sélection du mode « Serveur SMTP » : correction de l'URL du script (INC-015) sans effet en usage réel. La visibilité dépendait entièrement de JavaScript, si bien que toute situation où le script ne s'exécute pas — version en cache dans le navigateur, extension bloquant les ressources, proxy d'entreprise, 404 sur un déploiement incomplet — laisse la page sans aucun réglage SMTP, sans message et sans échappatoire. | Bloquante | 2026-10-03 | Masquage supprimé : `#bloc-smtp` est toujours rendu visible et l'attribut `hidden` retiré de la vue ; `basculerBlocSmtp()` et l'attribut `data-bloc-smtp` retirés de `parametres.js`, qui ne pilote plus que le test d'envoi. Vérifié sur le HTML complet servi avec `mail_transport = mail` : bloc et 5 champs présents |
| INC-016 | `scripts/build_docs.php` bouclait sans fin sur toute ligne de tableau mal formée. Une ligne commençant par `\|` qui n'est suivie d'aucune ligne de séparateurs n'est pas reconnue comme un tableau ; elle atteignait la branche « paragraphe », dont la boucle interne s'arrêtait immédiatement sur cette même ligne. L'index n'avançait jamais et `<p></p>` était empilé indéfiniment. Symptôme observé : **épuisement de la mémoire à 3 Go**, `qa_recette.html` restant périmé sans message d'erreur. | Bloquante | 2026-10-03 | Garde-fou d'avancement dans la branche paragraphe : si la boucle interne n'a rien consommé, la ligne est ajoutée et l'index incrémenté. Vérifié sur un document volontairement cassé — génération complète, code de sortie 0 |
| INC-018 | Le bouton « Éditer » d'une ligne de référentiel était inerte : aucun effet au clic, sans erreur console. Il portait `data-edition-dictionnaire` mais **ni `data-bs-toggle="modal"` ni `data-bs-target`**, alors que le seul mécanisme de déclenchement retenu est l'API déclarative de Bootstrap. Aucun `show.bs.modal` n'était donc émis et l'écouteur de préremplissage ne s'exécutait pas. | Bloquante | 2026-10-03 | `data-bs-toggle="modal"` et `data-bs-target="#modal-dictionnaire"` ajoutés au bouton, qui rejoint le mécanisme du bouton « Ajouter ». Le discriminant `hasAttribute('data-bs-target')` devenait alors faux pour les deux boutons, qui partagent le déclencheur : remplacé par `hasAttribute('data-edition-dictionnaire')`. Préremplissage rejoué sous jsdom sur 4 cas — 2 éditions correctes, ajout et affichage programmatique en mode création |
| INC-019 | Le liseré d'onglet n'apparaissait sur aucune des six tables de la barre « Référentiels ». La barre portait `nav nav-borders` : **`nav-borders` n'existe pas dans la feuille Tabler livrée**, aucune règle ne s'y applique. La classe `active` était bien générée sur le `nav-link` du type courant — elle était sans effet, faute de règle applicable. | Moyenne | 2026-10-03 | Barre basculée sur `nav nav-underline`, seule variante Tabler produisant un liseré inférieur (`.nav-underline .nav-link.active { border-bottom-color: currentcolor }`, `0.125rem`). Classe `active` posée à la fois sur `nav-item` et `nav-link`, avec `aria-current="page"`, conformément à la convention de la barre supérieure. Vérifié au rendu complet : 6 onglets, exactement 1 actif sur les deux éléments |
| INC-020 | `scripts/build_docs.php` tronquait silencieusement les lignes de tableau contenant une barre verticale échappée. La découpe des cellules ignorait l'échappement Markdown : toute cellule citant une expression régulière qui écrit une barre verticale échappée était coupée en deux colonnes. Deux lignes du registre des incidents étaient corrompues dans `qa_recette.html` — `INC-013` perdait la fin de sa description, **`INC-016` perdait sa colonne « Correctif » entière** — sans aucun message d'erreur, la génération se déroulant jusqu'au bout. | Moyenne | 2026-10-03 | Découpe remplacée par un découpage insensible à la barre verticale échappée, suivi de la substitution de `\|` par `|` dans chaque cellule : une barre verticale échappée ne délimite plus de colonne et redevient littéral. Les 36 lignes du registre des incidents vérifiées à 5 colonnes après régénération |
| INC-021 | Les quatre registres documentaires s'ouvraient **hors de l'application**. `DocsController::show()` faisait `readfile()` sur le fichier compilé : le visiteur atterrissait sur un document autonome, avec sa propre barre verticale et son propre pied de page. Les quatre liens du pied de page de Flotteo sortaient donc de l'application pour proposer un retour manuel, et la barre supérieure, l'en-tête et le fil de navigation disparaissaient. Le garde-fou reposait en outre sur une liste de noms de fichiers, donc sur un chemin de système de fichiers. | Bloquante | 2026-10-03 | `readfile()` et `AUTORISES` supprimés. `DocsController` rend `views/docs/index.php` dans le layout général : navigation `col-lg-3`, document `col-lg-9`, deux `.card`. Le garde-fou devient un contrôle de clé par `Models\Registre`. Vérifié sur les 4 pages : un seul `<!doctype>`, un seul `<html>`, barre supérieure et pied de page présents |
| INC-022 | Les quatre pages documentaires portaient des **emoji** (📖 📋 🗂️ ✅) en guise d'icônes, dans leur barre et leur titre, en violation du §3 des règles qui impose Font Awesome pour l'ensemble des modules. | Mineure | 2026-10-03 | Remplacés par `fa-book`, `fa-file-lines`, `fa-list` et `fa-check` via `Core\Icon` — quatre glyphes déjà présents dans le sous-ensemble embarqué, aucun ajout de police nécessaire |
| INC-023 | La classe `markdown` portée par le conteneur du document **n'existait pas** dans `public/assets/css/flotteo.css`. Elle était déjà inerte dans les vues autonomes : titres, listes, codes, citations et tableaux s'affichaient avec les styles par défaut du navigateur, un `<h2>` restant à la taille du texte courant, et un tableau large ou un bloc de code long débordant de la carte. | Moyenne | 2026-10-03 | Règles `.markdown` ajoutées à la feuille applicative, à partir des variables `--tblr-*` : échelle des titres, interlignes des listes, fond et débordement du code, filet des citations, en-têtes de tableau bornés |
| INC-024 | La description des quatre registres existait à deux endroits sans possibility de contrôle croisé : `REGLES` dans `scripts/build_docs.php` et `AUTORISES` dans `DocsController`. Ni titres ni icônes n'y étaient déclarés, et le moteur Markdown lui-même vivait dans le script, **inaccessible à l'application** — impossible d'y rendre un registre sans dupliquer le moteur, donc sans garantir deux implémentations divergentes. | Moyenne | 2026-10-03 | Description unique dans `Models\Registre::DOCUMENTS` (fichier, libellé, titre, glyphe, résumé), lue par le contrôleur et le script. Moteur extrait dans `Core\Markdown`, partagé par les deux. Identité du rendu vérifiée octet par octet sur le corps des 4 registres avant et après extraction |
| INC-025 | La barre supérieure provoquait un **défilement horizontal de toute la page** entre 992 et 1200 px. Mesuré sous Chrome headless, la page débordait de 101 px à 1024 px. Cause : `navbar-expand-lg` maintenait une barre horizontale à une ligne — la rangée interne porte `flex-nowrap` — alors que les sept modules, l'identité et le menu utilisateur exigent environ 1130 px de contenu. Les entrées de menu s'empilaient hors de la fenêtre et faisaient déborder le document entier. Le passage du corps de page en pleine largeur a aggravé le défaut (117 px à 1024 px) en réduisant l'espace disponible, sans en traiter la cause. | Bloquante | 2026-10-03 | Barre passée en `navbar-expand-xl`, qui se replie sous 1200 px ; `flotteo.css` aligné sur `@media (max-width: 1199.98px)`. Le liseré d'onglet actif suit le point de rupture (`.navbar-expand-xl .nav-item.active:after`, filet de 2 px vérifié de 1200 à 1920 px) ; sous 1200 px, menu replié sans repère `:after` — comportement mesuré à l'identique avant ce changement sous 992 px, le module courant restant identifiable par son gras 600 contre 400. Vérifié sur 3 pages du layout complet × 6 largeurs : aucun débordement |
| INC-027 | Le bouton « Éditer » de la page Utilisateurs était inerte, comme celui des référentiels (INC-018) : il portait `data-edition-utilisateur` mais **ni `data-bs-toggle="modal"` ni `data-bs-target`**. Aucun `show.bs.modal` n'était émis, l'écouteur de remise à zéro du formulaire ne s'exécutait pas, le clic n'avait aucun effet et aucune erreur console. | Bloquante | 2026-10-03 | `data-bs-toggle="modal"` et `data-bs-target="#modal-utilisateur"` ajoutés. Le discriminant du listener est `data-edition-utilisateur` : `data-bs-target`, qui doit figurer sur les deux boutons depuis qu'ils déclenchent la même modale, ne distingue plus rien. |
| INC-028 | Le contrôle de type de `Core\Upload::store()` ne portait que sur la taille : n'importe quel fichier était accepté, y compris un script PHP ou un exécutable, pour tout dépôt simultané d'un fichier et d'une pièce jointe d'incident. | Bloquante | 2026-10-03 | La méthode n'accepte plus qu'un sous-ensemble de MIME, **intersecté avec `securite.mime_autorises`** : la liste ne peut donc jamais élargir la configuration générale. `User::MIMES_AVATAR` retient `image/jpeg`, `image/png` et `image/webp`. La décision porte sur le MIME constaté par `finfo`, jamais sur l'extension annoncée. |
| INC-029 | Modifier le nom ou l'email d'un utilisateur **effaçait son avatar**. `User::update()` transmettait `avatar` à `Database::update()`, qui écrit explicitement les `null` transmis ; comme le formulaire d'édition ne produit aucune donnée de fichier, la valeur effacée était réinjectée à chaque enregistrement. | Moyenne | 2026-10-03 | `User::update()` n'écrit plus la colonne. L'avatar est traité à part par `UserController::appliquerAvatar()`, qui donne la priorité au nouveau fichier sur la case de suppression, et n'écrit que lorsqu'une action est demandée. |
| INC-030 | Trois icônes SVG inline subsistaient dans la vue Utilisateurs (ajout, crayon, corbeille), en violation du §3 des règles qui impose Font Awesome, et la vue embarquait sa logique dans un `<script>` inline. | Mineure | 2026-10-03 | Remplacées par `fa-plus`, `fa-pen` et `fa-trash-can` via `Core\Icon`, `aria-label` ajoutés. Logique déplacée dans `public/assets/js/utilisateurs.js`, chargée par `useScript()`. Vérifié : 0 `<svg>`, 0 script inline. |
| INC-031 | La colonne « Résultat » disparaissait de **neuf lignes du registre de non-régression** (`REG-02` à `REG-10`) dans `qa_recette.html`. Deux défauts distincts se cumulaient. D'une part, l'en-tête du tableau ne comportait que quatre colonnes (`#`, cas de test, attendu, résultat) alors que neuf lignes en écrivaient cinq : la deuxième contenait une **action** (`saisir …`, `ouvrir …`, `désactiver JavaScript`). D'autre part, `Core\Markdown::rendreTableau()` n'alignait le corps que sur les colonnes de l'en-tête : toute cellule excédentaire était **écartée sans message**, ce qui rendait la perte invisible. Aucun avertissement, code de sortie 0. | Moyenne | 2026-10-03 | En-tête complété d'une colonne « Action », et `REG-01` aligné sur les cinq colonnes. Le moteur rattache désormais le surplus de cellules à la dernière colonne au lieu de le jeter : plus aucune donnée d'un registre ne peut disparaître en silence. Vérifié au rendu : `REG-01` à `REG-10` rendent 4 cellules alignées, marqueur `⟨ À valider ⟩` ou `✔ Conforme` présent sur les dix lignes |
| INC-026 | Le contenu des pages était plafonné et ne profitait pas de la largeur de l'écran. Le corps de page et le pied de page employaient `container-xl`, dont le `max-width` atteint 1320 px à partir de 1400 px de fenêtre : au-delà, la page restait centrée avec de larges marges vides. Le bandeau, lui, était en `container-fluid` avec son seul padding par défaut, d'où un désalignement entre l'identité de la barre et le bord des cartes. | Moyenne | 2026-10-03 | Corps et pied de page en `container-fluid px-4 px-lg-5`, même padding appliqué à la barre supérieure. Utilitaires natifs porteurs de `!important`, donc aucune règle CSS maison ; le plafond de `.layout-boxed .page` ne s'appliquait pas, la classe n'étant pas employée. Mesuré : 32 px de marge latérale au-dessus de 992 px, 24 px en dessous, identiques sur la barre et le corps |

## Historique des campagnes

| Campagne | Date | Version | Cas exécutés | Conformes | Échecs |
| :--- | :--- | :--- | :--- | :--- | :--- |
| Initiale | 2026-10-02 | 1.0.0 | 11/11 NF | 11 | 0 |
| Chemins d'actifs | 2026-10-02 | 1.0.0 | 20/20 AST + 3/3 NF | 23 | 0 |
| Assistant d'installation | 2026-10-02 | 1.0.1 | 18/21 INS | 18 | 0 |
| Miroire d'installation (ergonomie) | 2026-10-02 | 1.0.1 | 10/12 INS (22-32) | 10 | 0 |
| Étape 2 de l'assistant (INC-004) | 2026-10-02 | 1.0.1 | 13/13 INS (33-45) | 13 | 0 |
| Doublon visuel de l'erreur (INC-005) | 2026-10-02 | 1.0.1 | 9/9 INS (46-54) | 9 | 0 |
| Nommage du champ utilisateur | 2026-10-02 | 1.0.1 | 7/7 INS (55-61) | 7 | 0 |
| Bandeau supérieur & Font Awesome | 2026-10-03 | 1.0.1 | 16/16 NAV + FA | 16 | 0 |
| Connexion / imports Logger (INC-006) | 2026-10-03 | 1.0.1 | 4/4 CON | 4 | 0 |
| Jeu de données de démonstration | 2026-10-03 | 1.0.1 | 18/18 DEM | 18 | 0 |
| Verrou d'installation (INC-007) + Font Awesome (INC-008) | 2026-10-03 | 1.0.1 | 16/16 VER-FA | 16 | 0 |
| Injection réelle en base (INC-009) | 2026-10-03 | 1.0.1 | 13/13 IMP | 13 | 0 |
| Item actif de la barre supérieure (INC-010) | 2026-10-03 | 1.1.1 | 12/12 NAV | 12 | 0 |
| Refonte de « Paramètres système » | 2026-10-03 | 1.1.1 | 20/20 PAR | 20 | 0 |
| Navigation par sections + Messagerie (SMTP) | 2026-10-03 | 1.1.1 | 14/14 MSG + 11/11 TRS | 25 | 0 |
| Référentiels, icônes, guide utilisateur | 2026-10-03 | 1.1.1 | 10/10 REF + 3/3 GUI | 13 | 0 |
| Module « Échéances » | 2026-10-03 | 1.1.1 | 19/19 ECH | 19 | 0 |
| Registres documentaires intégrés au layout (INC-021 à INC-024) | 2026-10-03 | 1.1.2 | 14/14 GUI | 14 | 0 |
| Layout en pleine largeur (INC-025, INC-026) | 2026-10-03 | 1.1.3 | 7/7 LAY + 2/2 NAV | 9 | 0 |
| Cartes de profil et avatar (INC-027 à INC-030) | 2026-10-03 | 1.2.0 | 24/24 UTI | 24 | 0 |
| Colonne perdue au rendu des registres (INC-031) | 2026-10-03 | 1.2.0 | 10/10 REG + 2/2 MD | 12 | 0 |
