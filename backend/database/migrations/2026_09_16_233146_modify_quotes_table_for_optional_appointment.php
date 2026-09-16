<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un devis référence désormais directement son Garage et son Client :
     * le RDV devient un lien de traçabilité optionnel plutôt qu'une condition
     * technique obligatoire (CLAUDE.md §5, ajout v0.9 — un devis est
     * nécessaire dès qu'il y a une prestation, avec ou sans RDV préalable).
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('garage_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->after('garage_id')->constrained()->cascadeOnDelete();
        });

        DB::statement('UPDATE quotes SET garage_id = appointments.garage_id, user_id = appointments.user_id FROM appointments WHERE appointments.id = quotes.appointment_id');

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('garage_id')->nullable(false)->change();
            $table->foreignId('user_id')->nullable(false)->change();

            $table->dropForeign(['appointment_id']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->change();
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropForeign(['garage_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['garage_id', 'user_id']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable(false)->change();
            $table->foreign('appointment_id')->references('id')->on('appointments')->cascadeOnDelete();
        });
    }
};
