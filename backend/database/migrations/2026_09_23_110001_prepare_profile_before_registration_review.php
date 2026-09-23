<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le profil Garage/Market Space est désormais créé vide dès la
     * vérification de l'email, et le dossier KYC au statut
     * `profile_incomplete` sans numéro RCCM (saisi ensuite à l'étape profil)
     * — CLAUDE.md §5, ajout v0.26. `structure_name`/`address` du dossier
     * deviennent nullable en attendant leur suppression (le profil devient
     * leur seule source, migration suivante du même lot).
     */
    public function up(): void
    {
        foreach (['garages', 'market_space_accounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('name')->nullable()->change();
                $table->string('address')->nullable()->change();
            });
        }

        Schema::table('professional_registrations', function (Blueprint $table) {
            $table->string('structure_name')->nullable()->change();
            $table->string('address')->nullable()->change();
            $table->string('business_registration_number')->nullable()->change();
        });
    }

    /**
     * Ne peut revenir en arrière que si aucune ligne n'a de valeur nulle dans
     * ces colonnes (sinon l'ajout de NOT NULL échoue) : les dossiers/profils
     * créés par le nouveau parcours devraient d'abord être complétés ou
     * supprimés.
     */
    public function down(): void
    {
        foreach (['garages', 'market_space_accounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('name')->nullable(false)->change();
                $table->string('address')->nullable(false)->change();
            });
        }

        Schema::table('professional_registrations', function (Blueprint $table) {
            $table->string('structure_name')->nullable(false)->change();
            $table->string('address')->nullable(false)->change();
            $table->string('business_registration_number')->nullable(false)->change();
        });
    }
};
