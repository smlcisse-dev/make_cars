<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Achat isolé de pièces/produits, sans prestation associée (CLAUDE.md §5,
     * règle 11 et ajout v0.9) : commande → paiement immédiat → facture. La
     * relation polymorphe "sellable" mutualise Garage (mini-boutique) et
     * Market Space, comme pour Product (CLAUDE.md §5, ajout v0.4).
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->morphs('sellable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('pdf_disk')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
