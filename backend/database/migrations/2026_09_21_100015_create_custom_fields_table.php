<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Champs supplémentaires propres à un projet. On les désactive, on ne les supprime jamais.
 * La table est prête, mais l'application ne propose pas encore cette fonction.
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->enum('section', ['general', 'objectives', 'session', 'participants', 'artists', 'next_steps']);
            $table->string('label', 150);
            $table->enum('field_type', ['text', 'number', 'select', 'checkbox', 'scale']);
            $table->json('options')->nullable();   // choix possibles (select) ou bornes (scale)
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->useCurrent();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'section', 'position']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE custom_fields ADD CONSTRAINT chk_custom_fields_active CHECK (is_active = 1 OR deactivated_at IS NOT NULL)');
        }
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
