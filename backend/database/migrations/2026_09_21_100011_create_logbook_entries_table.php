<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fiches du carnet de bord. La partie P4 (participants) est dans participant_evaluations.
// Les checks ne bloquent pas les brouillons, seulement les fiches soumises
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_session_id')->unique()->constrained();  // 1 fiche max par séance
            $table->foreignId('author_id')->constrained('users');
            $table->enum('status', ['draft', 'submitted', 'locked'])->default('draft')->index();

            // P1 - Généralités
            $table->unsignedSmallInteger('attendees_count')->nullable(); // Effectif total (max 999)
            $table->unsignedSmallInteger('attendees_male')->nullable();  // Garçons (obligatoire à la soumission)
            $table->unsignedSmallInteger('attendees_female')->nullable(); // Filles (obligatoire à la soumission)
            $table->string('class_or_age', 50)->nullable();             // Classe ou âge (ex: 2nde — 15-16 ans)

            // P2 - Objectifs
            $table->string('objectives', 400)->nullable();              // Objectifs péda & artistiques
            $table->string('competencies', 200)->nullable();            // Compétences en jeu
            $table->string('progression', 200)->nullable();             // Progression du projet

            // P3 - La séance
            $table->string('highlights', 200)->nullable();              // Temps forts & anecdotes
            $table->string('closing', 100)->nullable();                 // Clôture
            $table->string('feedback', 500)->nullable();                // Retours
            $table->string('tools_resources', 200)->nullable();         // Outils / ressources externes
            $table->boolean('adjustment_done')->nullable();             // Fait / non fait
            $table->string('adjustment_detail', 500)->nullable();       // Pourquoi (obligatoire si fait)

            // P5 - Les artistes
            $table->string('what_works', 200)->nullable();              // Ce qui fonctionne
            $table->string('learnings', 200)->nullable();               // Les acquis
            $table->string('to_watch', 200)->nullable();                // À surveiller
            $table->string('to_improve', 200)->nullable();              // À améliorer
            $table->string('additional_notes', 1000)->nullable();       // Notes complémentaires (1000 car max)

            // P6 - La suite
            $table->string('next_steps', 500)->nullable();              // À reprendre
            $table->boolean('keep_in_class')->default(false);           // Maintien en classe avec équipe enseignante
            $table->string('dropped_leads', 500)->nullable();           // Pistes à abandonner (pourquoi ?)
            $table->string('participant_wishes', 500)->nullable();      // Idées et envies exprimées par les participants

            // Verrouillage J+10 & Demande de déverrouillage
            $table->date('edit_deadline')->nullable();                  // Date séance + 10j, calculée par l'API
            $table->timestamp('submitted_at')->nullable();
            $table->text('unlock_reason')->nullable();                  // Motif de la demande formulée par l'artiste
            $table->timestamp('unlock_requested_at')->nullable();
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

    public function down(): void
    {
        Schema::dropIfExists('logbook_entries');
    }
};
