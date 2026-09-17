<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champs structurés (liste fixe, App\Enums\City/Region) en complément de
     * l'adresse texte libre déjà existante — données agrégées pour les
     * autorités béninoises (CLAUDE.md §1, ajout v0.17).
     */
    public function up(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->string('city')->nullable()->after('address');
            $table->string('region')->nullable()->after('city');
        });

        Schema::table('market_space_accounts', function (Blueprint $table) {
            $table->string('city')->nullable()->after('address');
            $table->string('region')->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->dropColumn(['city', 'region']);
        });

        Schema::table('market_space_accounts', function (Blueprint $table) {
            $table->dropColumn(['city', 'region']);
        });
    }
};
