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

    /** Sensations, évolution et interaction des fiches rendues (le brouillon n° 4 n'est pas compté). */
    public function test_ce_que_notent_les_intervenants_sur_les_donnees_de_demo(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'mariette.travard@klap.demo')->firstOrFail();

        $all = $this->actingAs($admin)->getJson('/api/statistics')->assertOk()->json('data.observations');

        $this->assertSame(7, $all['entries']);
        $this->assertSame(
            ['well_being' => 1, 'discomfort' => 0, 'tension' => 1, 'buy_in' => 1, 'enthusiasm' => 2, 'shyness' => 1, 'other' => 1],
            array_column($all['feelings'], 'entries', 'key'),
        );
        $this->assertSame(
            ['first_session' => 4, 'improvement' => 3, 'stagnation' => 0, 'regression' => 0],
            array_column($all['progress'], 'entries', 'key'),
        );
        // « conflit, puis entraide » compte pour les deux ; « curiosité, entraide » pour entraide seulement.
        $this->assertSame(['écoute' => 4, 'entraide' => 4, 'conflit' => 1], array_column($all['interaction_keywords'], 'entries', 'key'));

        // Même périmètre que le reste de l'écran : ici, un seul projet.
        $project = DB::table('projects')->where('name', 'like', 'Arts Danse au Lycée Charles%')->value('id');
        $one = $this->actingAs($admin)->getJson("/api/statistics?project_id={$project}")->json('data.observations');
        $this->assertSame(3, $one['entries']);
        $this->assertSame(['écoute' => 2, 'entraide' => 2, 'conflit' => 0], array_column($one['interaction_keywords'], 'entries', 'key'));
    }
}
