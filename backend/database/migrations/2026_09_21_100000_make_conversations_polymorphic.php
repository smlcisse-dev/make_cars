<?php

use App\Models\Garage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Généralise `conversations` sur le même modèle polymorphe que
 * `products`/`orders` (sellable_type/sellable_id) pour que le Market Space
 * ait lui aussi un chat avec les automobilistes. Les conversations
 * existantes (toutes Garage ↔ Automobiliste) sont conservées : leur
 * `garage_id` est recopié dans `sellable_id` avant d'être supprimé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('sellable_type')->nullable()->after('id');
            $table->unsignedBigInteger('sellable_id')->nullable()->after('sellable_type');
        });

        DB::table('conversations')->update([
            'sellable_type' => (new Garage)->getMorphClass(),
            'sellable_id' => DB::raw('garage_id'),
        ]);

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['garage_id', 'user_id']);
            $table->dropForeign(['garage_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('garage_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('sellable_type')->nullable(false)->change();
            $table->unsignedBigInteger('sellable_id')->nullable(false)->change();
            $table->unique(['sellable_type', 'sellable_id', 'user_id']);
            $table->index(['sellable_type', 'sellable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['sellable_type', 'sellable_id', 'user_id']);
            $table->dropIndex(['sellable_type', 'sellable_id']);
            $table->unsignedBigInteger('garage_id')->nullable()->after('id');
        });

        // Les conversations Market Space n'ont pas d'équivalent avant cette
        // migration : elles sont supprimées au retour arrière.
        DB::table('conversations')->where('sellable_type', '!=', (new Garage)->getMorphClass())->delete();
        DB::table('conversations')->update(['garage_id' => DB::raw('sellable_id')]);

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['sellable_type', 'sellable_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('garage_id')->nullable(false)->change();
            $table->foreign('garage_id')->references('id')->on('garages')->cascadeOnDelete();
            $table->unique(['garage_id', 'user_id']);
        });
    }
};
