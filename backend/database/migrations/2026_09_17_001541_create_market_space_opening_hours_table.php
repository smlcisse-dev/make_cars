<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Même mécanisme que garage_opening_hours, adapté à MarketSpaceAccount
     * (module Profil Market Space, ajout v0.10).
     */
    public function up(): void
    {
        Schema::create('market_space_opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_space_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();

            $table->unique(['market_space_account_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_space_opening_hours');
    }
};
