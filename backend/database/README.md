# Base de données

MySQL 8 (au moins 8.0.16, sinon les `CHECK` sont ignorés).

- `schema.sql` crée toute la base d'un coup. Il drop les tables avant, donc attention en prod.
- `migrations/` contient les mêmes tables, une par fichier.
- `seeders/InitialDataSeeder.sql` remplit la base avec des données de test (les 5 projets, des séances, des fiches...).

```bash
mysql -u root -p klap < backend/database/schema.sql
mysql -u root -p klap < backend/database/seeders/InitialDataSeeder.sql
```

Comptes de test : `mariette.travard@klap.demo` (admin), `lea.fournier@klap.demo` (intervenante), etc. Mot de passe : `Klap2026!`

## Ordre des migrations

Si on les lance une par une il faut suivre cet ordre à cause des clés étrangères (l'ordre alphabétique ne marche pas) :

users, invitations, password_resets, locations, funders, projects, project_funders, sub_projects, project_members, scheduled_sessions, session_sub_projects, logbook_entries, participant_evaluations, exercises, logbook_entry_artists, custom_fields, custom_field_values, notifications, report_exports, puis les vues (`CreateStatisticsViews.sql`).

## A gérer côté API

La base bloque déjà pas mal de choses (notes entre 1 et 5, 10 exercices max, précision obligatoire si "autre", détail obligatoire si l'ajustement est "fait"...). Par contre il reste à faire dans l'API :

- le minimum de 2 exercices quand on soumet une fiche
- calculer `edit_deadline` (date de la séance + 10 jours) et verrouiller la fiche après
