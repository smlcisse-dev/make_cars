<?php

use Database\Seeders\LocationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['garages', 'market_space_accounts'];

    /**
     * Remplace les champs `city`/`region` (enums) par la hiérarchie
     * Département → Commune → Arrondissement + quartier libre (CLAUDE.md §5,
     * ajout v0.19). Les valeurs existantes sont converties au mieux :
     * région → département, ville → commune (même slug), le reste est perdu
     * (null) — l'arrondissement n'existait pas.
     */
    public function up(): void
    {
        (new LocationSeeder)->run();

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('address')->constrained()->nullOnDelete();
                $table->foreignId('commune_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
                $table->foreignId('arrondissement_id')->nullable()->after('commune_id')->constrained()->nullOnDelete();
                $table->string('neighborhood')->nullable()->after('arrondissement_id');
            });

            $this->convertExistingValues($tableName);

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['city', 'region']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('city')->nullable()->after('address');
                $table->string('region')->nullable()->after('city');
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('arrondissement_id');
                $table->dropConstrainedForeignId('commune_id');
                $table->dropConstrainedForeignId('department_id');
                $table->dropColumn('neighborhood');
            });
        }
    }

    private function convertExistingValues(string $tableName): void
    {
        $departments = DB::table('departments')->pluck('id', 'slug');
        $communes = DB::table('communes')->get()->groupBy('slug');

        foreach (DB::table($tableName)->select('id', 'city', 'region')->get() as $row) {
            $departmentId = $row->region !== null ? ($departments[str_replace('_', '-', $row->region)] ?? null) : null;
            $communeId = null;

            if ($row->city !== null) {
                $matches = $communes->get(str_replace('_', '-', $row->city), collect())
                    ->when($departmentId !== null, fn ($c) => $c->where('department_id', $departmentId));
                $commune = $matches->first();

                if ($commune !== null) {
                    $communeId = $commune->id;
                    $departmentId ??= $commune->department_id;
                }
            }

            if ($departmentId !== null || $communeId !== null) {
                DB::table($tableName)->where('id', $row->id)->update([
                    'department_id' => $departmentId,
                    'commune_id' => $communeId,
                ]);
            }
        }
    }
};
