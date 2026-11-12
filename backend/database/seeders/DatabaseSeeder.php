<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Notre jeu de données, pour le développement et les démonstrations. Il décrit la situation
 * au 21/09/2026 ; la commande klap:demo le décale au jour de la démo.
 *
 *     php artisan migrate:fresh --seed  (on repart d'une base vide)
 */
class DatabaseSeeder extends Seeder
{
    /**
     * On lance les seeders dans l'ordre des dépendances : les comptes d'abord, les fiches en dernier.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ReferenceDataSeeder::class,
            ProjectSeeder::class,
            PlanningSeeder::class,
            LogbookSeeder::class,
        ]);
    }
}
