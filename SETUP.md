# Setup du projet KLAP avec Docker

Ce guide vous permet d'installer, configurer et lancer l'environnement de développement du projet **KLAP** (Backend Laravel 12, Base de données MariaDB 11, et Frontend React).

---

## 1. Prérequis

- **Docker Desktop** (avec Docker Compose) installé et **démarré** sur votre machine.
  > [!IMPORTANT]
  > Assurez-vous que Docker Desktop est bien lancé (icône verte *Engine running*) avant d'exécuter la moindre commande Docker.
- **Git** installé.

---

## 2. Installation pas à pas

### Étape 1 : Cloner le dépôt et se placer dans le projet

```bash
git clone <url-du-repo>
cd KLAP
```

---

### Étape 2 : Configurer les variables d'environnement (`.env`)

Copiez le fichier d'exemple pour créer votre fichier `.env` local :

**Sur Linux / macOS / Git Bash :**
```bash
cp .env.example .env
```

**Sur Windows (PowerShell) :**
```powershell
Copy-Item .env.example .env
```

#### Générer des mots de passe sécurisés pour la BDD

Ouvrez le fichier `.env` à la racine. Vous devez définir des mots de passe robustes pour `DB_ROOT_PASSWORD` et `DB_PASSWORD`. 

Pour générer des chaînes aléatoires sécurisées sans installer d'outils supplémentaires, utilisez l'une des méthodes ci-dessous :

- **Méthode A — Sur Windows (PowerShell) :**
  ```powershell
  $bytes = New-Object byte[] 24; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes); [Convert]::ToBase64String($bytes)
  ```
  *(Exécutez cette commande 2 fois : une fois pour `DB_PASSWORD`, une fois pour `DB_ROOT_PASSWORD`).*

- **Méthode B — Sur Linux / macOS / Git Bash (OpenSSL) :**
  ```bash
  openssl rand -base64 24   # pour DB_PASSWORD
  openssl rand -base64 24   # pour DB_ROOT_PASSWORD
  ```

- **Méthode C — Avec Python (multiplateforme) :**
  ```bash
  python -c "import secrets; print(secrets.token_urlsafe(24))"
  ```

Collez ces valeurs dans votre fichier `.env` :
```env
DB_PASSWORD=<valeur_générée_1>
DB_ROOT_PASSWORD=<valeur_générée_2>
```

#### Synchroniser avec le backend
Comme le dossier `backend/` est monté dans le conteneur, dupliquez votre fichier `.env` dans `backend/.env` :
```bash
# Linux / macOS / Git Bash
cp .env backend/.env

# Windows PowerShell
Copy-Item .env backend\.env -Force
```

> [!WARNING]
> Ne commitez jamais votre fichier `.env` sur Git (il est déjà listé dans `.gitignore`).

---

### Étape 3 : Lancer les conteneurs Docker

Pour démarrer la Base de données et l'API Backend :

