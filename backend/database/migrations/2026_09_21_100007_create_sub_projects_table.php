<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Sous-projets (une classe, un collège...). Un projet en a toujours au moins un
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->string('name', 150);
            $table->string('organization', 150)->nullable();  // établissement, asso
            $table->string('class_group', 100)->nullable();
            $table->string('age_range', 30)->nullable();       // ex. 16-17 ans
            $table->string('city', 100)->nullable();
            $table->char('postal_code', 5)->nullable();
            $table->unsignedSmallInteger('expected_headcount')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // sert à la clé étrangère composite de scheduled_session_sub_project
            $table->unique(['id', 'project_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sub_projects ADD CONSTRAINT chk_sub_projects_postal_code CHECK (postal_code IS NULL OR postal_code REGEXP '^[0-9]{5}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_projects');
    }
};
