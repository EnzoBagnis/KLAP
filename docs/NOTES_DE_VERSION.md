# KLAP 1.0 : notes de version

KLAP remplace le carnet de bord papier. Les intervenant·e·s remplissent une fiche après chaque séance,
souvent sur leur téléphone. L'administratrice suit les projets, et en tire les chiffres et le pré-bilan
destinés aux financeurs.

Le mode d'emploi détaillé est dans le **guide d'utilisation**. La liste des vérifications à faire
avant d'accepter la version est dans le **cahier de recette**.

---

## Ce que contient la version 1.0

### Pour les intervenant·e·s

- **Tableau de bord** : les fiches à remplir, les plus urgentes en premier, chacune avec sa date
  limite ; les prochaines séances ; les dernières fiches rendues.
- **La fiche de séance**, en six étapes, reprises du carnet papier :
  1. généralités (le lieu se corrige ici, ou se crée pour un projet en tournée) ;
  2. objectifs (jamais pré-remplis) ;
  3. la séance : 2 à 10 exercices, ajustement, temps forts ;
  4. les participants : présents par groupe, puis, pour l'ensemble du groupe, trois notes de 1 à 5,
     l'interaction, la sensation et l'évolution ;
  5. les artistes ;
  6. la suite.
- **Brouillon** enregistré à chaque étape, à reprendre plus tard.
- **Délai de 10 jours** : une fiche, rendue ou en brouillon, se modifie jusqu'au 10ᵉ jour après la
  séance. Ensuite elle est verrouillée, et son autrice peut demander à la rouvrir. Une fiche jamais
  commencée ne peut plus être créée après ce délai.
- **Rappel** « Ne citez pas d'élève par son nom » sur chaque fiche en cours de saisie.
- **Les fiches des collègues** du même projet se lisent, sans pouvoir être modifiées.

### Pour l'administratrice

- **Notifications** : la cloche s'allume quand une fiche est rendue, quand une réouverture est
  demandée et quand une personne invitée active son compte. Elle se met à jour toute seule.
- **Projets** : création (groupes, financeurs, lieu, intervenant·e principal·e), modification,
  archivage et restauration. Un projet n'est jamais effacé.
- **Planning** : programmer une séance ; la modifier, l'annuler ou la rétablir tant que sa fiche
  n'est pas commencée. Deux séances qui se chevauchent pour la même personne sont refusées.
- **Comptes** : invitation par mail (pas d'inscription libre), désactivation et réactivation.
- **Réouverture** d'une fiche verrouillée. KLAP garde qui l'a rouverte en dernier, et quand.
- **Remplir la fiche d'une intervenante** qui ne peut pas le faire elle-même (elle doit avoir un
  compte KLAP). La super-utilisatrice le peut aussi.

### Statistiques et pré-bilan (administratrice et super-utilisatrice)

- **Participants** : une classe venue à plusieurs séances n'est comptée qu'une fois (le plus grand
  nombre de présents de chaque groupe, puis la somme des groupes). Ce n'est pas un nombre de
  personnes distinctes : une classe inscrite dans deux projets compte dans chacun.
- **Les autres chiffres** :
  - séances documentées et fiches en retard ;
  - moyennes d'engagement, d'expression et d'autonomie ;
  - provenance (ville et code postal), âge, assiduité ;
  - sensations (en bleu) et interaction (en ambre), aux couleurs que vous avez choisies, et
    évolution.
- **Filtres** par projet et par période.
- **Export Excel** des chiffres affichés.
- **Pré-bilan Word** pour un financeur, prêt à retravailler :
  - **contenu** : les chiffres, les groupes, la provenance, l'âge, les temps forts et les notes
    complémentaires des fiches ;
  - **aucun texte rédigé** par l'application ;
  - **noms d'élèves** : KLAP n'en enregistre aucun, mais les temps forts et les notes
    complémentaires sont repris tels qu'écrits. D'où le rappel sur chaque fiche.

### Sécurité et données personnelles

- **Droits par rôle** : chacun ne voit que ce qui le concerne. Les droits sont contrôlés par
  l'application elle-même, pas seulement par ce qu'affiche l'écran.
- **Aucune donnée d'élève** n'est enregistrée : seulement des nombres de présents.
- **Audit de sécurité** du 9 novembre 2026, avec des outils d'analyse reconnus : aucune faille
  exploitable. Quelques renforcements ont été faits à cette occasion.
- **Mots de passe** : 8 caractères au moins, avec majuscule, minuscule, chiffre et caractère spécial.
- **Connexion** : au-delà de 5 essais en une minute, il faut patienter une minute.

### Qualité

- **Près de 900 tests automatiques**, relancés à chaque modification. Tous ont aussi été passés sur
  le type de base de données de la mise en ligne.
- **Accessibilité** :
  - un contrôle automatique, écran par écran, n'a relevé aucun défaut ;
  - l'application s'utilise entièrement au clavier, avec un lien « Aller au contenu » ;
  - un essai avec un lecteur d'écran reste à faire.

---

## Ce qui reste à décider avec vous

Ces points sont réglés de façon provisoire. Dites-nous si vous voulez les changer.

| Sujet | Aujourd'hui |
|---|---|
| Délai de modification | 10 jours après la séance ; une fiche rouverte est modifiable 10 jours à partir de sa réouverture |
| Demande de réouverture | sans motif ; l'intervenante n'est pas prévenue quand sa fiche est rouverte |
| Sensation | une seule par fiche |
| Brouillons des collègues | visibles par les collègues du même projet |
| Super-utilisatrice | elle reçoit aussi les notifications « fiche remplie » |
| Séance déplacée ou annulée | l'intervenante n'est pas prévenue |
| Lieu d'une séance | corrigé par l'autrice de la fiche ou par l'administration, pas par les autres collègues |
| Couleur de l'interaction | ambre foncé, pour rester lisible |
| Pré-bilan | les mêmes indicateurs pour tous les financeurs |

## Limites connues

- **Comptes** : on ne change pas le rôle d'un compte, et il n'y a pas encore de liste des invitations
  en cours (réinviter une adresse remplace l'invitation précédente).
- **Mots-clés d'interaction** : ils sont repérés dans un texte libre. « Pas de conflit » compte donc
  pour « conflit » : les chiffres donnent une tendance, la relecture des fiches donne le sens.
- **Cloche** : elle se met à jour toutes les 30 secondes, pas à l'instant même.

## Avant l'ouverture aux intervenant·e·s

Trois réglages restent à faire sur le serveur en ligne :
- **l'envoi des mails** (serveur d'envoi et adresse d'expédition). Sans lui, ni les invitations ni
  les mails de mot de passe oublié ne partent ;
- **la connexion sécurisée** (le cadenas dans la barre d'adresse) ;
- **la tâche automatique de chaque nuit**, qui passe au statut « verrouillée » les fiches de plus de
  10 jours.
