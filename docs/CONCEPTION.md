# Conception de KLAP : les diagrammes

Ce document complète le modèle de données ([MCD-carnet-de-bord-KLAP.pdf](MCD-carnet-de-bord-KLAP.pdf))
et la description de l'API ([API.md](API.md)). Les diagrammes sont tirés du code : les classes sont
les modèles de `backend/app/Models`, les états sont ceux que vérifient les tests.

## 1. Qui fait quoi

```mermaid
flowchart LR
    I([Intervenant·e])
    S([Super-utilisatrice])
    A([Administratrice])

    I --> F[Remplir et soumettre la fiche de ses séances]
    I --> R[Demander la réouverture d'une fiche verrouillée]
    I --> P[Consulter ses projets et son planning]
    I --> L[Créer un lieu, pour un projet en tournée]

    S --> T[Consulter tous les projets et toutes les fiches]
    S --> ST[Statistiques et export Excel]
    S --> PB[Pré-bilan Word par financeur]

    A --> T
    A --> ST
    A --> PB
    A --> G[Gérer les projets, les groupes et les équipes]
    A --> PL[Programmer, modifier, annuler une séance]
    A --> C[Inviter et désactiver des comptes]
    A --> LI[Tenir les listes : financeurs, types, lieux]
    A --> RO[Rouvrir une fiche verrouillée]
```

Une administratrice peut aussi être intervenante : elle remplit alors les fiches des séances dont
elle est la référente. Les droits sont vérifiés par le serveur à chaque requête.

## 2. Les classes

```mermaid
classDiagram
    class User { name; email; role; is_active }
    class Invitation { email; role; expires_at }
    class Project { name; status; start_date; end_date; planned_sessions }
    class ProjectType { name; is_active }
    class SubProject { name; age_range; city; postal_code; expected_headcount; is_active }
    class Funder { name; type; is_active }
    class Location { name; city; postal_code; is_active }
    class ScheduledSession { session_date; start_time; end_time; session_type; status }
    class LogbookEntry { status; edit_deadline; objectives; highlights; next_steps }
    class ParticipantEvaluation { engagement; expression; autonomy; interaction; feeling; progress }
    class Exercise { position; label }
    class KlapNotification { type; read_at }

    ProjectType "1" --> "*" Project
    Project "1" --> "*" SubProject : groupes
    Project "*" --> "*" Funder
    Project "*" --> "*" User : équipe
    Project "*" --> "1" Location : lieu par défaut
    Project "1" --> "*" ScheduledSession
    ScheduledSession "*" --> "*" SubProject : présents par groupe
    ScheduledSession "*" --> "1" Location
    ScheduledSession "*" --> "1" User : référent·e
    ScheduledSession "1" --> "0..1" LogbookEntry
    LogbookEntry "*" --> "1" User : auteur·rice
    LogbookEntry "1" --> "0..1" ParticipantEvaluation
    LogbookEntry "1" --> "*" Exercise
    LogbookEntry "1" --> "*" KlapNotification
    KlapNotification "*" --> "1" User : destinataire
    Invitation "*" --> "1" User : invité par
```

Le nombre de présents est porté par le lien entre la séance et chacun de ses groupes : c'est lui qui
sert à compter les participants (le maximum par groupe, puis la somme des groupes). On ne stocke
aucun élève.

## 3. La vie d'une fiche

```mermaid
stateDiagram-v2
    state "Brouillon" as B
    state "Brouillon hors délai" as BH
    state "Soumise" as S
    state "Verrouillée" as V
    [*] --> B : ouverte à partir du jour de la séance
    B --> B : enregistrement
    B --> S : soumission, fiche complète
    S --> S : modification avant l'échéance
    S --> V : échéance dépassée, tâche de chaque nuit
    B --> BH : échéance dépassée
    V --> S : réouverture par l'administratrice
    BH --> B : réouverture par l'administratrice
```

L'échéance tombe 10 jours après la séance. Un brouillon hors délai garde son statut : c'est la date
qui le ferme. Une réouverture donne 10 jours à partir du jour où elle est accordée.

Une séance annulée n'a pas de fiche, et une séance qui a une fiche ne s'annule plus ni ne se modifie.
Un projet archivé fige ses séances et ses fiches.

## 4. Remplir et soumettre une fiche

```mermaid
sequenceDiagram
    actor Léa as Intervenante (téléphone)
    participant Front as Interface React
    participant API as API Laravel
    participant Base as MariaDB
    actor Mariette as Administratrice

    Léa->>Front: « Remplir la fiche »
    Front->>API: POST /sessions/{id}/logbook-entry
    API->>Base: verrouille la séance, crée le brouillon
    API-->>Front: 201, la fiche
    loop à chaque étape
        Léa->>Front: saisit une étape
        Front->>API: PATCH /logbook-entries/{id}
        API->>Base: enregistre le brouillon
    end
    Léa->>Front: « Soumettre la fiche »
    Front->>API: POST /logbook-entries/{id}/submit
    API->>API: vérifie que la fiche est complète
    alt fiche incomplète
        API-->>Front: 422, les champs à corriger, par étape
    else fiche complète
        API->>Base: statut « soumise », notification à l'administration
        API-->>Front: 200
        Mariette->>Front: la cloche s'allume (relevé toutes les 30 secondes)
    end
```

## 5. Du code à la production

```mermaid
flowchart LR
    I[Issue] --> B[Branche depuis premain] --> PR[Pull request]
    PR --> CI{CI : style, analyse statique, tests, audits}
    CI -- verte --> PM[premain]
    CI -- rouge --> B
    PM --> M[main] --> AD[Alwaysdata : code, dépendances, migrations]
```
