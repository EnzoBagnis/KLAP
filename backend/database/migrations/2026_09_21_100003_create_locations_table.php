<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lieux, partagés par toute l'équipe : n'importe qui peut en ajouter un (projets en tournée).
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('address')->nullable();
            $table->char('postal_code', 5);
            $table->string('city', 100);
            $table->boolean('is_klap')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['name', 'postal_code']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE locations ADD CONSTRAINT chk_locations_postal_code CHECK (postal_code REGEXP '^[0-9]{5}$')");
        }
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
