<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Code de réinitialisation du mot de passe (CLAUDE.md §5, ajout v0.29) :
     * une seule demande en cours par email, le code est stocké haché.
     * Distincte de `password_reset_tokens` (mécanisme par lien de Laravel,
     * inutilisé par la plateforme).
     */
    public function up(): void
    {
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('code_hash');
            $table->timestamp('code_expires_at');
            $table->unsignedTinyInteger('attempts_left');
            $table->timestamp('last_code_sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_codes');
    }
};
