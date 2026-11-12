<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// pivot projet <-> financeur
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funder_project', function (Blueprint $table) {
            $table->foreignId('funder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->primary(['funder_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funder_project');
    }
};
