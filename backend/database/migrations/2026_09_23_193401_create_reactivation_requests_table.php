<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demandes de réactivation d'un compte suspendu (CLAUDE.md §5, ajout
     * v0.28). Cette table est l'historique des demandes (traçabilité §6) :
     * aucune demande n'est jamais supprimée, seul son statut évolue.
     */
    public function up(): void
    {
        Schema::create('reactivation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_registration_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('status')->default('pending');
            $table->text('response_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['professional_registration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactivation_requests');
    }
};
