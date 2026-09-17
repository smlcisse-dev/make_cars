<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avis/notation d'un automobiliste sur un Garage ou un Market Space,
     * uniquement après une transaction terminée (CLAUDE.md §5, règle 7 et
     * ajout v0.10) : un devis facturé ou une commande payée. "reviewable"
     * (Garage/MarketSpaceAccount) porte la cible notée, "transaction"
     * (Quote/Order) porte la preuve d'éligibilité — un seul avis par
     * transaction (contrainte unique), mais plusieurs transactions
     * différentes avec le même garage peuvent chacune recevoir leur avis.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->morphs('reviewable');
            $table->string('transaction_type');
            $table->unsignedBigInteger('transaction_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status')->default('visible');
            $table->text('moderation_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->unique(['transaction_type', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
