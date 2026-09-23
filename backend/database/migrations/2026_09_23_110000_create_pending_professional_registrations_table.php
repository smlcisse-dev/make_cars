<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demande d'inscription professionnelle en attente de vérification de
     * l'email (CLAUDE.md §5, ajout v0.26) : aucun compte `users` n'existe
     * tant que le code n'a pas été saisi correctement. Le mot de passe et le
     * code sont stockés hachés.
     */
    public function up(): void
    {
        Schema::create('pending_professional_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('account_type');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->index();
            $table->string('phone');
            $table->string('password');
            $table->string('code_hash');
            $table->timestamp('code_expires_at');
            $table->unsignedTinyInteger('attempts_left');
            $table->timestamp('last_code_sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_professional_registrations');
    }
};
