<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des décisions admin sur un dossier d'inscription
     * (CLAUDE.md §5 ajout v0.26, traçabilité §6) : un dossier refusé puis
     * soumis à nouveau accumule plusieurs décisions, alors que
     * `professional_registrations` ne garde que la dernière. Rempli à partir
     * des dossiers déjà examinés (`reviewed_at` renseigné).
     */
    public function up(): void
    {
        Schema::create('registration_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_registration_id')->constrained()->cascadeOnDelete();
            $table->string('decision');
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();
        });

        $now = now();

        $reviewed = DB::table('professional_registrations')
            ->whereNotNull('reviewed_at')
            ->whereIn('status', ['approved', 'rejected'])
            ->get(['id', 'status', 'rejection_reason', 'reviewed_by', 'reviewed_at']);

        foreach ($reviewed as $registration) {
            DB::table('registration_decisions')->insert([
                'professional_registration_id' => $registration->id,
                'decision' => $registration->status,
                'reason' => $registration->status === 'rejected' ? $registration->rejection_reason : null,
                'decided_by' => $registration->reviewed_by,
                'decided_at' => $registration->reviewed_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_decisions');
    }
};
