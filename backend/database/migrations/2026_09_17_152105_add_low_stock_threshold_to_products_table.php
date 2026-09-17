<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seuil d'alerte de stock bas, optionnel et propre à chaque produit
     * (CLAUDE.md §5, ajout v0.12) : pas de valeur par défaut imposée si le
     * vendeur ne le configure pas. "low_stock_alert_sent_at" évite qu'une
     * alerte se redéclenche à chaque vente supplémentaire une fois sous le
     * seuil — remis à null dès que le stock remonte au-dessus du seuil.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('stock_quantity');
            $table->timestamp('low_stock_alert_sent_at')->nullable()->after('low_stock_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['low_stock_threshold', 'low_stock_alert_sent_at']);
        });
    }
};
