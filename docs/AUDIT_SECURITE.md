# Audit de sécurité de KLAP

Audit réalisé le 9 novembre 2026 sur la branche `premain`, en local uniquement :
- base de test `klap_test`, données de démonstration ;
- backend en mode production (`APP_ENV=production`, `APP_DEBUG=false`) ;
- frontend construit pour la production (`vite build`).

Aucun test n'a visé un serveur en ligne. Ce rapport complète `CONFORMITE_SECURITE_RGPD.md`, qui décrit les
mesures prévues, en vérifiant qu'elles tiennent face aux outils et aux attaques courantes.

## En bref

| | Résultat |
|---|---|
| Vulnérabilités dans les dépendances (PHP et JavaScript) | **Aucune** |
| Secrets (mots de passe, clés) dans l'historique Git | **Aucun** (une seule alerte, un jeton factice de test) |
| Analyse statique du code (OWASP Top 10, PHP, React) | **Aucun problème** sur 178 fichiers |
| Contournement des droits entre rôles ou entre projets | **Aucun** (section 3) |
| Injection SQL, XSS, injection dans le Word | **Aucune** |
| Limites de connexion, révocation des jetons | **Conformes** |
| En-têtes HTTP | **Manquants**, corrigés pour l'API (#200). Recommandation donnée pour le frontend (section 5) |

Aucune faille exploitable n'a été trouvée. Les écarts relevés étaient des mesures de **défense en
profondeur** : ils sont corrigés dans #200, ou font l'objet d'une recommandation pour la mise en ligne.

---

## 1. Outils et méthode

