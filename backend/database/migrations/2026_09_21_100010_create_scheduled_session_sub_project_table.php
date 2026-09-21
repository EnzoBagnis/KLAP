<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Groupes concernés par une séance (plusieurs = fiche transversale) + nb de présents par groupe.
// (noms d'index raccourcis, sinon MySQL refuse : 64 caractères max)
// project_id est là pour que les clés composites empêchent de mélanger des groupes de projets différents
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_session_sub_project', function (Blueprint $table) {
            $table->unsignedBigInteger('scheduled_session_id');
            $table->unsignedBigInteger('sub_project_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedSmallInteger('attendees')->nullable();  // saisi dans la fiche

            $table->primary(['scheduled_session_id', 'sub_project_id']);
            $table->index(['sub_project_id', 'project_id'], 'ssp_sub_project_index');

            $table->foreign(['scheduled_session_id', 'project_id'], 'ssp_session_foreign')
                ->references(['id', 'project_id'])->on('scheduled_sessions')
                ->cascadeOnDelete();
            $table->foreign(['sub_project_id', 'project_id'], 'ssp_sub_project_foreign')
                ->references(['id', 'project_id'])->on('sub_projects');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_session_sub_project');
    }
};
