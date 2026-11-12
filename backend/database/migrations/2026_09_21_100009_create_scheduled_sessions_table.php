<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planning. La séance existe avant la fiche et sert à la pré-remplir. On ne l'appelle pas
 * « sessions » : ce nom est déjà pris par la table des sessions de Laravel.
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('scheduled_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->date('session_date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('location_id')->constrained();
            $table->foreignId('referent_id')->constrained('users');  // l'intervenant qui remplit la fiche
            $table->enum('session_type', [
                'guided_tour', 'viewing_workshop', 'practice_workshop',
                'complementary_workshop', 'performance', 'ongoing_project', 'other',
            ]);
            $table->string('session_type_detail', 100)->nullable();  // obligatoire si other
            $table->enum('status', ['planned', 'done', 'cancelled'])->default('planned');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['id', 'project_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE scheduled_sessions ADD CONSTRAINT chk_sessions_times CHECK (end_time > start_time)');
            DB::statement("ALTER TABLE scheduled_sessions ADD CONSTRAINT chk_sessions_type_detail CHECK (session_type <> 'other' OR (session_type_detail IS NOT NULL AND CHAR_LENGTH(session_type_detail) > 0))");
        }
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_sessions');
    }
};
