<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Même mécanisme que garage_images (disque public configurable), adapté
     * à MarketSpaceAccount (module Profil Market Space, ajout v0.10).
     */
    public function up(): void
    {
        Schema::create('market_space_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_space_account_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_space_images');
    }
};
