<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un événement notifiable enregistré pour un destinataire précis
     * (CLAUDE.md §5, ajout v0.12). "created" est aussi bien le statut initial
     * que le statut "en attente d'envoi réel" tant que FCM n'est pas
     * configuré (§7, point ouvert) — le passage à "sent"/"failed" ne
     * survient qu'une fois une tentative d'envoi réel effectuée.
     */
    public function up(): void
    {
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('status')->default('created');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
