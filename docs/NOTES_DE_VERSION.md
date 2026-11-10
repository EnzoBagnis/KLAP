# KLAP 1.0 : notes de version

KLAP remplace le carnet de bord papier. Les intervenant·e·s remplissent une fiche après chaque séance,
souvent sur leur téléphone. L'administratrice suit les projets, et en tire les chiffres et le pré-bilan
destinés aux financeurs.

Le mode d'emploi détaillé est dans le [guide d'utilisation](GUIDE_UTILISATION.md). La liste des
vérifications à faire avant d'accepter la version est dans le [cahier de recette](RECETTE.md).

---

## Ce que contient la version 1.0

### Pour les intervenant·e·s

- **Tableau de bord** : les fiches à remplir, les plus urgentes en premier, chacune avec sa date
  limite ; les prochaines séances ; les dernières fiches rendues.
- **La fiche de séance**, en six étapes, reprises du carnet papier :
  1. généralités (le lieu se corrige ici, ou se crée pour un projet en tournée) ;
  2. objectifs (jamais pré-remplis) ;
  3. la séance : 2 à 10 exercices, ajustement, temps forts ;
  4. les participants : présents par groupe, trois notes de 1 à 5, interaction, sensation, évolution ;
  5. les artistes ;
  6. la suite.
- **Brouillon** enregistré à chaque étape, à reprendre plus tard.
- **Délai de 10 jours** pour modifier une fiche rendue. Ensuite elle est verrouillée, et l'on peut
  demander sa réouverture.
- **Rappel** « Ne citez pas d'élève par son nom » sur chaque fiche.
- **Les fiches des collègues** du même projet se lisent, sans pouvoir être modifiées.

### Pour l'administratrice

- **Notifications** : la cloche s'allume quand une fiche est rendue, quand une réouverture est
  demandée et quand une personne invitée active son compte. Elle se met à jour toute seule.
- **Projets** : création (groupes, financeurs, lieu, intervenant·e principal·e), modification,
  archivage et restauration. Un projet n'est jamais effacé.
- **Planning** : programmer, modifier, annuler et rétablir une séance. Deux séances qui se chevauchent
  pour la même personne sont refusées.
- **Comptes** : invitation par mail (pas d'inscription libre), désactivation et réactivation.
- **Réouverture** d'une fiche verrouillée, tracée (qui, quand).
- **Remplir une fiche à la place** d'une intervenante qui n'a pas accès à KLAP.

### Statistiques et pré-bilan (administratrice et super-utilisatrice)

- **Participants comptés sans doublon** : pour chaque groupe, le plus grand nombre de présents sur
  ses séances, puis la somme des groupes.
- **Les autres chiffres** :
  - séances documentées et fiches en retard ;
  - moyennes d'engagement, d'expression et d'autonomie ;
  - provenance (ville et code postal), âge, assiduité ;
  - sensations, interaction et évolution, aux couleurs que vous avez choisies.
- **Filtres** par projet et par période.
- **Export Excel** des chiffres affichés.
- **Pré-bilan Word** pour un financeur, prêt à retravailler : les chiffres, les groupes, les temps
  forts. Aucun texte n'est rédigé par l'application, et aucun nom d'élève n'y figure.

### Sécurité et données personnelles

- **Droits par rôle** : chacun ne voit que ce qui le concerne, et les droits sont vérifiés par le
  serveur, pas seulement par l'écran.
- **Aucune donnée d'élève** n'est enregistrée : seulement des nombres de présents.
- **Audit de sécurité** avec des outils reconnus (gitleaks, Trivy, Semgrep, OWASP ZAP) : aucune faille
  trouvée ([rapport](AUDIT_SECURITE.md)).
- **Mots de passe** : 8 caractères au moins, avec majuscule, minuscule, chiffre et caractère spécial.
  Connexion limitée après plusieurs échecs.

### Qualité

- **Près de 900 tests automatiques**, relancés à chaque modification. Ceux du serveur passent aussi
  sur MariaDB, la base de la mise en ligne.
- **Accessibilité** : audit automatique (axe-core) sans violation. Les écrans sont utilisables au
  clavier et au lecteur d'écran.
- **Sur téléphone** : tous les écrans de l'intervenante ont été vérifiés.

---

## Ce qui reste à décider avec vous

Ces points sont réglés de façon provisoire. Un mot de votre part suffit pour les changer.

| Sujet | Aujourd'hui |
|---|---|
| Délai de modification | 10 jours après la séance ; une fiche rouverte a 10 jours de plus |
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

## Mise en ligne

La mise en ligne se fait sur Alwaysdata (voir [DEPLOIEMENT_ALWAYSDATA.md](DEPLOIEMENT_ALWAYSDATA.md)).
Avant d'ouvrir l'application aux intervenant·e·s, il faut configurer :
- l'adresse d'expédition des mails ;
- le HTTPS ;
- la tâche planifiée qui verrouille les fiches à J+10.

La liste de contrôle complète est dans le [rapport d'audit](AUDIT_SECURITE.md), section 5.
