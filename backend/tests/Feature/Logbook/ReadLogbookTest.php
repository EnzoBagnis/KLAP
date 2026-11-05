<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadLogbookTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
    }

    public static function readers(): array
    {
        return ['administratrice' => [UserRole::Admin], 'super-utilisatrice' => [UserRole::SuperUser]];
    }

    #[DataProvider('readers')]
    public function test_l_administration_lit_toutes_les_fiches(UserRole $role): void
    {
        $id = $this->createEntry(['objectives' => 'Objectif']);

        $this->actingAs(User::factory()->role($role)->create())
            ->getJson("/api/logbook-entries/{$id}")
            ->assertOk()
            ->assertJsonPath('data.objectives', 'Objectif')
            ->assertJsonPath('data.editable', false);
    }

    public function test_un_intervenant_ne_lit_que_ses_fiches_et_reprend_ses_brouillons(): void
    {
        $id = $this->createEntry();
        config(['app.debug' => false]);

        $this->getJson("/api/logbook-entries/{$id}")->assertOk()->assertJsonPath('data.author.id', $this->referent->id);
        $this->getJson("/api/sessions/{$this->session->id}/logbook-entry")->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson('/api/logbook-entries?status=draft')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/logbook-entries?status=submitted')->assertOk()->assertJsonCount(0, 'data');

        $other = User::factory()->role(UserRole::Contributor)->create();
        $hidden = $this->actingAs($other)->getJson("/api/logbook-entries/{$id}")->assertNotFound()->getContent();
        $this->assertSame($hidden, $this->actingAs($other)->getJson('/api/logbook-entries/999999')->assertNotFound()->getContent());
        $this->actingAs($other)->getJson('/api/logbook-entries')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($other)->getJson("/api/sessions/{$this->session->id}/logbook-entry")->assertNotFound();
    }

    public function test_un_statut_inconnu_est_refuse_et_un_visiteur_aussi(): void
    {
        $this->actingAs($this->referent)->getJson('/api/logbook-entries?status=perdu')
            ->assertUnprocessable()->assertJsonPath('errors.status.0', 'Le statut demandé n’existe pas.');
    }
}
