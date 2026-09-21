<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pas d'inscription libre : un compte se crée uniquement via une invitation de l'admin
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('full_name', 160);          // pré-rempli, pas modifiable à l'activation
            $table->enum('role', ['admin', 'super_user', 'contributor'])->default('contributor');
            $table->char('token_hash', 64)->unique();  // sha256 du token envoyé par mail
            $table->foreignId('invited_by')->constrained('users');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // rempli à l'activation
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