```bash
docker compose up -d --build db backend
```
*(L'option `-d` lance les conteneurs en tâche de fond pour libérer votre terminal).*

> [!NOTE]
> Tant que le code de l'application frontend React est en cours de structuration, lancez uniquement `db` et `backend`. Une fois le frontend prêt, `docker compose up -d --build` démarrera l'ensemble des 3 services.

---

### Étape 4 : Initialiser Laravel et la base de données

Une fois les conteneurs démarrés (laissez 5 à 10 secondes à MariaDB pour son premier démarrage) :

1. **Générer la clé d'application Laravel** (si `APP_KEY` est vide dans votre `.env`) :
   ```bash
   docker compose exec backend php artisan key:generate
   ```

2. **Exécuter les migrations de la base de données** :
   ```bash
   docker compose exec backend php artisan migrate
   ```
   > [!IMPORTANT]
   > Cette commande est obligatoire pour créer la table `sessions` et les tables de base de données. Sans elle, Laravel renverra une erreur `Table 'klap.sessions' doesn't exist`.

---

### Étape 5 : Vérifier le bon fonctionnement

- **Connexion Laravel vers MariaDB** : c'est la vérification la plus utile, elle échoue explicitement si le `.env` est mal renseigné.
  ```bash
  docker compose exec backend php artisan db:monitor
  ```
  Vous devez lire `mysql` suivi de `OK`. Si la ligne indique `sqlite`, c'est que `DB_CONNECTION` est absent ou mal défini dans `backend/.env` : Laravel est alors sur un fichier SQLite local et non sur MariaDB.

  Le même contrôle est automatisé côté tests :
  ```bash
  # Vérifie la configuration de connexion (tourne sur SQLite, ne touche pas MariaDB)
  docker compose exec backend php artisan test --filter=DatabaseConnectionTest

  # Interroge en plus la vraie base MariaDB
  docker compose exec -e DB_DATABASE=klap backend php artisan test --filter=DatabaseConnectionTest
  ```
  La suite de tests tourne sur SQLite en mémoire (`phpunit.xml` impose `DB_DATABASE=:memory:`), donc le test de connexion réelle se met en *skipped* dans le premier cas et vous indique comment l'exécuter. La seconde commande rétablit le nom de la base et interroge réellement MariaDB.

  > [!NOTE]
  > `php artisan db:show`, plus détaillée, n'est pas utilisable en l'état : elle se termine sur `The "intl" PHP extension is required` (code de sortie 1), cette extension n'étant pas installée dans l'image backend.

- **Backend (API Laravel)** : Ouvrez [http://localhost:8080](http://localhost:8080) dans votre navigateur. Vous devez voir la page d'accueil Laravel s'afficher avec succès (statut 200 OK).
- **Base de données (MariaDB)** : Accessible depuis votre machine sur `localhost:3306`.
  - Hôte : `localhost` (ou `127.0.0.1`)
  - Port : `3306`
  - Base : `klap`
  - Utilisateur : `klap_user` (ou `root`)
  - Mot de passe : celui configuré dans votre `.env`
  - Vous pouvez vous y connecter avec n'importe quel client SQL (DBeaver, TablePlus, HeidiSQL, extension VS Code Database Client...).

---

## 3. Utilisation au quotidien

### Démarrer le projet
```bash
docker compose up -d
# ou simplement
docker compose start
```

### Arrêter le projet
```bash
# Arrêt propre (garde l'état des conteneurs)
docker compose stop

# Arrêt complet et retrait des conteneurs
docker compose down
```

### Voir les logs en direct
```bash
# Logs du backend
docker compose logs -f backend

# Logs de la base de données
docker compose logs -f db
```

### Exécuter des commandes Laravel (Artisan et Composer)
Toutes les commandes s'exécutent directement dans le conteneur sans avoir besoin de PHP ou Composer installé sur votre machine :

```bash
# Liste des commandes Artisan disponibles
docker compose exec backend php artisan list

# Créer un nouveau modèle avec sa migration et son contrôleur
docker compose exec backend php artisan make:model NomDuModele -mcr

# Exécuter les migrations
docker compose exec backend php artisan migrate

# Installer une dépendance PHP avec Composer
docker compose exec backend composer require nom-du-package
```

### Réinitialiser complètement la base de données
Si vous souhaitez repartir d'une base totalement vierge :
```bash
docker compose down -v   # Le flag -v supprime aussi le volume db_data contenant les données
docker compose up -d db backend
docker compose exec backend php artisan migrate
```

---

## 4. Notes d'architecture & astuces techniques

- **Rechargement à chaud du code PHP :** Le dossier `./backend` est monté en volume (`./backend:/var/www/html`). Dès que vous modifiez un fichier PHP en local, il est instantanément pris en compte par Apache dans Docker sans besoin de reconstruire ou redémarrer le conteneur.
- **Connexion à MariaDB :**
  - **Depuis le code Laravel (backend)** : L'hôte de connexion est `db` (résolution interne Docker), configuré via `DB_HOST=db`.
  - **Depuis votre machine hôte (DBeaver, etc.)** : L'hôte est `localhost` ou `127.0.0.1`.
- **Résolution DNS Docker (`--skip-name-resolve`) :** MariaDB est configuré avec `--skip-name-resolve` dans `docker-compose.yml` afin d'éviter la latence de résolution inverse sur les adresses IP internes du réseau Docker.

---

## 5. Dépannage rapide (FAQ)

| Problème rencontré | Cause | Solution |
|---|---|---|
| `failed to connect to the docker API` ou `daemon not running` | Docker Desktop n'est pas lancé | Démarrez l'application **Docker Desktop** sous Windows/Mac et attendez que le statut soit *Engine running*. |
| `SQLSTATE[42S02]: Table 'klap.sessions' doesn't exist` | Les migrations n'ont pas encore été jouées dans MariaDB | Exécutez : `docker compose exec backend php artisan migrate` |
| `No application encryption key has been specified` | La variable `APP_KEY` est vide dans le `.env` | Exécutez : `docker compose exec backend php artisan key:generate` |
| `port is already allocated` (ex: 8080 ou 3306) | Un autre service local utilise déjà ce port (ex: MySQL local, Wamp, XAMPP, un autre Docker) | Arrêtez le service en conflit ou changez le port hôte dans `docker-compose.yml` (ex: `"8081:80"` ou `"3307:3306"`). |
| Latence / requêtes lentes vers la BDD | Résolution DNS inverse dans MariaDB | Vérifiez la présence de `command: --skip-name-resolve` sous le service `db` dans `docker-compose.yml`. |
