<?php

namespace Tests\Feature\Projects;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Contraintes CHECK de la table projects, vérifiées par la base elle-même.
 * SQLite les ignore : ces tests ne tournent que sur MariaDB ou MySQL (étape
 * « Tests contre MariaDB » de la CI, groupe mariadb).
 */
#[Group('mariadb')]
class ProjectConstraintsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Contraintes CHECK ignorées par SQLite.');
        }
    }

    /**
     * Une ligne de projet valide, à insérer directement en base.
     *
     * @param  array<string, mixed>  $overrides  ce qu'on change pour violer une contrainte
     * @return array<string, mixed>
     */
    private function projectRow(array $overrides): array
    {
        return array_merge([
            'name' => 'Projet contrainte',
            'status' => 'active',
            'default_location_id' => Location::factory()->create()->id,
            'created_by' => User::factory()->create()->id,
        ], $overrides);
    }

    public function test_la_base_refuse_une_fin_avant_le_debut(): void
    {
        $row = $this->projectRow(['start_date' => '2027-06-19', 'end_date' => '2026-09-15']);

        $this->expectException(QueryException::class);
        DB::table('projects')->insert($row);
    }

    public function test_la_base_refuse_zero_seance_prevue(): void
    {
        $row = $this->projectRow(['planned_sessions' => 0]);

        $this->expectException(QueryException::class);
        DB::table('projects')->insert($row);
    }

    public function test_la_base_accepte_un_projet_valide(): void
    {
        DB::table('projects')->insert(
            $this->projectRow(['start_date' => '2026-09-15', 'end_date' => '2027-06-19', 'planned_sessions' => 32])
        );

        $this->assertDatabaseHas('projects', ['name' => 'Projet contrainte', 'planned_sessions' => 32]);
    }
}
