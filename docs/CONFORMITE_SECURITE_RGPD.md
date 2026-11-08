# Sécurité, données personnelles et RGPD

> **Projet :** KLAP, carnet de bord numérique
> **Structure cliente :** KLAP Maison pour la danse / Kelemenis & cie (Marseille)
> **Référente :** Mariette Travard, responsable des publics, administratrice de l'application
> **Rédaction :** Lucas Lepape, puis mise à jour de l'équipe pour coller au code de `premain` (#121)
> **Cadre :** SAÉ BUT 3 Informatique, IUT d'Aix-Marseille Université

Ce document décrit **ce que l'application fait réellement**. Ce qui n'est pas encore fait, ou qui
relève d'une décision de KLAP, est regroupé à la section 6 au lieu d'être présenté comme acquis.

---

## 1. Les données traitées

| Qui | Données | Où |
|---|---|---|
| Comptes (administratrice, super-utilisatrice, intervenant·e·s) | Nom, adresse mail, téléphone (facultatif), rôle, mot de passe haché, date de dernière connexion, état actif ou non | `users` |
| Invitations | Adresse mail, nom, rôle, jeton haché, date d'expiration | `invitations` |
| Groupes (classes, ateliers) | Nom du groupe, structure, classe, **tranche d'âge**, ville, code postal, effectif attendu | `sub_projects` |
| Séances | Date, horaires, lieu, intervenant·e référent·e | `scheduled_sessions` |
| Fiches de séance | **Nombre** de présents par groupe, notes collectives sur 5 (engagement, expression, autonomie), sensation, progression, et des **champs de texte libre** (objectifs, temps forts, acquis, bilan de l'intervenant·e, suites, notes) | `logbook_entries`, `participant_evaluations`, pivot `scheduled_session_sub_project` |
| Notifications | Message qui cite le nom de l'intervenant·e et le projet | `klap_notifications` |
| Exports | Qui a exporté quel pré-bilan, pour quel financeur et quelle période | `report_exports` |

---

## 2. Les publics mineurs

Les ateliers s'adressent surtout à des élèves mineurs. L'application applique la **minimisation**
(article 5.1.c du RGPD) :

- **Aucun élève n'est enregistré individuellement.** Il n'y a ni nom, ni prénom, ni date de naissance d'élève.
  Une fiche compte les présents par groupe (un nombre), et les notes portent sur le groupe, pas sur une personne.
- Le groupe porte seulement une tranche d'âge (« 16-17 ans ») et une ville, ce qui suffit aux statistiques.
- Les statistiques sont agrégées. Le nombre de participants suit la règle de la cliente :
  pour chaque groupe, le maximum de présents sur ses séances, puis la somme des groupes.

**Limite à connaître :** les champs de **texte libre** peuvent contenir un prénom ou une remarque sur un
élève (par exemple « tensions entre deux élèves »). Ils ne sont donc pas anonymes par nature. Les mesures prises :
- un intervenant ne lit que les fiches des projets où il intervient (section 4) ;
- le **pré-bilan envoyé aux financeurs** ne reprend que les « temps forts » et les « notes complémentaires ».
  Le bilan de l'intervenant·e et les suites restent internes (#143) ;
- un rappel en haut de chaque fiche modifiable : « Ne citez pas d'élève par son nom » (#157).

---

## 3. Les comptes

- **Pas d'inscription libre.** Un compte naît d'une invitation envoyée par l'administratrice.
  - Le lien contient un jeton aléatoire de 64 caractères ; seule son empreinte est stockée en base.
  - Le lien est valable 7 jours et ne sert qu'une fois. Une nouvelle invitation à la même adresse remplace la précédente.
  - L'invité·e choisit son mot de passe : au moins 8 caractères, avec majuscule, minuscule, chiffre et caractère spécial.
- **Désactivation** : un compte a un état actif ou inactif (`is_active`). Il n'est jamais supprimé, pour garder
  l'historique des séances et des fiches. Un compte inactif :
  - ne peut plus se connecter ;
  - perd aussitôt l'accès, même avec un jeton déjà ouvert, et ses jetons sont supprimés (#148).

  L'écran pour désactiver un compte reste à faire (section 6).

---

## 4. Contrôle d'accès

Trois rôles, emboîtés comme le demande le cahier des charges (#141) :

| Rôle | Qui | Droits |
|---|---|---|
| `admin` | Mariette Travard | Tout : projets (création, modification, archivage), planning, invitations, réouverture des fiches, statistiques, pré-bilan |
| `super_user` | Emilie Garetier | Lecture de tout, modification et création des fiches, statistiques et pré-bilan. Pas de gestion des projets ni des comptes |
| `contributor` | Intervenant·e·s | Les projets où il ou elle intervient, et les fiches de ces projets. Ne modifie que ses propres fiches |

- Les droits sont vérifiés **côté serveur**, sur chaque route. Masquer un bouton à l'écran n'est pas une protection.
- Un projet auquel on n'a pas accès répond 404, comme un projet inexistant : l'API ne révèle pas qu'il existe.

**Mesures techniques :**
- **Mots de passe** : hachés avec bcrypt (coût 12, `BCRYPT_ROUNDS`).
- **Jetons d'API** : jetons Laravel Sanctum, envoyés en en-tête `Authorization: Bearer`.
  - Seule leur empreinte SHA-256 est stockée en base.
  - La déconnexion supprime le jeton de l'appareil utilisé.
  - Un jeton expire au bout de 30 jours (`SANCTUM_EXPIRATION`). Les jetons expirés sont purgés chaque nuit (#157).
- **Tentatives de connexion limitées** (#150) : au plus 5 par minute pour un même compte depuis une même adresse IP,
  et 30 par minute par adresse IP. Au-delà, la réponse est 429 avec un délai d'attente.
  Des collègues sur le même Wi-Fi ne se bloquent pas entre eux.
- **Message d'échec identique** que l'adresse existe ou non : la connexion ne révèle pas quels comptes existent.
- **Projets jamais effacés** : la suppression n'existe pas. L'archivage demande de saisir le nom exact du projet et se défait (#152).

---

## 5. Intégrité des fiches

- **Verrouillage à J+10** : une fiche n'est plus modifiable dix jours après sa séance (heure de Paris).
  - Une tâche planifiée passe chaque nuit les fiches concernées en `locked` (`logbook:lock-expired`, 0 h 05).
  - Le délai s'applique aussi aux comptes administrateurs.
- **Réouverture** :
  - seule l'administratrice rouvre une fiche, et la fiche a alors **dix jours de plus** ;
  - l'auteur·rice d'une fiche verrouillée peut demander la réouverture depuis l'application, sans motif à saisir ;
  - le dernier déverrouillage est tracé : qui, et quand.
- **Pré-bilan** : il est exporté en Word (`.docx`) pour être retravaillé avant l'envoi, et chaque export est tracé.
  - Il ne contient que des chiffres agrégés et les textes repris d'une fiche.
  - Il ne contient aucun nom d'élève, ni d'auteur·rice de fiche.

---

## 6. À faire ou à décider avec KLAP

| Sujet | État | Proposition |
|---|---|---|
| Responsable de traitement, finalité, base légale | Non formalisé | KLAP est responsable de traitement. Finalité : suivi des actions artistiques et bilans aux financeurs. Base légale : intérêt légitime ou mission, à confirmer par KLAP. Inscription au registre des traitements de KLAP |
| Durées de conservation | Rien n'est purgé | Fixer une durée avec KLAP (par exemple la durée des conventions de financement), puis ajouter une purge ou une anonymisation des fiches anciennes |
| Hébergement | Prévu sur Alwaysdata (#79) | Vérifier le lieu d'hébergement des données et signer l'accord de sous-traitance (article 28) |
| HTTPS | À la mise en ligne | HTTPS obligatoire en production (identifiants et jetons circulent dans les requêtes) |
| Écran de gestion des comptes | Liste et invitations seulement | Désactiver un compte et changer un rôle depuis l'écran Utilisateurs |
| Droits des personnes (accès, rectification) | Par l'administratrice | Procédure à écrire : une demande d'un·e intervenant·e passe par Mariette |
| Champs personnalisés | Tables présentes (`custom_fields`), non utilisées | Seulement si la cliente le demande |
| Export des statistiques | Seul le pré-bilan Word existe | Format à décider avec la cliente (question ouverte) |

---

## 7. Récapitulatif

| Domaine | Ce qui est en place | Où dans le code |
|---|---|---|
| Mineurs | Aucun élève enregistré, présents comptés par groupe, notes collectives | `participant_evaluations`, pivot des présents |
| Comptes | Invitation seulement, jeton haché valable 7 jours, mot de passe fort, désactivation immédiate | `InvitationController`, `AppServiceProvider` |
| Accès | Trois rôles vérifiés côté serveur, 404 sur un projet non accessible | `EnsureUserHasRole`, scopes `visibleTo` et `readableBy` |
| Attaques | Bcrypt, empreintes des jetons, jetons expirés après 30 jours, limite des connexions par compte et par IP | `AppServiceProvider`, `config/sanctum.php`, `routes/api.php` |
| Intégrité | Verrouillage J+10 planifié, réouverture par l'administratrice seule, archivage sans effacement | `LockExpiredLogbookEntries`, `LogbookEntryController`, `ProjectController` |
| Financeurs | Pré-bilan Word sans nom de personne, export tracé | `PreReportService`, `report_exports` |
