<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Message système posté à la génération de la facture d'une commande
     * (même mécanisme que pour les devis/factures — CLAUDE.md §5, ajout v0.9),
     * uniquement pour la mini-boutique Garage : le chat ne couvre pour
     * l'instant que la paire Garage ↔ Automobiliste (ajout v0.8).
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('quote_version_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
