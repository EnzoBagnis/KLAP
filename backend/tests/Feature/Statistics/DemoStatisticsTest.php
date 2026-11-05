<?php

namespace Tests\Feature\Statistics;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Chiffre de référence du contexte client, sur les données de démonstration. */
class DemoStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_59_participants_au_lycee_charles_et_non_un_cumul(): void
    {
        // Dans la transaction du test : les autres tests ne voient pas ces données.
        $this->seed();
        $project = DB::table('projects')->where('name', 'like', 'Arts Danse au Lycée Charles%')->first();
        $admin = User::query()->where('email', 'mariette.travard@klap.demo')->firstOrFail();

        $stats = $this->actingAs($admin)->getJson("/api/statistics?project_id={$project->id}")->assertOk()->json('data');

        $this->assertSame(59, $stats['projects'][0]['participants']);
        $this->assertSame(59, (int) DB::table('v_project_participants')->where('project_id', $project->id)->value('participants'));
    }
}
