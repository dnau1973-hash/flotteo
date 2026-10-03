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

```bash
# 1. Renseigner les identifiants de base de données
nano config/database.php

# 2. Créer la base, appliquer le schéma et générer le compte administrateur
php scripts/install.php admin@votre-societe.fr 'MotDePasseSolide2026!'

# 3. Activer la configuration Apache
sudo cp docs/apache/flotteo.conf /etc/apache2/sites-available/flotteo.conf
sudo a2enmod rewrite headers
sudo a2ensite flotteo
sudo systemctl reload apache2
```

L'application est ensuite accessible sur `http://<hôte>/flotteo/login`.

### Jeu de démonstration (optionnel)

```bash
mysql -u <user> -p flotteo < sql/demodata.sql
```

## 3. Arborescence

```
flotteo/
├── config/          Configuration (base de données, constantes applicatives)
├── core/            Noyau : Database, Router, Controller, Auth, Csrf, View, Upload, Mailer
├── controllers/     Contrôleurs d'action, un par module
├── models/          Accès aux données et règles métier
├── services/        Logique transverse (AlertService)
├── views/           Templates PHP : layout, auth, dashboard, vehicles, …
├── public/          Racine servie par Apache (front controller + assets + uploads)
├── sql/             Schéma de base et jeu de démonstration
├── scripts/         Installation, génération des registres, tâche cron
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

## 5. Rôles

| Rôle | Consultation | Écriture | Administration |
| :--- | :---: | :---: | :---: |
| `lecture_seule` | ✔ | — | — |
| `modification` | ✔ | ✔ | — |
| `administration` | ✔ | ✔ | ✔ |

## 6. Registres documentaires

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

## 7. Règles de contribution

* Chaque fichier PHP démarre par `declare(strict_types=1);`.
* PDO exclusivement, requêtes préparées, aucune concaténation SQL.
* `alert()`, `confirm()` et `prompt()` sont proscrits : modales Tabler et toasts uniquement.
* Toute action modifiant l'état passe par `POST` et un jeton CSRF.
