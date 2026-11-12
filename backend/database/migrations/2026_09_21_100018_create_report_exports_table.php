<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des exports du pré-bilan. On génère le .docx à la volée : on ne le stocke pas.
 */
return new class extends Migration
{
    /**
     * On applique la migration.
     */
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('funder_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generated_by')->constrained('users');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('options')->nullable();   // sections / indicateurs cochés
            $table->timestamps();

            $table->index('project_id');
        });
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
