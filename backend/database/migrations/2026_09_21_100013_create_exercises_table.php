<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Exercices d'une séance, 10 max. Le minimum de 2 à la soumission est vérifié côté API
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logbook_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('label', 200);
            $table->timestamps();

            $table->unique(['logbook_entry_id', 'position']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE exercises ADD CONSTRAINT chk_exercises_position CHECK (position BETWEEN 1 AND 10)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
