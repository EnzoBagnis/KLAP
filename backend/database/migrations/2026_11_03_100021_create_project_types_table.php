<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Types de projet (« Partenariat lycée », « Projet annuel école »…), gérés par
 * l'administratrice. Comme les lieux et les champs personnalisés, un type se
 * désactive, il ne se supprime jamais : les projets qui l'utilisent le gardent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_types');
    }
};
