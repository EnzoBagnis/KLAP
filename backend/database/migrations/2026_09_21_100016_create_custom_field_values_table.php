<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Réponses aux champs personnalisés pour chaque fiche de carnet de bord
return new class extends Migration
{
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

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
