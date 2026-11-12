<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projets. On ne les supprime jamais : on les archive (status = archived).
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['draft', 'active', 'finished', 'archived'])->default('active')->index();
            $table->foreignId('default_location_id')->constrained('locations');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE projects ADD CONSTRAINT chk_projects_dates CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)');
        }
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
