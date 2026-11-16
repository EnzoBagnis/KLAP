# Campagne de tests du 14 novembre 2026

La veille, la cliente avait essayé l'application pour de vrai et s'était retrouvée bloquée dès la
première heure : toutes nos démonstrations partaient de données déjà remplies, jamais d'une
application vide. On a donc rejoué tout le parcours depuis zéro, puis cherché à casser l'application.

Ce document dit ce qu'on a testé, ce qu'on a trouvé, ce qu'on a corrigé et ce qu'on n'a pas testé.

## En bref

| | Résultat |
|---|---|
| Parcours complet sur une base vide | aucun défaut bloquant, deux défauts d'affichage corrigés (#275) |
| Chiffres (statistiques, Excel, pré-bilan) | recalculés à la main : tous concordent |
| Mêmes actions envoyées au même instant | quatre défauts trouvés et corrigés (#277) |
| Volume (1 772 fiches) | chaque écran répond en moins de 300 ms |
| Parcours de démonstration | conforme à [DEMO.md](DEMO.md) |
| Clavier et accessibilité | aucune violation WCAG 2.1 AA détectée sur trois écrans |
| Serveur en ligne | réglages vérifiés, aucune erreur dans le journal depuis l'installation |

## 1. Les conditions

- Le code testé est celui de la production (branche `main`, PR #274), puis `premain` pour vérifier
  les corrections.
- La base est MariaDB 11, vidée avant de commencer. La base de développement et le serveur en ligne
  n'ont reçu aucune écriture.
- Le frontend est le build de production, pas le serveur de développement.
- Les mails partent dans un journal local : on y relève les liens d'invitation et de mot de passe.

## 2. Le parcours complet

Le jeu de données : 4 comptes sur les 3 rôles, 2 projets (un à 3 groupes, un à groupe unique dont
l'administratrice est l'intervenante), 22 séances, 16 fiches.

Ce qu'on a vérifié :

- **Création des comptes** : lien du premier compte, invitation, activation, lien remplacé, lien
  déjà utilisé, mot de passe trop faible.
- **Refus attendus** : doublons de financeur, de lieu, de type et de groupe ; dates inversées ;
  séance sans groupe ; chevauchement d'horaires ; groupe d'un autre projet ; fiche incomplète ;
  séance pas encore terminée ; aucun présent ; onze exercices ; note hors de 1 à 5. Chaque refus
  donne un message en français qui dit quoi corriger.
- **Droits** : chaque action interdite à un rôle est refusée par le serveur, y compris sans
  connexion. Un projet ou une fiche interdits répondent comme s'ils n'existaient pas.
- **Vie d'une séance** : modification, annulation, rétablissement, lieu corrigé depuis la fiche,
  lieu désactivé gardé par les séances qui l'utilisent déjà.
- **Verrouillage** : délai dépassé, modification refusée, demande de réouverture, notification à
  l'administratrice, réouverture, dix jours de plus.
- **Administration** : désactivation et réactivation d'un lieu, d'un financeur, d'un type, d'un
  groupe et d'un compte ; archivage et rétablissement d'un projet ; changement de mot de passe.
- **Écrans** : 84 affichages (3 rôles, ordinateur à 1280 px et téléphone à 390 px), sans erreur
  JavaScript. Une fiche a été remplie et soumise à la souris en largeur de téléphone, une séance
  programmée depuis le Planning.

### Les chiffres, recalculés à la main

| Groupe | Présents saisis | Participants attendus | Affiché |
|---|---|---|---|
| 6e A (24 prévus) | 22, 23, 24, 22, 21, 18 | 24, le maximum | 24 |
| 6e B (26 prévus) | 25, 24, 26, 25 | 26 | 26 |
| 5e C (22 prévus) | 20, 21, 19 | 21 | 21 |
| Atelier seniors (15 prévus) | 12, 14, 15 | 15 | 15 |

Total : 86 participants et 13 séances documentées, à l'écran, dans le classeur Excel et dans les
pré-bilans Word. Une fiche restée en brouillon n'est pas comptée. Les moyennes de présents,
l'assiduité et le filtre par période concordent aussi.

### Deux défauts d'affichage, corrigés (#275)

- Le tableau « Par projet » des statistiques demandait 1 343 px de large : sur un ordinateur
  portable, les dernières colonnes ne se voyaient qu'en faisant défiler.
- Dans « Programmer une séance », le message « Choisissez le projet. » restait affiché une fois le
  projet choisi.

## 3. Les mêmes actions au même instant

Un double appui, deux onglets ou deux personnes peuvent envoyer la même requête au même moment. On
l'a simulé : 6 à 8 requêtes identiques parties ensemble, sur un serveur à 8 processus.

| Action | Avant | Après correction |
|---|---|---|
| Programmer 8 fois la même séance | 7 séances créées | 1 séance, 7 refus |
| Ajouter 6 fois le même groupe | 3 groupes en double | 1 groupe, 5 refus |
| Créer 6 fois le même type de projet | 3 erreurs du serveur | 1 type, 5 refus |
| Inviter 6 fois la même adresse | 4 erreurs du serveur | 6 invitations, la dernière remplace les autres |
| Créer 96 fiches à 6 en parallèle | 10 erreurs du serveur | 480 fiches sur 480 à 8 en parallèle |

Sans défaut dès le départ : ouvrir 8 fois la même fiche (une seule est créée), soumettre 6 fois la
même fiche (une seule notification), créer le même financeur ou le même lieu, annuler une séance
pendant qu'on ouvre sa fiche. Deux personnes qui enregistrent des champs différents de la même
fiche ne perdent rien ; sur le même champ, la dernière écriture gagne.

### Les causes, et les corrections (#277)

- **Séances et groupes** : la requête vérifiait le chevauchement ou le nom, puis écrivait. Entre
  les deux, une autre requête pouvait passer. On revérifie maintenant dans la transaction, après
  avoir verrouillé la ligne de l'intervenant·e ou celle du projet.
- **Types de projet** : la base refusait bien le doublon, mais le refus n'était pas rattrapé. Il
  répond maintenant comme la validation, avec « Ce type de projet existe déjà. ».
- **Invitations** : deux transactions se bloquaient mutuellement. Les invitations d'une même
  administratrice passent maintenant l'une après l'autre.
- **Fiches** : à la création, on supprimait les exercices d'une fiche qui n'en avait pas encore.
  En base, cette suppression à vide verrouille tout l'intervalle après le dernier exercice : deux
  fiches créées ensemble se bloquaient. On ne supprime plus que pour une fiche qui existait déjà.
- Les transactions concernées se rejouent jusqu'à trois fois quand MariaDB le demande.

Aucun test unitaire ne voit ces défauts : il faut de vraies requêtes en parallèle, sur la vraie
base. La preuve est donc cette campagne, pas un test PHPUnit.

## 4. Le volume

Avec 12 projets, 36 groupes et 1 772 fiches rendues, soit plusieurs années d'activité :

| Écran | Temps de réponse |
|---|---|
| Tableau de bord | 143 ms |
| Planning d'un mois | 49 ms |
| Statistiques, tous les projets | 264 ms |
| Export Excel, tous les projets | 234 ms |
| Pré-bilan Word d'un projet | 163 ms |

Mesuré sur un poste de développement, une requête à la fois. La route qui liste toutes les fiches
d'un coup répond en 2,1 s à ce volume : aucun écran ne l'appelle.

## 5. Ce qu'on a relevé sans le corriger

Ce sont des choix à discuter avec la cliente, pas des défauts :

- une séance peut être programmée en dehors des dates du projet, sans avertissement ;
- un compte peut être désactivé alors qu'il a encore des séances à venir (le retirer de l'équipe
  du projet, lui, est refusé dans ce cas) ;
- l'intervenante n'est pas prévenue quand sa fiche est rouverte (déjà noté dans les notes de version).

## 6. Ce qu'on n'a pas testé

- Un vrai téléphone : les essais « téléphone » sont faits dans Chromium à 390 px de large, pas sur
  Safari (iPhone) ni sur un Android.
- Un lecteur d'écran : on a vérifié le clavier, les libellés et les règles automatiques, pas la
  lecture vocale.
- La charge sur le serveur en ligne : les mesures sont locales.
- L'arrivée réelle des mails dans une boîte : en local, ils partent dans un journal. En ligne, la
  cliente et un intervenant ont bien reçu les leurs.
