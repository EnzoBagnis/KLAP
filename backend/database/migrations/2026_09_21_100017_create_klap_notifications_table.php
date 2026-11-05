<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Notifications de l'appli (fiche rendue, demande de déverrouillage...).
// Nommée klap_notifications pour laisser libre la table notifications de Laravel si on s'en sert un jour
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klap_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['entry_submitted', 'unlock_request', 'unlock_approved', 'invitation_accepted', 'reminder']);
            $table->foreignId('logbook_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('klap_notifications');
    }
};
