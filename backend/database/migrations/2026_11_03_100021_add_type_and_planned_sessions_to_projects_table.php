<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Champs de l'écran « Nouveau projet » (maquette, écran 58) absents du schéma :
 * le type de projet (« Partenariat lycée ») et le nombre de séances prévues,
 * affiché sur le tableau de bord (« 21 / 32 séances »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('project_type', 100)->nullable()->after('description');
            $table->unsignedSmallInteger('planned_sessions')->nullable()->after('project_type');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE projects ADD CONSTRAINT chk_projects_planned_sessions CHECK (planned_sessions IS NULL OR planned_sessions > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE projects DROP CONSTRAINT chk_projects_planned_sessions');
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['project_type', 'planned_sessions']);
        });
    }
};
