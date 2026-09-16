<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('professional_registrations', function (Blueprint $table) {
            $table->text('suspension_reason')->nullable()->after('rejection_reason');
            $table->foreignId('suspended_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable()->after('suspended_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('professional_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspension_reason', 'suspended_at']);
        });
    }
};
