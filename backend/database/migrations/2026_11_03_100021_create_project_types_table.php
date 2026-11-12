<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Types de projet (« Partenariat lycée », « Projet annuel école »…), que l'administratrice
 * tient elle-même. Comme les lieux et les champs personnalisés, on désactive un type, on ne le
 * supprime jamais : les projets qui l'utilisent le gardent.
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_types');
    }
};
