# Guide d'architecture BDD : Du Local (Docker) à Alwaysdata (Production)

> **Projet :** KLAP — Carnet de bord numérique  
> **Contexte :** SAÉ BUT3 Informatique — IUT d'Aix-Marseille Université  
> **Destinataires :** Équipe de développement KLAP  

---

## 1. Contexte et Problématique

Dans notre projet, nous avons deux environnements distincts :
1. **L'environnement local (sur nos PC respectifs) :** Nous utilisons **Docker Compose** avec un conteneur MariaDB.
2. **L'environnement de production (Alwaysdata) :** C'est là que l'application finale sera hébergée pour être consultée par Mariette Travard et nos évaluateurs. Alwaysdata ne fait pas tourner de conteneurs Docker pour la base de données, mais fournit un serveur MySQL natif hébergé et administré.

**La question clé :** *Comment synchroniser notre schéma de base de données (nos 16 tables, clés étrangères, vues) entre nos PC locaux et Alwaysdata sans devoir tout recréer manuellement dans phpMyAdmin à chaque modification ?*

**La réponse :** Grâce aux **migrations Laravel** exécutées via **SSH**.

---

## 2. Le principe des Migrations Laravel

Plutôt que d'écrire des scripts `.sql` séparés ou de cliquer dans phpMyAdmin :
- Chaque table est décrite dans un fichier de migration PHP versionné sous Git (dans `backend/database/migrations/`).
- Laravel connaît l'état de la base grâce à une table interne `migrations`.
- La commande magique :
  ```bash
  php artisan migrate
  ```
  Lit toutes les migrations qui n'ont pas encore été jouées et crée/met à jour les tables automatiquement, que l'on soit sur Docker en local ou sur les serveurs d'Alwaysdata.

---

## 3. Le Fonctionnement en Détail (Option 1 : Déploiement SSH)

### Étape 1 : Création de la base sur Alwaysdata (1 seule fois au début)

Sur le panneau d'administration Alwaysdata (**Bases de données > MySQL**) :
1. On clique sur **« Ajouter une base de données »** (ex: `klap_db`).
2. On crée un utilisateur avec les droits d'accès et un mot de passe.
3. Alwaysdata nous donne une adresse de serveur MySQL dédiée, par exemple : `mysql-moncompte.alwaysdata.net`.

À ce stade, la base existe mais elle est **totalement vide**.

---

### Étape 2 : La configuration via le fichier `.env`

Le code PHP de l'application Laravel est **exactement le même** en local et sur Alwaysdata. La seule différence réside dans le fichier `.env` (non versionné sur Git) :

```ini
# ==========================================
# EN LOCAL (sur nos PC via Docker)
# ==========================================
DB_CONNECTION=mysql
DB_HOST=db                         # Nom du service Docker interne
DB_PORT=3306
DB_DATABASE=klap
DB_USERNAME=klap_user
DB_PASSWORD=mot_de_passe_local

# ==========================================
# SUR ALWAYSDATA (en Production)
# ==========================================
DB_CONNECTION=mysql
DB_HOST=mysql-moncompte.alwaysdata.net  # Hôte fourni par Alwaysdata
DB_PORT=3306
DB_DATABASE=moncompte_klap              # Nom de la base sur Alwaysdata
DB_USERNAME=moncompte_user              # Nom d'utilisateur Alwaysdata
DB_PASSWORD=mot_de_passe_alwaysdata     # Mot de passe Alwaysdata
```

---

### Étape 3 : Le déploiement et la commande magique via SSH

Alwaysdata fournit un accès terminal en **SSH** pour tous les comptes.

Une fois que notre code est déployé sur Alwaysdata (via `git pull origin main`) :

1. **On se connecte en SSH au serveur Alwaysdata depuis notre terminal :**
   ```bash
   ssh moncompte@ssh-moncompte.alwaysdata.net
   ```

2. **On se rend dans le dossier de notre application :**
   ```bash
   cd www/backend
   ```

3. **On lance la commande de migration :**
   ```bash
   php artisan migrate --force
   ```
   *(Le flag `--force` est demandé par Laravel en production pour confirmer qu'on souhaite appliquer les changements).*

4. **Résultat immédiat :**
   Laravel se connecte au MySQL d'Alwaysdata, crée l'ensemble des tables métier (`utilisateurs`, `projets`, `sous_projets`, `seances`, `fiches`, `exercices`, etc.) avec leurs types exacts, leurs clés étrangères et leurs contraintes en **moins de 3 secondes**.

---

## 4. Pourquoi cette approche est la meilleure pour l'équipe ?

| Avantages | Explication |
|---|---|
| **Zéro risque d'oubli** | Personne n'a besoin de se rappeler d'ajouter une colonne ou une table à la main sur phpMyAdmin. Si c'est dans Git, c'est appliqué. |
| **Autonomie en local** | Chacun des 6 membres de l'équipe développe avec son propre Docker sans risque de corrompre la base des autres ou la base d'Alwaysdata. |
| **Historique & Traçabilité** | Toute évolution de la base de données est tracée dans l'historique des commits Git (qui a modifié quelle table et quand). |
| **Remplissage de données de test (Seeders)** | En lançant `php artisan db:seed`, on peut injecter instantanément des faux projets, fausses fiches et faux utilisateurs pour tester l'application ou faire une démo à Mariette Travard. |
| **Standard professionnel** | C'est la méthode rigoureuse enseignée en IUT et utilisée en entreprise (intégration continue / déploiement continu). |

---

## 5. Résumé pour l'équipe

1. **En local :** On code et on teste nos tables avec Docker (`docker compose exec backend php artisan migrate`).
2. **Sur Git :** On commite nos fichiers de migration dans `backend/database/migrations/`.
3. **Sur Alwaysdata :** On configure le `.env` de production avec les accès Alwaysdata, et on lance `php artisan migrate --force` en SSH. La base de production est à jour immédiatement.
