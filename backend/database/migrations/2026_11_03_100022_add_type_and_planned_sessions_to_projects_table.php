<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Champs de l'écran « Nouveau projet » (maquette, écran 58) absents du schéma :
 * - le type de projet, choisi dans la liste gérée par l'administratrice ;
 * - le nombre de séances prévues, objectif fixé à la création (le cahier des charges
 *   annonce le volume d'interventions de chaque projet avant tout planning). Le nombre
 *   de séances réalisées, lui, se calcule à partir des séances et n'est pas stocké.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        Schema::table('projects', function (Blueprint $table) use ($sqlite) {
            $column = $table->foreignId('project_type_id')->nullable()->after('description');
            // Sur SQLite, une clé étrangère ajoutée oblige à reconstruire la table, ce qui
            // casse les vues de statistiques qui la lisent. La contrainte vit donc sur
            // MariaDB et MySQL, comme les CHECK.
            if (! $sqlite) {
                $column->constrained('project_types');
            }
            $table->unsignedSmallInteger('planned_sessions')->nullable()->after('project_type_id');
        });

        if (! $sqlite) {
            DB::statement('ALTER TABLE projects ADD CONSTRAINT chk_projects_planned_sessions CHECK (planned_sessions IS NULL OR planned_sessions > 0)');
        }
    }

    public function down(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        if (! $sqlite) {
            DB::statement('ALTER TABLE projects DROP CONSTRAINT chk_projects_planned_sessions');
        }

        Schema::table('projects', function (Blueprint $table) use ($sqlite) {
            if (! $sqlite) {
                $table->dropForeign(['project_type_id']);
            }
            $table->dropColumn(['project_type_id', 'planned_sessions']);
        });
    }
};
