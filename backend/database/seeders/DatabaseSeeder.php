<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de test pour le dev et les démos.
     * php artisan migrate:fresh --seed  (repart d'une base vide)
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
