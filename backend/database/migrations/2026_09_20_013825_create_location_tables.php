<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Données de référence du découpage administratif (CLAUDE.md §5, ajout
     * v0.19). Slug unique dans son parent : identifiant stable, indépendant
     * de l'orthographe d'affichage.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unique(['department_id', 'slug']);
        });

        Schema::create('arrondissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unique(['commune_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arrondissements');
        Schema::dropIfExists('communes');
        Schema::dropIfExists('departments');
    }
};
