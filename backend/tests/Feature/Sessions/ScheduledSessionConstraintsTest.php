<?php

namespace Tests\Feature\Sessions;

use App\Models\Location;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Contraintes de scheduled_sessions vérifiées par la base (MariaDB ou MySQL seulement). */
#[Group('mariadb')]
class ScheduledSessionConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Contraintes CHECK et clés composites non vérifiées par SQLite.');
        }
    }

    private function sessionRow(int $projectId, array $overrides = []): array
    {
        return array_merge([
            'project_id' => $projectId,
            'session_date' => '2026-10-05',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'location_id' => Location::factory()->create()->id,
            'referent_id' => User::factory()->create()->id,
            'session_type' => 'practice_workshop',
            'status' => 'planned',
            'created_by' => User::factory()->create()->id,
        ], $overrides);
    }

    public function test_la_base_refuse_une_fin_avant_le_debut(): void
    {
        $row = $this->sessionRow(Project::factory()->create()->id, ['end_time' => '09:00']);

        $this->expectException(QueryException::class);
        DB::table('scheduled_sessions')->insert($row);
    }

    public function test_la_base_refuse_le_type_autre_sans_precision(): void
    {
        $row = $this->sessionRow(Project::factory()->create()->id, ['session_type' => 'other']);

        $this->expectException(QueryException::class);
        DB::table('scheduled_sessions')->insert($row);
    }

    public function test_la_base_refuse_un_groupe_d_un_autre_projet(): void
    {
        $project = Project::factory()->create();
        $sessionId = DB::table('scheduled_sessions')->insertGetId($this->sessionRow($project->id));
        $foreignGroup = SubProject::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('scheduled_session_sub_project')->insert([
            'scheduled_session_id' => $sessionId, 'sub_project_id' => $foreignGroup->id, 'project_id' => $project->id,
        ]);
    }

    public function test_la_base_accepte_une_seance_valide(): void
    {
        $project = Project::factory()->create();
        $group = SubProject::factory()->for($project)->create();
        $sessionId = DB::table('scheduled_sessions')->insertGetId($this->sessionRow($project->id));
        DB::table('scheduled_session_sub_project')->insert([
            'scheduled_session_id' => $sessionId, 'sub_project_id' => $group->id, 'project_id' => $project->id,
        ]);

        $this->assertDatabaseHas('scheduled_session_sub_project', ['scheduled_session_id' => $sessionId]);
    }
}
