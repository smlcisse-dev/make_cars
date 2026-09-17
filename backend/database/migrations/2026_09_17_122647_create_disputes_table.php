<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réclamation d'un automobiliste sur un Garage ou un Market Space,
     * obligatoirement rattachée à une transaction terminée (devis facturé ou
     * commande payée) — même principe de rattachement que le module Avis,
     * pour éviter les réclamations non fondées (CLAUDE.md §5, ajout v0.11).
     * "respondent" porte le professionnel visé, "transaction" porte la
     * preuve d'éligibilité. Cycle : submitted → under_review →
     * resolved_founded/resolved_rejected → closed.
     */
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->morphs('respondent');
            $table->string('transaction_type');
            $table->unsignedBigInteger('transaction_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status')->default('submitted');
            $table->timestamp('response_requested_at')->nullable();
            $table->foreignId('response_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_reason')->nullable();
            $table->string('resolution_action')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['transaction_type', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
