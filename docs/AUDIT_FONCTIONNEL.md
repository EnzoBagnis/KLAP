# Audit fonctionnel : les cas d'utilisation, de bout en bout

Le 10 novembre 2026, toute l'application a été parcourue par l'interface, comme le feraient Mariette,
Emilie et les intervenant·e·s. Le parcours s'est fait sur le build de production (branche `premain`),
avec les données de démonstration (`php artisan klap:demo`) dans une base de test. Les parcours ont
été automatisés avec Playwright :
- sur ordinateur (1440 × 900) pour l'administratrice et la super-utilisatrice ;
- sur téléphone (390 × 844) pour les intervenant·e·s.

Les mails ont été lus dans le journal de Laravel (`MAIL_MAILER=log`).

Ce document donne ce qui a été fait, ce qui marche, et les anomalies trouvées, avec leur suite :
corrigée, proposée, ou en attente d'une décision de la cliente.

## 1. Ce qui a été parcouru

### Le parcours de Mariette, sans aide
1. **Inviter** une intervenante et un intervenant. Le mail arrive, le lien permet de choisir un mot
   de passe, et la cloche annonce « … a activé son compte ».
2. **Créer un projet** avec deux groupes, un financeur et une intervenante principale.
3. **Programmer des séances** :
   - une séance simple ;
   - une séance sur deux groupes (transversale) ;
   - une séance qui chevauche, refusée ;
   - une séance accolée (12 h-14 h après 10 h-12 h), acceptée ;
   - une séance dans un nouveau lieu.

   Elle modifie, annule, puis rétablit une séance.
4. **Suivre** : le tableau de bord (fiches en retard, demandes de réouverture), la cloche, la
   réouverture d'une fiche verrouillée, une fiche créée à la place de l'intervenante.
5. **Rendre compte** :
   - les statistiques : participants, moyennes, provenance, âge, assiduité, observations ;
   - l'export Excel ;
   - le pré-bilan Word pour un financeur.
6. **Archiver** le projet, puis le restaurer. **Désactiver** un compte, puis le réactiver.

### Le parcours des intervenant·e·s, sur téléphone
- La connexion « Rester connecté·e ».
- La fiche du jour en six étapes, avec le brouillon conservé d'une étape à l'autre.
- Une soumission incomplète, puis complète.
- La modification d'une fiche rendue.
- Le lieu corrigé depuis l'étape 1, dont un nouveau lieu.
- Une fiche verrouillée et sa demande de réouverture.
- La lecture seule de la fiche d'une collègue.
- Le refus d'une séance qui n'est pas encore terminée.

### Le cahier de recette (`docs/RECETTE.md`)
| Section | Scénarios passés | Résultat |
|---|---|---|
| 2. Connexion et compte | A1 à A8 | tous conformes |
| 3. L'intervenante sur téléphone | B1 à B15 | tous conformes |
| 4. L'administratrice | C1 à C19 | tous conformes |
| 5. Statistiques et pré-bilan | D1 à D9 | tous conformes ; **60 participants**, comme attendu |
| 6. La super-utilisatrice | E1 à E4 | tous conformes |
| 7. Sécurité et données personnelles | F1, F2, F3, F5 | conformes ; F4 (`https://`) se vérifie une fois en ligne |

C15 à C18 ont été faits avec les mails du journal, comme le prévoit le cahier.

### Tests aux limites, par l'API
| Essai | Attendu | Obtenu |
|---|---|---|
| Texte avec `<script>`, `&`, guillemets, emoji, `=SUM(A1)` dans une fiche | enregistré, affiché tel quel | conforme : rien d'interprété à l'écran ; Word et Excel bien formés, le texte y est échappé |
| Temps forts de 201 caractères | refusé | refusé, **message en anglais** (AF1) |
| 200 emoji (caractères multioctets) | accepté | conforme |
| Présents négatifs, décimaux | refusés | refusés, **message en anglais** pour le négatif (AF1) |
| 190 présents pour un groupe de 20 | à discuter | **accepté** (AF6) |
| Note 6 sur 5, 11 exercices, groupe d'un autre projet | refusés | refusés, **messages en anglais** sauf le dernier (AF1) |
| Séance qui finit avant de commencer, de 0 minute, le 31 février, sans groupe | refusées | conformes |
| Séance en 2099 | refusée | **acceptée** (AF8) |
| Nom de projet de 151 caractères | refusé | refusé, **message en anglais** (AF1) |
| Code postal « 1300A », lieu en double | refusés | conformes |
| Léa crée une séance, lit les statistiques | 403 | conformes |
| Statistiques sur une période inversée | refusées | conforme |

