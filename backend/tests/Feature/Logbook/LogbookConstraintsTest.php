<?php

namespace Tests\Feature\Logbook;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Sur MariaDB : l'API renvoie 422, jamais 500, sur les valeurs que les CHECK refusent,
 * et la base refuse d'elle-même les états incohérents.
 */
#[Group('mariadb')]
class LogbookConstraintsTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Contraintes CHECK non vérifiées par SQLite.');
        }
        $this->setUpSession();
    }

    public function test_l_api_renvoie_422_sur_les_valeurs_que_la_base_refuse(): void
    {
        $id = $this->createEntry();

        foreach ([
            ['evaluation' => ['engagement' => 9]],
            ['evaluation' => ['feeling' => 'other']],
            ['exercises' => array_fill(0, 11, 'x')],
        ] as $payload) {
            $this->patchJson("/api/logbook-entries/{$id}", $payload)->assertUnprocessable();
        }

        // Effacer la précision d'une sensation « autre » déjà enregistrée : 422, pas 500.
        $this->patchJson("/api/logbook-entries/{$id}", ['evaluation' => ['feeling' => 'other', 'feeling_detail' => 'Fatigue']])->assertOk();
        foreach ([['evaluation' => ['feeling_detail' => null]], ['evaluation' => ['feeling_detail' => '']]] as $payload) {
            $this->patchJson("/api/logbook-entries/{$id}", $payload)->assertUnprocessable();
        }

        $this->patchJson("/api/logbook-entries/{$id}", $this->completePayload())->assertOk();
        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();
        $this->patchJson("/api/logbook-entries/{$id}", ['adjustment_done' => true, 'adjustment_detail' => 'ab'])->assertUnprocessable();
    }

    public function test_la_base_refuse_une_fiche_soumise_sans_date_de_soumission(): void
    {
        $id = $this->createEntry();

        $this->expectException(QueryException::class);
        DB::table('logbook_entries')->where('id', $id)->update(['status' => 'submitted', 'adjustment_done' => false]);
    }

    public function test_la_base_refuse_une_explication_d_ajustement_trop_courte(): void
    {
        $id = $this->createEntry();

        $this->expectException(QueryException::class);
        DB::table('logbook_entries')->where('id', $id)->update([
            'status' => 'submitted', 'submitted_at' => now(), 'adjustment_done' => true, 'adjustment_detail' => 'abc',
        ]);
    }

    public function test_la_base_refuse_un_brouillon_verrouille(): void
    {
        $id = $this->createEntry();

        $this->expectException(QueryException::class);
        DB::table('logbook_entries')->where('id', $id)->update(['status' => 'locked']);
    }
}
