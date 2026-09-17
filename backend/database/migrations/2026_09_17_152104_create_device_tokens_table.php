<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token FCM d'un appareil, mis à jour par l'app à chaque connexion
     * (CLAUDE.md §5, ajout v0.12). Unique sur "token" seul (pas sur
     * user_id+token) : un même appareil physique n'a qu'un seul jeton FCM à
     * un instant donné, et un changement de compte sur le même appareil
     * doit réassigner ce jeton plutôt que d'en dupliquer un nouveau.
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
