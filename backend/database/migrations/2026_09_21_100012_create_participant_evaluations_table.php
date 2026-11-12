<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partie 4 de la fiche : l'observation des participants. Une ligne par fiche.
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('participant_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logbook_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('engagement')->nullable();
            $table->unsignedTinyInteger('expression')->nullable();
            $table->unsignedTinyInteger('autonomy')->nullable();
            $table->string('interaction', 100)->nullable();   // écoute, entraide, conflit...
            $table->enum('feeling', ['well_being', 'discomfort', 'tension', 'buy_in', 'enthusiasm', 'shyness', 'other'])->nullable();
            $table->string('feeling_detail', 100)->nullable();
            $table->enum('progress', ['first_session', 'improvement', 'stagnation', 'regression'])->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE participant_evaluations ADD CONSTRAINT chk_eval_engagement CHECK (engagement BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE participant_evaluations ADD CONSTRAINT chk_eval_expression CHECK (expression BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE participant_evaluations ADD CONSTRAINT chk_eval_autonomy CHECK (autonomy BETWEEN 1 AND 5)');
            DB::statement("ALTER TABLE participant_evaluations ADD CONSTRAINT chk_eval_feeling_detail CHECK (feeling IS NULL OR feeling <> 'other' OR (feeling_detail IS NOT NULL AND CHAR_LENGTH(feeling_detail) > 0))");
        }
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('participant_evaluations');
    }
};
