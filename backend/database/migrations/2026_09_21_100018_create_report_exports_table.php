<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Historique des exports du pré-bilan (le .docx est généré à la volée, pas stocké)
return new class extends Migration
{
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

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