L'heure de Marseille est bien appliquée, et non celle du serveur (UTC) : fin de séance, échéances,
« aujourd'hui », date des exports.

## 2. Anomalies

Les anomalies sont classées par gravité :
- **bloquante** : empêche un usage prévu ;
- **majeure** : un usage prévu donne un résultat faux ou incomplet ;
- **mineure** : gêne, sans fausser le résultat.

| | Anomalie | Gravité | Suite |
|---|---|---|---|
| AF1 | Plusieurs messages d'erreur s'affichent en anglais : « The présents field must be at least 0. », note hors de 1 à 5, texte trop long, plus de 10 exercices, nom de projet trop long | mineure | corrigée : #235, PR #239 |
| AF2 | Depuis l'intégration du frontend dans `backend/public` (a1bb799), une adresse d'API inconnue répond 200 avec la page de l'application au lieu d'un 404 ; la CI de `premain` est rouge (14 tests). La page servie reçoit aussi la politique de sécurité de l'API (`default-src 'none'`), qui bloque ses scripts : elle resterait blanche. Le build commité appelle `http://localhost:8080/api` | bloquante (CI, et page blanche une fois en ligne) | correctif proposé à Enzo : #237, PR #238 |
| AF3 | À la création d'un projet, seul le nom des groupes est saisi : ni tranche d'âge, ni effectif prévu (la ville est celle du projet). Ces champs ne se modifient pas ensuite. Les statistiques et le pré-bilan affichent alors « Âge : non renseigné » et une assiduité sans pourcentage | majeure | corrigée à la création : #244, PR #245 ; la modification après coup est à décider |
| AF4 | Pré-bilan : quand tous les champs d'une fiche sont des réponses vides (« Rien », « RAS »), le titre de la séance est imprimé sans rien dessous | mineure | corrigée : #240, PR #242 |
| AF5 | Une séance d'un projet archivé peut encore être annulée (la création, la modification et le rétablissement sont bien refusés) | mineure | corrigée : #241, PR #243 |
| AF6 | Aucun contrôle de vraisemblance sur les présents : une faute de frappe (190 au lieu de 19) gonfle les statistiques et le pré-bilan sans avertissement | majeure | **décision de la cliente** |
| AF7 | Un intervenant ponctuel ne peut pas être ajouté à un projet : la séance doit avoir pour intervenant·e un membre du projet, et seul·e l'intervenant·e principal·e en est membre. L'écran annonce pourtant que les intervenant·e·s « s'ajoutent en remplissant une fiche » | majeure | **décision de la cliente** |
| AF8 | Une séance peut être programmée hors des dates du projet (jusqu'en 2099). À l'inverse, une séance passée depuis plus de 10 jours ne peut plus recevoir de fiche : on ne peut pas saisir l'historique d'un projet commencé avant l'application, et ces séances comptent comme « en retard » pour toujours | majeure | **décision de la cliente** |
| AF9 | Aucun import de données (projets, groupes, séances, comptes) : tout se saisit à la main | évolution | **décision de la cliente** |
| AF10 | Une invitation remplacée par une nouvelle affiche « Ce lien a expiré » au lieu de « Ce lien a été remplacé » | mineure | à noter |
| AF11 | « Prochaines séances » du tableau de bord montre aussi les séances du jour déjà terminées | mineure | à noter |

## 3. Questions pour la cliente

1. **Présents (AF6)** : faut-il refuser un nombre de présents supérieur à l'effectif prévu du groupe,
   ou seulement prévenir (« 190 présents pour un groupe de 20 : est-ce bien ça ? ») ?
2. **Intervenant·e ponctuel·le (AF7)** : faut-il pouvoir choisir n'importe quel·le intervenant·e
   actif·ve pour une séance, en l'ajoutant au projet à ce moment ? Ou ajouter des membres depuis la
   page du projet ?
3. **Dates (AF8)** :
   - faut-il refuser une séance hors des dates du projet ?
   - pour saisir l'historique, faut-il que l'administratrice puisse créer une fiche après 10 jours,
     ou que ces séances ne comptent pas comme en retard ?
4. **Import (AF9)** : y a-t-il un fichier existant (tableur des projets, des classes) à reprendre ?
   Si oui, sous quelle forme ?

## 4. Refaire l'audit

1. Lancer l'application sur une base de test (jamais la base `klap`).
2. Recréer les données : `php artisan klap:demo --force`.
3. Suivre `docs/RECETTE.md`, section par section, en notant chaque écart avec la fiche d'anomalie de
   la section 8.

Pour les tests aux limites, envoyer les requêtes du tableau ci-dessus à l'API avec les comptes de
démonstration.
