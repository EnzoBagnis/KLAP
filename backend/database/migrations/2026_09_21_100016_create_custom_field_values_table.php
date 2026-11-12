<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valeurs des champs supplémentaires, fiche par fiche. Comme custom_fields, la table est
 * prête mais l'application ne s'en sert pas encore.
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logbook_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['logbook_entry_id', 'custom_field_id']);
        });
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
