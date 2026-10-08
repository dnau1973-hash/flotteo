# Flotteo — Gestion de parc automobile

Application web interne de gestion, pilotage opérationnel, analyse financière et
traçabilité du cycle de vie d'un parc de véhicules de location.

Spécifications de référence : [`cahier_des_charges_flotteo.md`](cahier_des_charges_flotteo.md) v1.1.

---

## 1. Pile technique

| Couche | Choix |
| :--- | :--- |
| Serveur | Apache 2.4 avec `mod_rewrite` |
| Backend | PHP 8.1+ natif, architecture VMVC stricte, sans framework |
| Base de données | MySQL / MariaDB via PDO |
| Frontend | HTML5, CSS3, JavaScript Vanilla, Tabler.io, ApexCharts |

Assets Tabler.io et ApexCharts sont servis **localement** depuis
`public/assets/tabler/` : aucune requête CDN à l'exécution.

## 2. Installation

L'assistant web (`/install`) et le script `scripts/install.php` exécutent la même
logique et produisent le même résultat. Choisissez l'un ou l'autre.

```bash
# 1. Renseigner les identifiants de base de données
nano config/database.php

# 2. Créer la base, appliquer le schéma et générer le compte administrateur
FLOTTEO_ADMIN_EMAIL='admin@votre-societe.fr' \
FLOTTEO_ADMIN_PASSWORD='MotDePasseSolide2026!' \
php scripts/install.php

# 3. Activer la configuration Apache
sudo cp docs/apache/flotteo.conf /etc/apache2/sites-available/flotteo.conf
sudo a2enmod rewrite headers
sudo a2ensite flotteo
sudo systemctl reload apache2
```

L'application est ensuite accessible sur `http://<hôte>/flotteo/login`.

### Recherche de mise à jour (facultatif)

L'étape 2 de l'assistant web propose un champ **facultatif** « Jeton personnel
GitHub », et le script lit la variable `FLOTTEO_GITHUB_TOKEN` :

```bash
FLOTTEO_GITHUB_TOKEN='ghp_…' php scripts/install.php
```

