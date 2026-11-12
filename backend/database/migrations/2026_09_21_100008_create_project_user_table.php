<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Équipe d'un projet : les intervenant·e·s qui y sont rattaché·e·s, en principal·e ou en
 * ponctuel·le, avec leurs dates. On ne confie une séance qu'à un membre de l'équipe.
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('member_role', ['lead', 'occasional'])->default('lead');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->primary(['project_id', 'user_id']);
        });
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_user');
    }
};
