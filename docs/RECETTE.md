# Cahier de recette de KLAP

La recette est la dernière étape avant la livraison. La cliente vérifie elle-même que l'application fait
ce qui était demandé, scénario par scénario, puis elle signe le procès-verbal à la fin du document.

Les résultats attendus décrivent le comportement réel de l'application. Le détail se trouve dans le
[guide d'utilisation](GUIDE_UTILISATION.md).

---

## 1. Préparer la recette

| | |
|---|---|
| **Version testée** | branche `main`, commit : `________` |
| **Adresse** | `______________________________` (mise en ligne) ou version locale |
| **Date** | `____ / ____ / 2026` |
| **Présents** | `______________________________` |

### Les données

Sur la version locale ou une copie de test, la commande `php artisan klap:demo` recrée les données de
démonstration (voir [DEMO.md](DEMO.md)). Elle les date du jour où on la lance : **la lancer le jour
même, juste avant la recette**. Lancée la veille, la « séance du jour » de Léa serait celle d'hier.

**Elle efface toute la base : ne jamais la lancer sur la version en ligne.** En ligne, on crée un
projet de test, puis on l'archive à la fin de la recette.

Les scénarios se déroulent **dans l'ordre du document** : certains s'appuient sur les précédents
(la fiche soumise en B9 fait changer les statistiques en D3, par exemple).

### Les comptes

Mot de passe commun sur les données de démonstration : `Klap2026!`

| Rôle | Compte de démonstration | Sert à | À tester sur |
|---|---|---|---|
| Administratrice | `mariette.travard@klap.demo` | sections 4 et 5 | ordinateur |
| Super-utilisatrice | `emilie.garetier@klap.demo` | section 6 | ordinateur |
| Intervenante | `lea.fournier@klap.demo` | section 3 | **téléphone** |
| Intervenante | `nina.roussel@klap.demo` | A3 à A6 et B12 | ordinateur ou téléphone |

Les scénarios A3 à A6 changent le mot de passe et déconnectent le compte : ils se font avec le compte
de Nina, pour ne pas gêner Léa sur son téléphone.

### Les mails

L'invitation, le mot de passe oublié et l'activation passent par un mail : A3, A4, C15 et C16, ainsi
que C17 et C18, qui portent sur le compte invité.

- **En ligne** : il faut que l'envoi des mails soit configuré.
- **Sur la version locale** : le mail n'est pas envoyé, il est écrit dans le journal du serveur
  (`storage/logs/laravel.log`). On y copie le lien.

Sans mail, C17 et C18 peuvent se faire sur le compte de Hugo (`hugo.martin@klap.demo`).

### Comment noter

Pour chaque scénario, cocher **OK** si le résultat obtenu correspond au résultat attendu, **KO** sinon,
et écrire ce qui a été vu dans « Remarque ». Un KO donne lieu à une fiche d'anomalie (section 8).

---

## 2. Connexion et compte

| N° | Action | Résultat attendu | OK | KO | Remarque |
|---|---|---|---|---|---|
| A1 | Se connecter avec un mot de passe faux | Message d'erreur ; on reste sur la page de connexion | ☐ | ☐ | |
| A2 | Sur ordinateur, se connecter **sans** cocher « Rester connecté·e », puis fermer toutes les fenêtres du navigateur (sans reprise des onglets) et le rouvrir | Il faut se reconnecter | ☐ | ☐ | |
| A3 | Compte de Nina : « Mot de passe oublié ? », saisir son adresse | Un mail arrive ; son lien ouvre la page du nouveau mot de passe | ☐ | ☐ | |
| A4 | Choisir le nouveau mot de passe ; puis rouvrir le même lien, saisir un autre mot de passe valide et valider | « Ce lien n'est plus valable : demandez-en un nouveau. », avec le lien « Demander un nouveau lien » | ☐ | ☐ | |
| A5 | Compte de Nina, « Mon profil » › « Changer mon mot de passe » : saisir un mot de passe sans chiffre ni caractère spécial | Les règles se cochent au fil de la saisie (8 caractères, majuscule, minuscule, chiffre, caractère spécial) ; à la validation : « Le mot de passe ne respecte pas toutes les règles. » | ☐ | ☐ | |
| A6 | Même écran, avec un mot de passe valide | Le mot de passe change ; les autres appareils sont déconnectés | ☐ | ☐ | |
| A7 | Se tromper de mot de passe 6 fois en moins d'une minute | « Trop de tentatives. Réessayez dans N secondes. » | ☐ | ☐ | |
| A8 | Ouvrir une adresse qui n'existe pas (`/nimporte-quoi`) | « Page introuvable », avec un lien vers l'accueil | ☐ | ☐ | |

---

## 3. L'intervenante, sur téléphone (compte de Léa)

| N° | Action | Résultat attendu | OK | KO | Remarque |
|---|---|---|---|---|---|
| B1 | Se connecter en cochant « Rester connecté·e », fermer le navigateur, le rouvrir | On est toujours connectée | ☐ | ☐ | |
| B2 | Regarder le tableau de bord | « Fiches à remplir », les plus anciennes en premier, chacune avec sa date limite ; la séance du jour est marquée « Aujourd'hui » | ☐ | ☐ | |
| B3 | Regarder « Prochaines séances », puis « Tout le planning » | Ses séances à venir, puis le planning | ☐ | ☐ | |
| B4 | Ouvrir le menu | Tableau de bord, Projets, Planning ; **pas** de Statistiques, Pré-bilan ni Gestion | ☐ | ☐ | |
| B5 | « Remplir la fiche » de la séance du jour | La fiche s'ouvre à l'étape 1, Généralités : rappel de la séance, avec le lieu dans un menu. En haut, le rappel « Ne citez pas d'élève par son nom » | ☐ | ☐ | |
| B6 | Remplir l'étape 2, passer à l'étape 3, la remplir, passer à l'étape 4 ; quitter, puis revenir par « Reprendre la fiche » | Les étapes 2 et 3 sont conservées (le brouillon s'enregistre à chaque changement d'étape, ou avec « Enregistrer le brouillon ») | ☐ | ☐ | |
| B7 | À l'étape 3, choisir « Fait » pour l'ajustement | Le champ « Quel ajustement, et pourquoi ? » apparaît | ☐ | ☐ | |
| B8 | Laisser des champs obligatoires vides et toucher « Soumettre la fiche » | « La fiche n'est pas complète », avec les champs et les étapes à compléter | ☐ | ☐ | |
| B9 | Tout compléter, avec **19 présents** chez les Terminales, et soumettre | La fiche est soumise | ☐ | ☐ | |
| B10 | Modifier un champ de cette fiche, puis « Enregistrer les modifications » | La modification est enregistrée (possible jusqu'au 10ᵉ jour après la séance) | ☐ | ☐ | |
| B11 | Dans « Dernières fiches rendues », ouvrir la fiche marquée « verrouillée » (séance d'il y a 13 jours) | Elle ne se modifie plus ; le bouton « Demander la réouverture » est proposé | ☐ | ☐ | |
| B12 | Toucher « Demander la réouverture » | La demande est envoyée ; l'administratrice est prévenue (voir C3) | ☐ | ☐ | |
| B13 | Faire tout le parcours B5 à B9 sur téléphone | Tout est lisible et utilisable sans zoomer ; rien ne dépasse de l'écran | ☐ | ☐ | |
| B14 | **Compte de Nina** : ouvrir une fiche de Léa (projet Arts Danse au Lycée Charles) | « Fiche de Léa Fournier, en lecture seule. » ; les champs ne se modifient pas, le lieu n'est pas un menu | ☐ | ☐ | |
| B15 | **Compte de Léa** : fiche du jour, étape 1, choisir « Nouveau lieu… », le créer (nom, code postal, ville), passer à l'étape 2 ; puis ouvrir le planning | Le nouveau lieu est enregistré avec la fiche ; le planning l'affiche pour la séance | ☐ | ☐ | |

---

## 4. L'administratrice (compte de Mariette)

### Suivi et notifications

| N° | Action | Résultat attendu | OK | KO | Remarque |
|---|---|---|---|---|---|
| C1 | Après B9, changer de page (ou recharger), puis ouvrir la cloche | En tête de liste : « Léa Fournier a rempli la fiche de la séance du … : Arts Danse au Lycée Charles (Terminales spécialité danse). » | ☐ | ☐ | |
| C2 | « Tout marquer comme lu » | La pastille disparaît | ☐ | ☐ | |
| C3 | Après B12, ouvrir le tableau de bord | « Demandes de réouverture » compte la demande de Léa | ☐ | ☐ | |
| C4 | Ouvrir la fiche concernée, « Rouvrir la fiche » | La fiche redevient modifiable pour 10 jours | ☐ | ☐ | |
| C5 | Tableau de bord : la carte « Fiches en retard » | Un nombre de fiches en retard, avec un lien vers les statistiques | ☐ | ☐ | |
| C6 | Planning : séance d'il y a 3 jours (Premières spécialité danse), « Créer la fiche » ; la remplir avec **au plus 19 présents**, et la soumettre | L'écran demande « Créer la fiche de cette séance pour Léa Fournier ? » ; la fiche est enregistrée au nom de Léa ; Emilie reçoit « Mariette Travard a rempli la fiche… » | ☐ | ☐ | |

### Projets, lieux et planning

| N° | Action | Résultat attendu | OK | KO | Remarque |
|---|---|---|---|---|---|
| C7 | Gestion › Projets : créer un projet avec deux sous-projets (groupes), un financeur, et **Léa** comme intervenante principale | Le projet apparaît dans Projets, Gestion › Projets, et dans les listes des Statistiques et du Pré-bilan | ☐ | ☐ | |
| C8 | Modifier ce projet (par exemple ses dates ou le nombre de séances prévues) | La modification est enregistrée | ☐ | ☐ | |
| C9 | Créer un nouveau lieu (nom, code postal, ville) | Le lieu est proposé dans la liste des lieux | ☐ | ☐ | |
| C10 | Planning : programmer une séance de ce projet, **un autre jour qu'aujourd'hui**, pour un groupe, avec Léa | La séance apparaît au planning de Léa | ☐ | ☐ | |
| C11 | Programmer une 2ᵉ séance qui chevauche la 1ʳᵉ, avec Léa | Refusé. Une séance accolée (12 h-14 h après 10 h-12 h) est acceptée | ☐ | ☐ | |
| C12 | Programmer pour Léa une séance **aujourd'hui, qui finit plus tard dans la journée**. Sur le téléphone de Léa, la remplir entièrement et soumettre | Refusé : à l'étape 1, « La séance n'est pas encore terminée : la fiche se soumet après. » (le bandeau du haut dit aussi « La fiche n'est pas complète ») | ☐ | ☐ | |
| C13 | Modifier, puis annuler une séance sans fiche ; puis « Rétablir » | Chaque action est prise en compte ; la séance rétablie revient au planning | ☐ | ☐ | |
| C14 | Archiver le projet de test (en retapant son nom exact) ; puis cocher « Afficher les projets archivés », ouvrir le projet, « Restaurer », puis « Restaurer le projet » | Le projet quitte la liste, ses chiffres restent dans les statistiques ; après restauration, il revient | ☐ | ☐ | |

### Comptes

| N° | Action | Résultat attendu | OK | KO | Remarque |
|---|---|---|---|---|---|
| C15 | Gestion › Utilisateurs : inviter une adresse de test comme intervenant·e | Un mail d'invitation arrive ; le lien permet de choisir un mot de passe | ☐ | ☐ | |
| C16 | Une fois le compte activé, ouvrir la cloche | « … a activé son compte » | ☐ | ☐ | |
| C17 | Désactiver ce compte, puis essayer de s'y connecter | « Ce compte a été désactivé. Contactez l'administratrice. » | ☐ | ☐ | |
| C18 | Réactiver le compte | La connexion fonctionne de nouveau | ☐ | ☐ | |
| C19 | Chercher, dans la liste, le bouton « Désactiver » sur sa propre ligne | Il n'y en a pas : on ne peut pas désactiver son propre compte | ☐ | ☐ | |

---

## 5. Statistiques et pré-bilan (administratrice)

| N° | Action | Résultat attendu | OK | KO | Remarque |
|---|---|---|---|---|---|
| D1 | Statistiques : projet « Arts Danse au Lycée Charles », « Appliquer » | Participants, séances documentées, fiches en retard, moyennes, provenance, âge, assiduité ; « Ce que notent les intervenant·e·s » : sensations (bleu), interaction (ambre), évolution. Sur les données de démonstration, avant B9 et C6 : 3 fiches ; Timidité 1, Adhésion 1, Enthousiasme 1 ; Écoute 2, Entraide 2, Conflit 0 ; Première séance 1, Amélioration 2 | ☐ | ☐ | |
| D2 | Vérifier le nombre de participants à la main | Le plus grand nombre de présents de chaque groupe, additionnés : une classe venue à plusieurs séances n'est comptée qu'une fois | ☐ | ☐ | |
| D3 | Sur les données de démonstration, avec B9 (19 Terminales) et C6 (au plus 19 Premières) | **60** participants (18 + 19 + 22 avant la fiche de Léa, soit 59 ; 19 Terminales le portent à 60) | ☐ | ☐ | |
| D4 | Choisir une période | Seules les séances de la période sont comptées | ☐ | ☐ | |
| D5 | Pré-bilan : projet, financeur « DRAC Provence-Alpes-Côte d'Azur », « Télécharger le pré-bilan » | « Pré-bilan téléchargé : pre-bilan-….docx » ; le fichier s'ouvre dans Word ou LibreOffice | ☐ | ☐ | |
| D6 | Relire le document | Pour la même période, mêmes chiffres que l'écran Statistiques ; groupes, provenance, âge ; temps forts et notes complémentaires des fiches rendues (les « Rien » sont écartés) ; tableaux dans la largeur de la page | ☐ | ☐ | |
| D7 | Chercher un nom d'élève, le bilan de l'intervenant·e, « À reprendre » | Rien de tout cela dans le document (ces champs restent internes) | ☐ | ☐ | |
| D8 | Depuis la page d'un projet, bouton « Pré-bilan » | Le projet est déjà sélectionné | ☐ | ☐ | |

---

## 6. La super-utilisatrice (compte d'Emilie)

| N° | Action | Résultat attendu | OK | KO | Remarque |
|---|---|---|---|---|---|
| E1 | Se connecter | Tous les projets et toutes les fiches ; menus Statistiques et Pré-bilan ; **pas** de menu Gestion | ☐ | ☐ | |
| E2 | Ouvrir une fiche verrouillée | Pas de bouton « Rouvrir la fiche » (réservé à l'administratrice) | ☐ | ☐ | |
| E3 | Après B9, ouvrir la cloche | La notification de la fiche de Léa y est aussi | ☐ | ☐ | |
| E4 | Modifier une fiche rendue il y a moins de 10 jours | La modification est enregistrée (elle peut remplir ou corriger une fiche à la place d'une intervenante) | ☐ | ☐ | |

---

## 7. Sécurité et données personnelles

| N° | Action | Résultat attendu | OK | KO | Remarque |
|---|---|---|---|---|---|
| F1 | Compte de Léa : ouvrir `/projets/2`, un projet où elle n'intervient pas | « Projet introuvable », puis « Ce projet n'existe pas ou ne vous est pas accessible. » | ☐ | ☐ | |
| F2 | Compte de Léa : ouvrir `/gestion/utilisateurs` | Retour au tableau de bord, sans la page de gestion | ☐ | ☐ | |
| F3 | Se déconnecter, puis revenir en arrière dans le navigateur | Les pages ne s'affichent plus : retour à la connexion | ☐ | ☐ | |
| F4 | En ligne : regarder l'adresse du site | `https://`, avec le cadenas | ☐ | ☐ | |
| F5 | Parcourir l'application | Aucune donnée d'élève n'est demandée (seulement des nombres de présents) | ☐ | ☐ | |

L'audit de sécurité complet, avec les outils utilisés, est dans [AUDIT_SECURITE.md](AUDIT_SECURITE.md).

---

## 8. Fiche d'anomalie

À recopier pour chaque KO.

| | |
|---|---|
| **N° du scénario** | |
| **Compte, appareil, navigateur** | |
| **Ce qui était attendu** | |
| **Ce qui s'est passé** (capture d'écran si possible) | |
| **Gravité** | ☐ bloquante (empêche le travail) ☐ gênante (contournable) ☐ mineure (affichage, texte) |
| **Suite donnée par l'équipe** | |

---

## 9. Procès-verbal de recette

| | Nombre |
|---|---|
| Scénarios déroulés | `____` / 59 |
| OK | `____` |
| KO bloquants | `____` |
| KO gênants ou mineurs | `____` |

**Décision de la cliente** :

- ☐ **Recette validée** : l'application est acceptée en l'état.
- ☐ **Validée avec réserves** : l'application est acceptée ; les anomalies listées ci-dessous seront
  corrigées avant le `____ / ____ / 2026`.
- ☐ **Refusée** : des anomalies bloquantes empêchent la mise en service. Une nouvelle recette aura lieu
  après correction.

**Réserves** :

```
.
.
.
```

| Pour KLAP Maison pour la danse | Pour l'équipe projet |
|---|---|
| Mariette Travard, responsable des publics | Nom : |
| Date : | Date : |
| Signature : | Signature : |
