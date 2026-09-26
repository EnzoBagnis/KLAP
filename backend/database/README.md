# Base de données

Migrations et seeders Laravel. Testé sur MySQL 8 (comme Alwaysdata) et SQLite.
Il faut MariaDB 10.2+ ou MySQL 8.0.16+ pour que les `CHECK` soient appliqués (ils sont ignorés sur SQLite).

En local avec Docker :

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

Sur Alwaysdata (seulement la première fois pour les données de test) :

```bash
php artisan migrate --force
php artisan db:seed --force
```

Comptes de test : `mariette.travard@klap.demo` (admin), `lea.fournier@klap.demo` (intervenante)... Mot de passe `Klap2026!`

Quelques choix :

- la table `users` de Laravel est gardée, on lui ajoute `role`, `phone`, `is_active`, `last_login_at`
- les pivots suivent la convention Laravel (`funder_project`, `project_user`, `logbook_entry_user`, `scheduled_session_sub_project`)
- `scheduled_sessions` et `klap_notifications` pour ne pas tomber sur les tables `sessions` / `notifications` de Laravel
- les stats sont des vues SQL (`v_project_participants`, `v_project_indicators`, `v_missing_entries`...)

Reste à faire côté API : minimum 2 exercices à la soumission, calcul de `edit_deadline` (séance + 10 jours) et verrouillage des fiches.
