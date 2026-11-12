<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Les listes de référence : lieux, financeurs et types de projet.
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * On remplit les tables.
     */
    public function run(): void
    {
        $now = now();

        // Les 3 salles de KLAP (noms donnés par la cliente), plus 2 lieux extérieurs.
        $locations = [
            [1, 'Petit studio',        '5 avenue Rostand', '13003', 'Marseille', true,  1],
            [2, 'Grand studio',        '5 avenue Rostand', '13003', 'Marseille', true,  1],
            [3, 'Salle de création',   '5 avenue Rostand', '13003', 'Marseille', true,  1],
            [4, 'Lycée Saint-Charles', null,               '13003', 'Marseille', false, 1],
            [5, 'Salle polyvalente',   null,               '13400', 'Aubagne',   false, 6],
        ];
        foreach ($locations as [$id, $name, $address, $cp, $city, $isKlap, $by]) {
            DB::table('locations')->insert([
                'id' => $id, 'name' => $name, 'address' => $address, 'postal_code' => $cp, 'city' => $city,
                'is_klap' => $isKlap, 'created_by' => $by, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Les types de départ : ceux de la maquette et du cahier des charges. L'administratrice
        // peut en ajouter, les renommer ou les désactiver.
        foreach ([
            [1, 'Partenariat lycée'],
            [2, 'Parcours collège'],
            [3, 'Projet de création scolaire'],
            [4, 'Projet annuel école'],
            [5, 'Projet de création en territoire'],
        ] as [$id, $name]) {
            DB::table('project_types')->insert(['id' => $id, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('funders')->insert([
            ['id' => 1, 'name' => "DRAC Provence-Alpes-Côte d'Azur",  'type' => 'state',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Ville de Marseille',               'type' => 'local_authority', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Région Sud',                       'type' => 'local_authority', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Département des Bouches-du-Rhône', 'type' => 'local_authority', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Fondation (démo)',                 'type' => 'foundation',      'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
