<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Même champs que Garage pour le profil public (module Profil Market
     * Space, ajout v0.10).
     */
    public function up(): void
    {
        Schema::table('market_space_accounts', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('market_space_accounts', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
