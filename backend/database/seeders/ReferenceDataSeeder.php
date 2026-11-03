<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder des données de référence :
 * - Les 3 studios permanents de KLAP (Grand studio, Petit studio, Salle de création)
 * - Les lieux partenaires externes
 * - Les financeurs officiels de KLAP (DRAC, Ville de Marseille, Région Sud, Fondations...)
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Les 3 salles internes de KLAP + lieux extérieurs partenaires
        $locations = [
            [1, 'Petit studio',        'KLAP Maison pour la danse', '5 avenue Rostand', '13003', 'Marseille', true,  1],
            [2, 'Grand studio',        'KLAP Maison pour la danse', '5 avenue Rostand', '13003', 'Marseille', true,  1],
            [3, 'Salle de création',   'KLAP Maison pour la danse', '5 avenue Rostand', '13003', 'Marseille', true,  1],
            [4, 'Gymnase / Studio 2',  'Lycée Saint-Charles',       null,               '13003', 'Marseille', false, 1],
            [5, 'Salle polyvalente',   'École Peyssonnel',          null,               '13003', 'Marseille', false, 6],
            [6, 'Gymnase',             'Collège Édouard Manet',     null,               '13014', 'Marseille', false, 1],
        ];

        foreach ($locations as [$id, $name, $structure, $address, $cp, $city, $isKlap, $by]) {
            DB::table('locations')->insert([
                'id' => $id,
                'name' => $name,
                'structure' => $structure,
                'address' => $address,
                'postal_code' => $cp,
                'city' => $city,
                'is_klap' => $isKlap,
                'created_by' => $by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Financeurs publics et privés
        DB::table('funders')->insert([
            ['id' => 1, 'name' => "DRAC Provence-Alpes-Côte d'Azur",  'type' => 'state',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Ville de Marseille',               'type' => 'local_authority', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Région Sud',                       'type' => 'local_authority', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Département des Bouches-du-Rhône', 'type' => 'local_authority', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Fondation de France (Mécénat)',    'type' => 'foundation',      'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
