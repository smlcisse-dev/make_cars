<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compte automobiliste "express" créé par un garagiste pour un client
     * walk-in sans app (CLAUDE.md §5, ajout v0.9) : l'email reste obligatoire
     * pour ce flux précis au niveau validation métier, mais devient nullable
     * en base pour ouvrir la voie à de futurs flux sans email (ex. auth par
     * téléphone/SMS, cf. §7 points ouverts). `is_express` distingue un compte
     * jamais "réclamé" par son propriétaire réel (mot de passe non défini).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->boolean('is_express')->default(false)->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_express');
            $table->string('email')->nullable(false)->change();
        });
    }
};
