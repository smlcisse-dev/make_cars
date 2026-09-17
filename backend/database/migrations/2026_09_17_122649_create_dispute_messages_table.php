<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Espace d'échange dédié à une réclamation, entre l'admin et le
     * professionnel concerné (Garage/Market Space) — distinct du chat
     * Garage↔Automobiliste (portée limitée à cette paire, CLAUDE.md §5,
     * ajout v0.8). `author_id` identifie l'auteur (admin ou professionnel),
     * jamais nul : contrairement au chat, il n'y a pas de message système ici.
     */
    public function up(): void
    {
        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_messages');
    }
};
