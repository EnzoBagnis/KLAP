<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fiches du carnet de bord. La partie 4 (les participants) est dans participant_evaluations.
 * Nos contraintes CHECK ne bloquent pas les brouillons, seulement les fiches soumises.
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('logbook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_session_id')->unique()->constrained();  // 1 fiche max par séance
            $table->foreignId('author_id')->constrained('users');
            $table->enum('status', ['draft', 'submitted', 'locked'])->default('draft')->index();

            // Partie 2 : les objectifs.
            $table->string('objectives', 400)->nullable();
            // Partie 3 : le déroulé de la séance.
            $table->boolean('adjustment_done')->nullable();          // fait / non fait
            $table->string('adjustment_detail', 500)->nullable();
            $table->string('highlights', 200)->nullable();           // temps forts ("rien" accepté)
            $table->string('tools_resources', 200)->nullable();
            // Partie 5 : le bilan de l'intervenant·e.
            $table->string('learnings', 200)->nullable();            // les acquis
            $table->string('artist_review', 200)->nullable();
            // Partie 6 : la suite à donner.
            $table->string('next_steps', 500)->nullable();
            $table->string('dropped_leads', 500)->nullable();        // pistes à abandonner
            $table->boolean('keep_in_class')->default(false);        // maintien en classe avec les profs
            $table->string('additional_notes', 500)->nullable();

            $table->date('edit_deadline')->nullable();               // date séance + 10j, calculée par l'API
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE logbook_entries ADD CONSTRAINT chk_entries_adjustment CHECK (
                status = 'draft'
                OR adjustment_done = 0
                OR (adjustment_done = 1 AND adjustment_detail IS NOT NULL AND CHAR_LENGTH(adjustment_detail) BETWEEN 5 AND 500)
            )");
            DB::statement("ALTER TABLE logbook_entries ADD CONSTRAINT chk_entries_submitted CHECK (
                status = 'draft' OR (submitted_at IS NOT NULL AND adjustment_done IS NOT NULL)
            )");
        }
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('logbook_entries');
    }
};
