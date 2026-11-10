# Préparer la démonstration

## 1. Les données du jour

Les données de démonstration décrivent la situation au 21/09/2026. La commande suivante les recrée
et les **date du jour de la démo**. Les états restent les mêmes : des fiches rendues, d'autres
verrouillées, un brouillon, une fiche rouverte à la demande de son autrice (aucune demande en
attente). Elle ajoute aussi une séance de
Léa Fournier le jour même, sans fiche, pour la remplir en direct.

```bash
docker compose exec backend php artisan klap:demo                  # jour de la démo : aujourd'hui
docker compose exec backend php artisan klap:demo --date=2026-11-19
```

**La base entière est effacée** : la commande demande une confirmation (ou `--force` pour l'éviter).
À lancer **juste avant la démo**, puis de nouveau entre deux répétitions.

Une fiche ne se soumet qu'**après la fin de la séance**. La séance du jour de Léa se termine donc au
quart d'heure qui précède le lancement de la commande (de 10 h à 12 h si on la lance l'après-midi).
Si la démo a lieu le matin, relancer la commande peu avant, sinon Léa ne pourra pas soumettre sa fiche.

## 2. Les comptes

Mot de passe commun : `Klap2026!`

| Qui | Adresse | Écran |
|---|---|---|
| Mariette Travard (administratrice) | `mariette.travard@klap.demo` | Ordinateur |
| Léa Fournier (intervenante) | `lea.fournier@klap.demo` | Téléphone |
| Emilie Garetier (super-utilisatrice) | `emilie.garetier@klap.demo` | En réserve |

## 3. Le parcours : du terrain au financeur

1. **Téléphone, Léa** : dès la connexion, le tableau de bord montre « Fiches à remplir », avec leur
   échéance. La séance du jour (Arts Danse au Lycée Charles, Terminales spécialité danse) est la dernière
   de la liste, marquée « Aujourd'hui » : les plus anciennes, donc les plus urgentes, viennent d'abord.
   Elle touche « Remplir la fiche », remplit les six étapes et soumet.
2. **Ordinateur, Mariette** : la cloche s'allume, avec le message « Léa Fournier a rempli la fiche de la séance… ».
3. **Statistiques** : projet Arts Danse au Lycée Charles, puis « Appliquer ». Le nombre de participants suit
   la règle de la cliente : le maximum par groupe, puis la somme. Il vaut 59 avant la fiche du jour. Si Léa
   saisit **plus de 18 présents** chez les Terminales (19 par exemple), il passe à 60 sous les yeux du jury ;
   avec 18 ou moins, il ne bouge pas (c'est la règle).
4. **Pré-bilan** : projet Arts Danse au Lycée Charles, financeur « DRAC Provence-Alpes-Côte d'Azur »,
   puis télécharger. Le Word contient les chiffres et les temps forts des fiches, y compris celle que
   Léa vient de remplir. On y arrive par le menu « Pré-bilan », ou par le bouton « Pré-bilan » de la
   page du projet, qui le présélectionne. Il reste à choisir le financeur, puis à télécharger.

## 4. Plan B

- La vidéo enregistrée du même parcours.
- Une version locale prête sur un portable : `docker compose up -d`, puis `klap:demo`.
