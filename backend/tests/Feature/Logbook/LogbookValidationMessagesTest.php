<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Messages des règles de taille (max, min, between) : en français, et non les messages anglais
 * par défaut de Laravel (#235). Une intervenante qui tape « -3 » présents lisait
 * « The présents field must be at least 0. ».
 */
class LogbookValidationMessagesTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
    }

    public function test_les_messages_de_la_fiche_sont_en_francais(): void
    {
        $id = $this->createEntry();
        $group = $this->groupIds()[0];

        foreach ([
            [['attendees' => [['sub_project_id' => $group, 'count' => -3]]], 'attendees.0.count', 'Le champ présents doit valoir au moins 0.'],
            [['attendees' => [['sub_project_id' => $group, 'count' => 70000]]], 'attendees.0.count', 'Le champ présents ne doit pas dépasser 65535.'],
            [['evaluation' => ['engagement' => 6]], 'evaluation.engagement', 'La note d’engagement va de 1 à 5.'],
            [['highlights' => str_repeat('a', 201)], 'highlights', 'Le champ temps forts ne doit pas dépasser 200 caractères.'],
            [['exercises' => array_fill(0, 11, 'x')], 'exercises', 'Une fiche compte au plus 10 exercices.'],
        ] as [$payload, $field, $message]) {
            // Clés à points (« attendees.0.count ») : lues telles quelles, pas comme un chemin.
            $errors = $this->patchJson("/api/logbook-entries/{$id}", $payload)->assertUnprocessable()->json('errors');
            $this->assertSame($message, $errors[$field][0] ?? null, $field);
        }
    }

    public function test_les_messages_du_projet_sont_en_francais(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $project = Project::factory()->create();

        $this->patchJson("/api/projects/{$project->id}", ['name' => str_repeat('P', 151)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Le champ nom du projet ne doit pas dépasser 150 caractères.');
        $this->patchJson("/api/projects/{$project->id}", ['planned_sessions' => 0])
            ->assertUnprocessable()
            ->assertJsonPath('errors.planned_sessions.0', fn ($message) => str_contains($message, 'doit valoir au moins 1'));
    }
}
