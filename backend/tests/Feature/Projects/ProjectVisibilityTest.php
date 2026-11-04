<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * RG02 (#89) : la super-utilisatrice consulte tous les projets, l'intervenant seulement
 * ceux où il est affecté. Un projet non affecté lui renvoie 404, pour ne pas révéler
 * qu'il existe.
 */
class ProjectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Project $braises;

    private Project $aimer;

    private Project $draft;

    private Project $archived;

    private Project $otherArchived;

    private User $lea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lea = User::factory()->role(UserRole::Contributor)->create(['name' => 'Léa Fournier']);

        $this->braises = Project::factory()->create(['name' => 'BRAISES', 'start_date' => '2026-09-01']);
        $this->aimer = Project::factory()->create(['name' => 'AIMER', 'start_date' => '2026-09-02']);
        $this->draft = Project::factory()->create(['name' => 'Classes cousines', 'status' => 'draft', 'start_date' => '2026-10-01']);
        $this->archived = Project::factory()->create(['name' => 'Pilote 2025', 'status' => 'archived', 'start_date' => '2025-01-01']);
        $this->otherArchived = Project::factory()->create(['name' => 'Pilote 2024', 'status' => 'archived', 'start_date' => '2024-01-01']);
        Project::factory()->create(['name' => 'Étincelles', 'start_date' => '2026-09-03']);

        // Léa est principale de BRAISES et du brouillon, ponctuelle d'AIMER et affectée à un archivé.
        $this->braises->members()->attach($this->lea->id, ['member_role' => 'lead']);
        $this->aimer->members()->attach($this->lea->id, ['member_role' => 'occasional']);
        $this->draft->members()->attach($this->lea->id, ['member_role' => 'lead']);
        $this->archived->members()->attach($this->lea->id, ['member_role' => 'lead']);
    }

    private function names(string $uri): array
    {
        return $this->getJson($uri)->assertOk()->json('data.*.name');
    }

    public function test_l_administratrice_voit_toujours_tout(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->assertCount(4, $this->names('/api/projects'));
        $this->assertCount(6, $this->names('/api/projects?include_archived=1'));
        $this->getJson("/api/projects/{$this->otherArchived->id}")->assertOk();
    }

    public function test_la_super_utilisatrice_voit_tous_les_projets_non_archives(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::SuperUser)->create());

        $this->assertEqualsCanonicalizing(
            ['BRAISES', 'AIMER', 'Classes cousines', 'Étincelles'],
            $this->names('/api/projects'),
        );
        $this->assertCount(6, $this->names('/api/projects?include_archived=1'));
    }

    public function test_la_super_utilisatrice_lit_le_detail_de_n_importe_quel_projet(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::SuperUser)->create());

        $this->getJson("/api/projects/{$this->braises->id}")->assertOk()->assertJsonPath('data.name', 'BRAISES');
        $this->getJson("/api/projects/{$this->otherArchived->id}")->assertOk();
        $this->getJson('/api/projects/999999')->assertNotFound();
    }

    public function test_l_intervenant_ne_voit_que_ses_projets_brouillon_compris(): void
    {
        Sanctum::actingAs($this->lea);

        $this->assertEqualsCanonicalizing(
            ['BRAISES', 'AIMER', 'Classes cousines'],
            $this->names('/api/projects'),
        );
    }

    public function test_include_archived_n_ajoute_que_les_archives_de_l_intervenant(): void
    {
        Sanctum::actingAs($this->lea);

        $this->assertEqualsCanonicalizing(
            ['BRAISES', 'AIMER', 'Classes cousines', 'Pilote 2025'],
            $this->names('/api/projects?include_archived=1'),
        );
    }

    public function test_un_intervenant_sans_affectation_recoit_une_liste_vide(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Contributor)->create());

        $this->getJson('/api/projects')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_l_intervenant_lit_le_detail_de_ses_projets_principal_ou_ponctuel(): void
    {
        Sanctum::actingAs($this->lea);

        $this->getJson("/api/projects/{$this->braises->id}")->assertOk()->assertJsonPath('data.name', 'BRAISES');
        $this->getJson("/api/projects/{$this->aimer->id}")->assertOk()->assertJsonPath('data.name', 'AIMER');
        $this->getJson("/api/projects/{$this->archived->id}")->assertOk();
    }

    public function test_un_projet_non_affecte_renvoie_404_sans_reveler_son_nom(): void
    {
        Sanctum::actingAs($this->lea);
        $etincelles = Project::where('name', 'Étincelles')->firstOrFail();
        // Comme en production : sans la trace de débogage, qui contient la ligne d'appel.
        config(['app.debug' => false]);

        $hidden = $this->getJson("/api/projects/{$etincelles->id}")->assertNotFound()->getContent();
        $missing = $this->getJson('/api/projects/999999')->assertNotFound()->getContent();

        // Même réponse au caractère près : rien ne distingue un projet caché d'un id inconnu.
        $this->assertSame($missing, $hidden);
        $this->assertStringNotContainsString('Étincelles', $hidden);
        $this->assertSame($missing, $this->getJson("/api/projects/{$this->otherArchived->id}")->getContent());
        $this->assertSame($missing, $this->getJson('/api/projects/abc')->assertNotFound()->getContent());
    }

    public function test_les_autres_roles_ne_peuvent_toujours_pas_creer_de_projet(): void
    {
        foreach ([UserRole::Contributor, UserRole::SuperUser] as $role) {
            Sanctum::actingAs(User::factory()->role($role)->create());

            $this->postJson('/api/projects', ['name' => 'Projet interdit'])->assertForbidden();
        }

        $this->assertDatabaseMissing('projects', ['name' => 'Projet interdit']);
    }

    public function test_un_visiteur_non_connecte_est_refuse(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->getJson("/api/projects/{$this->braises->id}")->assertUnauthorized();
    }
}