| Outil | Version | Ce qu'il vérifie | Portée |
|---|---|---|---|
| [gitleaks](https://github.com/gitleaks/gitleaks) | 8.21.2 | Secrets dans **tout l'historique** Git | 185 commits, toutes les branches du dépôt d'équipe |
| [Trivy](https://trivy.dev) | 0.57.1 | Dépendances vulnérables (CVE), Dockerfiles, secrets | `composer.lock`, `package-lock.json`, Dockerfiles |
| [Semgrep](https://semgrep.dev) | 1.97.0 | Analyse statique : jeux de règles `php`, `javascript`, `react`, `secrets`, `owasp-top-ten` | `backend/app`, `routes`, `config`, `frontend/src` (178 fichiers) |
| `composer audit` | Composer 2.11 | Avis de sécurité des paquets PHP | `composer.lock` |
| `npm audit` | npm 11 | Avis de sécurité des paquets JavaScript | `package-lock.json`, dépendances de développement comprises |
| [OWASP ZAP](https://www.zaproxy.org) (scan de base) | 2.17.0 | En-têtes HTTP, cookies, CORS, fuites d'information | Frontend construit et API |
| `curl` et Playwright | | Tests manuels : droits, injections, XSS, limites, jetons | API et navigateur |

Pour refaire l'audit, les commandes sont en annexe.

---

## 2. Analyse du code et des dépendances

### Dépendances

`composer audit`, `npm audit` et Trivy ne trouvent **aucune vulnérabilité connue** :
- côté PHP : Laravel 12, Sanctum, PHPWord ;
- côté JavaScript : React 19, Vite, React Router.

L'intégration continue relance `composer audit` et `npm audit` à chaque push (`QUALITE.md`, section 1).

### Secrets

gitleaks ne lève qu'**une seule alerte**, et c'est un faux positif :
- c'est la constante `TOKEN` de `backend/tests/Feature/InvitationTest.php`, une chaîne factice utilisée par les tests ;
- les fichiers `.env` ne sont pas versionnés, seul `.env.example` l'est, et il ne contient aucun vrai secret.

### Analyse statique

Semgrep ne trouve **aucun problème**. Une recherche ciblée le confirme :
- **SQL** : toutes les requêtes passent par Eloquent ou le Query Builder. Les sept fragments SQL écrits
  à la main (`whereRaw`, `selectRaw`) sont des constantes ou utilisent des paramètres liés (`?`) ;
- **XSS** : le frontend n'utilise ni `dangerouslySetInnerHTML`, ni `innerHTML`, ni `eval`. React échappe
  tout le texte affiché.

### Docker

Trivy signale deux points sur les quatre Dockerfiles :
- les conteneurs tournent en `root` ;
- il n'y a pas de `HEALTHCHECK`.

Ces images ne servent qu'au **développement local** : la production sur Alwaysdata n'utilise pas Docker. Aucune correction n'est donc
nécessaire avant la soutenance.

---

## 3. Tests de l'API (manuels)

Comptes de démonstration : Mariette (administratrice), Emilie (super-utilisatrice), Léa (intervenante
affectée aux projets 1 et 4).

### Authentification et jetons

| Essai | Attendu | Obtenu |
|---|---|---|
| Les 7 routes protégées, appelées sans jeton | 401 | 401 partout |
| Jeton inventé | 401 | 401 |
| Jeton après déconnexion | 401 | 401 |
| Jeton d'un compte désactivé ensuite | 401 tout de suite | 401, et la reconnexion est refusée |
| 7 mots de passe faux de suite | blocage après 5 | `422` ×5, puis `429` avec `Retry-After: 59` |
| Compte inexistant, puis mot de passe faux | même message | même message : on ne peut pas savoir si une adresse a un compte |
| 7 demandes de « mot de passe oublié » | blocage après 5 | `200` ×5, puis `429` |
| 12 jetons d'invitation tirés au hasard | introuvables, puis blocage | `404` ×10, puis `429` |

### Droits : contournement horizontal et vertical

| Essai | Obtenu |
|---|---|
| Léa lit un projet où elle n'est pas affectée (2, 3, 5, 6), ses séances, son pré-bilan | `404`, `404`, `403` : le projet semble ne pas exister |
| Léa lit, modifie, soumet une fiche d'un autre projet, ou demande sa réouverture (fiches 6, 7, 8) | `404` partout |
| Léa marque comme lue une notification de Mariette | `404` |
| Léa appelle les routes d'administration (comptes, invitation, archivage, réouverture, modification de projet, statistiques) | `403` partout |
| Emilie appelle les routes réservées à l'administratrice | `403` partout |
| L'administratrice invite une autre administratrice | `422` : le rôle n'est pas proposé |
| L'administratrice désactive son propre compte | `422` : refusé |
| Glisser `role: admin` dans la modification d'un compte | `422`, et le compte reste intervenant |

### Injections

| Essai | Obtenu |
|---|---|
| Injection SQL dans les filtres des statistiques (`project_id=1' OR 1=1--`, `UNION SELECT`, `'; DROP TABLE`) | `422` : la validation refuse la valeur avant toute requête |
| Charge XSS `<img onerror>` et `<script>` enregistrée dans une fiche | Affichée comme du texte dans la fiche, aucun script exécuté (vérifié dans le navigateur) |
| Même charge, plus `]]><w:p>`, reprise dans le pré-bilan Word | XML échappé (`&lt;script&gt;`), document valide |
| Texte de 5 Mo dans un champ de la fiche | `422` : la longueur est limitée |
| Erreur provoquée en mode production | Aucune trace de pile ni chemin de fichier dans la réponse |

### Écarts relevés, et corrigés dans #200

| Constat | Risque | Correction |
|---|---|---|
| `/api/projects/1abc` renvoyait le projet 1, car MySQL convertit `'1abc'` en `1` | Faible : les droits s'appliquent quand même, rien ne fuit | Identifiants de route limités aux chiffres (`Route::patterns`). Une adresse inconnue répond comme un élément introuvable |
| Aucun en-tête de sécurité sur les réponses de l'API | Faible pour une API JSON | Middleware `SecurityHeaders` : `nosniff`, `DENY`, CSP stricte, `Referrer-Policy`, `Permissions-Policy`, HSTS en HTTPS |
| `X-Powered-By: PHP/8.5.12` | Information utile à un attaquant | En-tête retiré |
| La racine du backend servait la page d'accueil Laravel, avec un cookie de session et un cookie XSRF | Cookies inutiles | La racine répond en JSON, sans session ni cookie |
| CORS `Access-Control-Allow-Origin: *` codé en dur | Faible : le jeton Bearer n'est jamais envoyé automatiquement par le navigateur | Origines réglables avec `CORS_ALLOWED_ORIGINS` |

Des tests automatiques couvrent chaque correction (`tests/Feature/Http/SecurityHeadersTest.php`).

---

### Ajouts du 10 novembre

Deux nouveaux chemins ont été testés de l'extérieur le 10 novembre, sur l'appli en marche
(build de production, `klap_test`) : la correction du lieu depuis la fiche (#215) et l'export Excel
des statistiques (#219).

| Requête | Compte | Attendu | Obtenu |
|---|---|---|---|
| `GET /statistics/export` | aucun | 401 | 401 |
| `GET /statistics/export` | intervenant (Hugo) | 403 | 403 |
| `GET /statistics/export?project_id=1 OR 1=1` | super-utilisatrice | 422 | 422 |
| `GET /statistics/export` | super-utilisatrice | 200, sans cache partagé | 200, `Cache-Control: no-store, private`, `nosniff` |
| `PATCH /logbook-entries/2` (lieu) | intervenant d'un autre projet (Hugo) | 404 | 404 |
| `PATCH /logbook-entries/2` (lieu) | collègue du projet (Nina) | 403 | 403 |
| `PATCH /logbook-entries/1` (lieu), fiche verrouillée | son autrice (Léa) | 403 | 403 |
| `PATCH …` avec `location_id` = `"1 OR 1=1"`, ou un tableau | Léa | 422 | 422 |
| `PATCH …` avec `session_date`, `scheduled_session_id` | Léa | champs ignorés | ignorés : la séance garde sa date et la fiche sa séance |

- **Injection de formule dans Excel** : impossible. Les textes sont écrits comme des chaînes, jamais
  comme des formules ; un nom de groupe `=HYPERLINK(...)` reste un texte (test automatique).
- **Bug trouvé pendant ces tests** (#225) : une fiche rendue dont les temps forts valent « Rien » ne
  pouvait plus être modifiée, car « rien » (4 caractères) était refusé alors que le message
  l'accepte. Ce n'est pas une faille, mais un blocage : il est corrigé.

## 4. Scan OWASP ZAP

Résultat du scan de base, avant puis après les corrections :

| Cible | Avant | Après |
|---|---|---|
| API | 0 échec, 11 avertissements | 0 échec, **4** avertissements |
| Frontend | 0 échec, 8 avertissements | 0 échec, **4** avertissements (avec les en-têtes de la section 5) |

Les avertissements qui restent sont informatifs, ou ne s'appliquent pas en production :
- **« Commentaire suspect »** : faux positif, ZAP a pris le nom de classe CSS `is-todo` pour un commentaire « TODO ».
- **Contenu non mis en cache, « application web moderne »** : ce sont des informations, pas des failles.
- **`Cross-Origin-Embedder-Policy` / `Cross-Origin-Resource-Policy`** : sans objet, l'application n'utilise aucune
  fonction du navigateur qui les exige.
- **`X-Content-Type-Options` absent sur `robots.txt`** : en local, ce fichier statique est servi par le serveur PHP intégré,
  sans passer par Laravel. En production, c'est Apache qui le sert (voir section 5).
- **CORS `*`** : `*` est la valeur par défaut tant que `CORS_ALLOWED_ORIGINS` n'est pas renseignée (voir section 5).

---

## 5. Recommandations pour la mise en ligne (Alwaysdata)

À vérifier au moment du déploiement (#79). Les cinq premiers points sont **indispensables**.

1. **HTTPS obligatoire**, avec redirection de HTTP vers HTTPS. Le mot de passe et le jeton circulent dans
   chaque requête. Une fois HTTPS actif, l'API envoie d'elle-même `Strict-Transport-Security`.
2. **Dans `.env`** :
   - `APP_ENV=production` ;
   - `APP_DEBUG=false`, faute de quoi une erreur affiche le code et la configuration ;
   - `LOG_LEVEL=warning` ;
   - une `APP_KEY` propre à la production.
3. **`CORS_ALLOWED_ORIGINS`** : l'adresse exacte du frontend, sans `/` final (par exemple
   `https://klap.alwaysdata.net`). Puis relancer `php artisan config:cache`.
4. **`FRONTEND_URL`** : la même adresse. Les liens d'invitation et de mot de passe oublié en dépendent.
5. **La racine web pointe sur `backend/public`**, jamais sur `backend/`. Sinon `.env` est lisible depuis le web.
6. **PHP** : `expose_php = Off`, pour retirer aussi `X-Powered-By` des fichiers statiques.
7. **En-têtes du frontend.** Le frontend est un ensemble de fichiers statiques, servi par Apache.
   Depuis #203, le build produit `dist/.htaccess` (`frontend/build/htaccess.js`), qui :
   - renvoie les routes React vers `index.html`, sinon recharger `/projets/1` donne une 404 ;
   - envoie la CSP, avec l'adresse de l'API tirée de `VITE_API_URL`, ainsi que `nosniff`,
     `Referrer-Policy` et `Permissions-Policy` ;
   - met en cache long les fichiers `assets/`, mais jamais `index.html`.

   Il suffit de construire avec la bonne adresse d'API, puis de copier **tout** `dist/`, fichier caché
   compris :

   ```bash
   VITE_API_URL=https://ADRESSE-DE-L-API/api npm run build
   ```

   Testé dans Apache 2.4 avec le build de production :
   - connexion, rechargement de pages profondes, téléchargement du pré-bilan ;
   - aucune erreur dans la console ;
   - `.htaccess` et la liste des dossiers restent inaccessibles.

   `font-src data:` est nécessaire, car les polices sont intégrées au CSS.
8. **Sauvegardes** de la base, et procédure de restauration testée une fois.
9. **Tâches planifiées.** `php artisan schedule:run` doit tourner chaque minute (tâche cron d'Alwaysdata),
   sinon deux mécanismes s'arrêtent :
   - le passage au statut « verrouillée » des fiches de plus de 10 jours (le refus des modifications
     après le délai, lui, ne dépend pas de la tâche) ;
   - la purge des jetons expirés.
10. **Envoi des mails.** Par défaut, `MAIL_MAILER=log` : les mails sont écrits dans le journal, pas
    envoyés. Sans serveur d'envoi configuré (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`,
    `MAIL_USERNAME`, `MAIL_PASSWORD`) et sans `MAIL_FROM_ADDRESS` propre à KLAP, ni les invitations
    ni les mails de mot de passe oublié ne partent.

---

## 6. Limites de cet audit

- **Pas de test d'intrusion du serveur de production**, qui n'existe pas encore. La configuration réelle
  d'Alwaysdata (TLS, Apache, PHP) reste à vérifier avec la liste de la section 5.
- **ZAP a fait un scan passif**, pas d'attaque active. Les attaques ont été faites à la main (section 3) et
  portent sur les risques propres à KLAP : droits, données des mineurs, pré-bilan.
- **Les dépendances évoluent.** Un paquet sain aujourd'hui peut recevoir un avis demain. L'intégration
  continue relance les audits à chaque push.

---

## Annexe : refaire l'audit

Depuis la racine du dépôt :

```bash
# Secrets dans tout l'historique
docker run --rm -v "$PWD":/repo:ro zricethezav/gitleaks:v8.21.2 detect --source /repo --log-opts="--all" --redact

# Dépendances, Dockerfiles, secrets
docker run --rm -v "$PWD":/src:ro aquasec/trivy:0.57.1 fs --scanners vuln,misconfig,secret /src

# Analyse statique
docker run --rm -v "$PWD":/src:ro -w /src semgrep/semgrep:1.97.0 semgrep scan --metrics=off \
  --config p/php --config p/javascript --config p/react --config p/secrets --config p/owasp-top-ten \
  backend/app backend/routes backend/config frontend/src

# Avis de sécurité
docker compose exec backend composer audit --locked
docker run --rm -v "$PWD/frontend":/app -w /app node:24-alpine npm audit

# En-têtes HTTP (application lancée en local : API sur 8090, frontend sur 5173)
docker run --rm --network host ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t http://localhost:8090/api/login -I
docker run --rm --network host ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t http://localhost:5173/ -I
```

À lancer sur la base `klap_test`, jamais sur `klap`.
