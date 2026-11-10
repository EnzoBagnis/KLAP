# Qualité : ce qui est vérifié, et comment le refaire

Ce document rassemble les vérifications faites sur l'application, avec leurs résultats et la façon
de les reproduire. Les chiffres datent du 9 novembre 2026, branche `premain`.

## 1. Intégration continue (à chaque push)

`.github/workflows` vérifie à chaque push :
- **Backend** :
  - migrations et données de démonstration ;
  - réversibilité des migrations ;
  - tests sur SQLite **et** sur MariaDB, car les contraintes CHECK ne sont vérifiées que par MariaDB ;
  - `composer audit`.
- **Frontend** : tests, `npm run build`, `npm audit`.

Rien n'est fusionné dans `premain` sans CI verte.

## 2. Tests automatiques

| | Fichiers | Tests | Résultat |
|---|---|---|---|
| Backend (PHPUnit) | 39 | 512, dont 13 propres à MariaDB | tous passent sur SQLite et MariaDB |
| Frontend (Vitest) | 22 | 316 | tous passent |

Les tests backend passent par l'API, comme le ferait le frontend :
- droits de chaque rôle ;
- verrouillage à J+10, réouverture ;
- règle des participants (le chiffre de référence de la cliente, 59, est vérifié sur les données de démo) ;
- pré-bilan Word, relu avec phpword ;
- invitations, mot de passe oublié ;
- limites de connexion.

```bash
docker compose exec backend php artisan test                     # SQLite
docker compose exec -e DB_CONNECTION=mysql -e DB_DATABASE=klap_test backend php artisan test
docker run --rm -v "$PWD/frontend":/app -w /app node:24-alpine npx vitest run
```

## 3. Parcours vérifiés dans l'application

Sur les données de `php artisan klap:demo` (voir `docs/DEMO.md`), sur un écran de téléphone (390 px)
et d'ordinateur :
- **Démo de bout en bout** : fiche remplie sur téléphone, cloche, statistiques (59 puis 60 participants), pré-bilan Word.
- **Invitation et activation** : règles du mot de passe, lien déjà utilisé ou inventé.
- **Mot de passe oublié** : demande, mail, nouveau mot de passe, lien à usage unique.
- **Demande de réouverture, puis réouverture.**
- **Projet** : modifier, archiver (nom exact demandé), restaurer.
- **Programmer une séance** : lieu par défaut, refus des séances simultanées.
- **Modifier, annuler, rétablir une séance** (#180).
- **Mon profil** : changer son mot de passe.
- **Droits vus par Emilie (super-utilisatrice) et Hugo (intervenant).**
- **Statistiques** : filtres de période, graphiques (#185).
- **Tableau de bord** (#72) et **pré-bilan** téléchargé depuis l'écran (#144).

Chaque défaut trouvé a fait l'objet d'une issue et d'une correction : #163, #166, #168, #170, #175, #178, #182.

## 4. Accessibilité

Audit **axe-core 4.10** (règles WCAG 2 A et AA, bonnes pratiques) sur tous les écrans :
- côté administratrice, sur ordinateur ;
- côté intervenante, sur téléphone (dont les six étapes de la fiche et le menu mobile).

**Aucune violation.** La seule trouvée, des repères en double sur les statistiques, a été corrigée (#170).

Pour le refaire : ouvrir un écran, puis dans la console du navigateur charger
`https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.2/axe.min.js` et lancer `axe.run()`.

## 5. Sécurité

- **Droits vérifiés de l'extérieur** : sur les données de démo, un intervenant (Hugo) a tenté de lire
  et de modifier toutes les fiches et tous les projets par leur numéro, de rouvrir, d'archiver, de
  télécharger un pré-bilan et d'ouvrir la gestion.
  - Il lit exactement ce que ses droits permettent.
  - Toutes ses écritures sont refusées.
- **Comptes** :
  - mots de passe hachés (bcrypt) ; jetons de connexion stockés hachés, valables 30 jours, refusés dès que le compte est désactivé ;
  - limite des essais de connexion par compte et par IP ;
  - mot de passe oublié sans fuite : même réponse et même temps de réponse, mesurés (environ 1,05 s), que l'adresse existe ou non.
- **Dépendances** : `composer audit` et `npm audit` ne trouvent aucune faille connue.
- **Audit outillé** (gitleaks, Trivy, Semgrep, OWASP ZAP, tests manuels de l'API) : voir `docs/AUDIT_SECURITE.md`. Aucune faille exploitable ; en-têtes HTTP de l'API ajoutés (#200).
- **Données personnelles** : voir `docs/CONFORMITE_SECURITE_RGPD.md`. Aucun élève n'est enregistré, et le pré-bilan ne contient aucun nom de personne.

## 6. Performances

Nombre de requêtes SQL par écran, sur les données de démo, puis avec deux fois plus de séances et de fiches :
- il reste le même (par exemple 11 requêtes pour la liste des fiches, avec 8 comme avec 16 fiches) : pas de requêtes en rafale ;
- les réponses prennent entre 2 et 55 ms ; la plus lente est la génération du Word.

## 7. Ce qui reste à faire (connu)

- **Mise en ligne** : la liste de contrôle est sur #79 (HTTPS, adresse d'expédition des mails, tâche planifiée, CORS).
- **Recette par la cliente** : les 59 scénarios à dérouler avec elle, et le procès-verbal à signer, sont dans `docs/RECETTE.md`.
- **Questions à la cliente** : durées de conservation, champs du pré-bilan, brouillons visibles par les collègues.
