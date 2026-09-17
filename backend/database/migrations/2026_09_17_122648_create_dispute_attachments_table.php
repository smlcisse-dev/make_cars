<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos jointes en preuve à une réclamation, sur le disque privé dédié
     * aux médias (même mécanisme que les images du chat — CLAUDE.md §5,
     * ajouts v0.8 et v0.11) : jamais d'URL publique, toujours un
     * téléchargement authentifié gated par l'appartenance à la réclamation.
     */
    public function up(): void
    {
        Schema::create('dispute_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_attachments');
    }
};