Renseignée, elle est écrite dans `config/secrets.php` (mode `0640`, voir
[Jeton personnel](#jeton-personnel)). Omise — c'est le cas par défaut — **aucun
fichier n'est créé** et la recherche de mise à jour reste fonctionnelle en accès
anonyme : 60 requêtes/heure au lieu de 5 000, dans la limite des dépôts publics.

### Jeu de démonstration (optionnel)

```bash
mysql -u <user> -p flotteo < sql/demodata.sql
```

### Schéma de base de données

`sql/schema.sql` est l'unique script de structure : il crée en une passe
**toutes** les tables et colonnes ainsi que les paramètres par défaut. Aucune
migration `ALTER TABLE` n'est à appliquer après l'installation, et l'application
ne modifie jamais la structure de la base à l'exécution.

Pour une base créée avec une version antérieure, sauvegardez les données puis
réinstallez sur une base vide (assistant web ou `scripts/install.php`).

## 3. Arborescence

```
flotteo/
├── bin/              Scripts CLI (sauvegarde planifiée)
├── config/          Configuration (base de données, constantes applicatives, secrets)
├── core/            Noyau : Database, Router, Controller, Auth, Csrf, View, Upload, Mailer, SmtpClient
├── controllers/     Contrôleurs d'action, un par module
├── models/          Accès aux données et règles métier
├── services/        Logique transverse (AlertService, BackupService, SambaClient, GithubClient)
├── views/           Templates PHP : layout, auth, dashboard, vehicles, …
├── public/          Racine servie par Apache (front controller + assets + uploads)
├── sql/             Schéma de base et jeu de démonstration
├── scripts/         Installation, génération des registres, tâche cron
├── storage/backups/ Archives de sauvegarde (hors zone servie)
├── storage/logs/    Journaux techniques (jamais exposés en HTTP)
└── docs/            Registres synchronisés .md / .html + configuration Apache
```

## 4. Tâche cron — alertes de fin de contrat

```cron
5 7 * * 1 /usr/bin/php /var/www/flotteo/scripts/alert_cron.php
```

Le destinataire, les paliers d'anticipation (3 / 6 / 9 mois par défaut) et
l'activation des alertes se pilotent depuis
**Administration → Paramètres système**.

## 5. Tâche cron — sauvegarde

```cron
17 2 * * * /usr/bin/php /var/www/flotteo/bin/backup.php
```

Le script appelle le même `Services\BackupService` que le bouton de lancement
manuel : il produit une archive `ZIP` du schéma, des données et de
`public/uploads/`, applique la purge de rétention, puis la dépose sur le
partage Samba si l'externalisation est activée.

Les réglages (rétention, dossier d'archivage, partage Samba, authentification)
se pilotent depuis **Administration → Paramètres système → Sauvegardes**.

## 6. Recherche de mise à jour

**Administration → Paramètres système → Mises à jour** : renseignez le dépôt
GitHub (`compte/depot`), puis « Rechercher les mises à jour ». Le contrôle est
déclenché à la demande et n'interroge l'API qu'à ce moment — l'affichage relit le
dernier relevé mémorisé, sans appel réseau.

Sans authentification, l'API GitHub limite l'appel à **60 requêtes par heure et
par adresse IP**, ce qui suffit pour un usage occasionnel. Un jeton personnel
relève ce plafond à 5 000.

### Dépôt sans version publiée

Beaucoup de dépôts versionnent par **étiquettes** sans jamais créer de *release*.
L'API `releases/latest` répond alors 404, aussi bien que pour un dépôt inexistant.
Flotteo distingue les deux cas et, quand le dépôt existe sans version publiée,
retient son **étiquette de version la plus haute**. L'écran l'indique alors
explicitement : le numéro affiché provient d'une étiquette, sans date de
publication associée.

Deux règles encadrent ce repli :

* les **préversions** (`v2.0-rc1`, `v1.0-beta`) sont écartées, comme le fait
  l'API des versions publiées — annoncer une version candidate comme mise à jour
  conduirait à installer du code non stabilisé ;
* le **plus haut numéro** est retenu, et non la première étiquette renvoyée :
  GitHub les ordonne par date de création du tag, si bien qu'un correctif
  retroporté (`v1.9.3` publié après `v2.1.0`) remonterait sinon en tête.

### Jeton personnel

Le jeton se place dans `config/secrets.php`, clé `github_token` — **pas** dans la
table `parametres` : le module de sauvegarde exporte toute la base dans une
archive téléchargeable, et un `ghp_…` y donnerait accès à vos dépôts.

```bash
sudo nano config/secrets.php          # renseigner github_token
sudo chown www-data:www-data config/secrets.php
sudo chmod 640 config/secrets.php
```

Le groupe doit être **`www-data`** : Flotteo est servi par Apache sous ce compte,
et un fichier en `600` owned par un compte de session lui serait **illisible** —
le jeton serait alors ignoré silencieusement, sans erreur à l'écran. `640` laisse
le fichier lisible par le serveur web et inaccessible aux autres comptes.

Le fichier est hors racine servie (`public/.htaccess` répond 403 sur `config/`) et
exclu du dépôt par `.gitignore` et par `.aiexclude.md`. La variable
d'environnement `FLOTTEO_GITHUB_TOKEN` prime sur lui si votre outillage de
déploiement injecte le secret autrement.

L'assistant d'installation écrit ce fichier pour vous si vous renseignez le champ
« Jeton personnel GitHub » de l'étape 2, avec le bon mode. Fait à la main, sans
le `chown`, le fichier appartient au compte qui l'a créé : d'où la commande.

Périmètre recommandé : lecture seule sur le dépôt des versions, et rien d'autre.
Flotteo n'exige aucune écriture sur GitHub — il consulte, il n'installe pas.

## 7. Rôles

| Rôle | Consultation | Écriture | Administration |
| :--- | :---: | :---: | :---: |
| `lecture_seule` | ✔ | — | — |
| `modification` | ✔ | ✔ | — |
| `administration` | ✔ | ✔ | ✔ |

## 8. Registres documentaires

Le protocole de clôture impose la synchronisation permanente de trois registres,
chacun présent dans une version Markdown (source) et HTML (exploitable) :

| Registre | Source | Exploitable |
| :--- | :--- | :--- |
| Journal des modifications | `docs/changelog.md` | `docs/changelog.html` |
| Registre des fonctionnalités | `docs/features.md` | `docs/features.html` |
| Registre de recette & qualité | `docs/qa_recette.md` | `docs/qa_recette.html` |

```bash
php scripts/build_docs.php   # régénère les trois versions HTML
```

## 9. Règles de contribution

* Chaque fichier PHP démarre par `declare(strict_types=1);`.
* PDO exclusivement, requêtes préparées, aucune concaténation SQL.
* `alert()`, `confirm()` et `prompt()` sont proscrits : modales Tabler et toasts uniquement.
* Toute action modifiant l'état passe par `POST` et un jeton CSRF.
