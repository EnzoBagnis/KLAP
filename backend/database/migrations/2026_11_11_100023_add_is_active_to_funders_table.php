<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financeurs tenus par l'administratrice (#249) : on désactive un financeur au lieu de le
 * supprimer. Désactivé, on ne le propose plus pour un nouveau projet ; les projets qui l'ont
 * le gardent.
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::table('funders', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('type');
        });
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::table('funders', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
