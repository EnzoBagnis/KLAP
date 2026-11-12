<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financeurs des projets. Le pré-bilan se prépare par financeur.
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('funders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->enum('type', ['state', 'local_authority', 'foundation', 'other'])->default('other');
            $table->timestamps();
        });
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('funders');
    }
};
