# API du carnet de bord

Toutes les routes sont sous `/api`, en JSON. Ce document suit `backend/routes/api.php` : en cas de
doute, c'est le code qui fait foi (`php artisan route:list --path=api`).

## Généralités

- **Authentification** : `POST /api/login` renvoie un jeton Sanctum. On l'envoie ensuite dans
  l'en-tête `Authorization: Bearer <jeton>`. Le jeton expire au bout de 30 jours (`SANCTUM_EXPIRATION`),
  et il est refusé dès que le compte est désactivé.
- **Rôles** : `admin` (Mariette), `super_user` (Emilie), `contributor` (intervenant·e·s). Les droits
  sont vérifiés sur le serveur, route par route : masquer un bouton à l'écran ne protège rien.
- **Toujours envoyer `Accept: application/json`** : sans lui, une requête non connectée est redirigée
  au lieu de recevoir une 401.

| Code | Sens |
|---|---|
| 401 | Pas connecté, jeton expiré ou compte désactivé : le frontend déconnecte. |
| 403 | Connecté, mais pas le droit (rôle, ou fiche verrouillée). Message dans `message`. |
| 404 | N'existe pas, **ou n'est pas visible** pour ce compte (un projet non affecté répond 404, pour ne pas révéler qu'il existe). |
| 409 | Conflit d'état : déjà archivé, fiche déjà créée, demande déjà en attente… |
| 422 | Saisie refusée : `errors` donne, par champ, des messages en français. |
| 429 | Trop d'essais : l'en-tête `Retry-After` donne le nombre de secondes à attendre. |

## Accès sans connexion

| Méthode et route | Rôle | Limite | Effet |
|---|---|---|---|
| `POST /login` | tous | 5/min par compte et IP, 30/min par IP | Connexion : `{ token, user }`. Un compte désactivé est refusé. |
| `GET /invitations/{jeton}` | tous | 10/min par IP | Lire une invitation (nom, adresse, expiration). |
| `POST /invitations/{jeton}/activate` | tous | 10/min par IP | Activer son compte en choisissant son mot de passe. |
| `POST /forgot-password` | tous | 5/min par IP, 1/min par compte | Recevoir un lien par mail. Même réponse, dans le même temps, que l'adresse existe ou non. |
| `POST /reset-password` | tous | 5/min par IP | Nouveau mot de passe avec le jeton du mail (60 min, usage unique). Ferme toutes les sessions. |

## Compte connecté

| Méthode et route | Rôle | Effet |
|---|---|---|
| `GET /me` | tous | L'utilisateur du jeton : nom, adresse, rôle, téléphone, dernière connexion. |
| `POST /logout` | tous | Supprime le jeton de cet appareil seulement. |
| `PUT /me/password` | tous (5/min) | Changer son mot de passe en donnant l'actuel. Déconnecte les autres appareils. |
| `GET /dashboard` | tous | Page d'accueil : fiches à remplir, prochaines séances, dernières fiches ; suivi pour l'administration. |
| `GET /notifications` | tous | Ses notifications (30 dernières) et le nombre de non lues. |
| `PATCH /notifications/{id}/read` | tous | Marquer une notification comme lue. |
| `POST /notifications/read-all` | tous | Tout marquer comme lu. |

## Projets

| Méthode et route | Rôle | Effet |
|---|---|---|
| `GET /projects` | tous | Projets visibles : tous pour l'administration, ceux où l'on intervient pour un·e intervenant·e. Archivés masqués, sauf `?include_archived=1`. |
| `GET /projects/{id}` | tous | Détail : groupes, équipe, financeurs. 404 si non visible. |
| `POST /projects` | admin | Créer un projet, avec ses groupes, son intervenant·e principal·e et ses financeurs. |
| `PATCH /projects/{id}` | admin | Modifier les champs envoyés. 409 si archivé. |
| `POST /projects/{id}/archive` | admin | Archiver, avec `confirm_name` = le nom exact du projet. Rien n'est effacé. |
| `POST /projects/{id}/restore` | admin | Restaurer un projet archivé (`status` facultatif). |
| `GET /project-types` · `POST` · `PATCH /project-types/{id}` | admin | Types de projet, sans suppression. |
| `GET /locations` · `POST /locations` | tous | Lieux : lister, en créer un. |
| `GET /users?role=contributor` | admin | Liste minimale (id, nom) des comptes actifs, pour les menus. |
| `GET /funders` | admin | Financeurs, pour les menus. La super-utilisatrice lit ceux d'un projet dans `GET /projects/{id}`. |

## Planning et fiches

| Méthode et route | Rôle | Effet |
|---|---|---|
| `GET /sessions?from=&to=` | tous | Séances de la période (366 jours au plus), selon les droits. Chaque séance indique sa fiche et le statut de son projet. |
| `GET /projects/{id}/sessions` | tous | Séances d'un projet visible. |
| `POST /projects/{id}/sessions` | admin | Programmer une séance. Refusée si l'intervenant·e a déjà une séance au même moment. |
| `PATCH /sessions/{id}` | admin | Modifier une séance sans fiche (mêmes règles). 409 si elle a une fiche ou si elle est annulée. |
| `POST /sessions/{id}/cancel` | admin | Annuler une séance sans fiche. Rien n'est effacé. |
| `POST /sessions/{id}/restore` | admin | Rétablir une séance annulée, si l'intervenant·e est libre à ce moment-là. |
| `GET /sessions/{id}/logbook-entry` | tous | La fiche d'une séance, si on peut la lire. |
| `POST /sessions/{id}/logbook-entry` | référent·e, admin, super_user | Ouvrir la fiche : du jour de la séance à J+10, ni séance annulée, ni projet archivé. |
| `GET /logbook-entries` · `GET /logbook-entries/{id}` | tous | Fiches lisibles : les siennes et celles de ses projets ; tout pour l'administration. |
| `PATCH /logbook-entries/{id}` | auteur·rice, admin, super_user | Enregistrer le brouillon, ou modifier une fiche rendue avant l'échéance. Accepte aussi `location_id` (lieu actif, ou lieu actuel) : il change le lieu de la séance (#215). |
| `POST /logbook-entries/{id}/submit` | auteur·rice, admin, super_user | Soumettre. La première soumission notifie l'administration. |
| `POST /logbook-entries/{id}/unlock-request` | auteur·rice | Demander la réouverture d'une fiche verrouillée. |
| `POST /logbook-entries/{id}/unlock` | admin | Rouvrir : dix jours de plus, opération tracée. |

## Statistiques, pré-bilan, comptes

| Méthode et route | Rôle | Effet |
|---|---|---|
| `GET /statistics?project_id=&from=&to=` | admin, super_user | Participants (maximum par groupe, puis somme), fiches manquantes, moyennes, provenance, âge, assiduité. |
| `GET /projects/{id}/pre-report?funder_id=&from=&to=` | admin, super_user | Pré-bilan Word (`.docx`) pour un financeur du projet. Chaque export est tracé. |
| `POST /invitations` | admin | Inviter une personne : mail avec un lien valable 7 jours. |
| `GET /accounts` | admin | Tous les comptes, les actifs d'abord. |
| `PATCH /accounts/{id}` | admin | `is_active` : désactiver (jetons révoqués) ou réactiver. Pas son propre compte. |

## Commandes

| Commande | Effet |
|---|---|
| `php artisan logbook:lock-expired` | Verrouille les fiches à J+10 (planifiée chaque nuit à 0 h 05). |
| `php artisan sanctum:prune-expired --hours=24` | Purge les jetons expirés (planifiée chaque nuit à 0 h 15). |
| `php artisan klap:demo [--date=]` | **Efface la base** et la remplit avec les données de démonstration du jour (voir `docs/DEMO.md`). |
