<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financeurs gérés par l'administratrice (#249) : un financeur se désactive au lieu d'être supprimé.
 * Désactivé, il n'est plus proposé pour un nouveau projet ; les projets qui l'ont le gardent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funders', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('funders', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
