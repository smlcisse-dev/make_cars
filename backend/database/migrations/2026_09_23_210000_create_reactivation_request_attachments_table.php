<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pièces jointes d'une demande de réactivation (CLAUDE.md §5, ajout
     * v0.28) : preuves facultatives (0 à 5), sur le disque privé des médias.
     * Elles font partie de l'historique de la demande : jamais supprimées.
     */
    public function up(): void
    {
        Schema::create('reactivation_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reactivation_request_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactivation_request_attachments');
    }
};
