<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P5 - artistes présents à la séance
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbook_entry_user', function (Blueprint $table) {
            $table->foreignId('logbook_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->enum('member_role', ['lead', 'occasional'])->default('lead');
            $table->primary(['logbook_entry_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_entry_user');
    }
};
