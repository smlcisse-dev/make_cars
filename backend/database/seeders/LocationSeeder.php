<?php

namespace Database\Seeders;

use App\Models\Arrondissement;
use App\Models\Commune;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Charge le découpage administratif du Bénin depuis
 * database/data/benin_locations.php. Idempotent (clé naturelle = slug dans
 * son parent) : peut être relancé sans dupliquer (CLAUDE.md §5, ajout v0.19).
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<int, array{name: string, communes: array<int, array{name: string, arrondissements: array<int, string>}>}> $departments */
        $departments = require database_path('data/benin_locations.php');

        // Upserts groupés (un aller-retour par niveau) plutôt qu'un
        // updateOrCreate par ligne : ~1 300 requêtes contre une base
        // distante prenaient plusieurs minutes.
        Department::query()->upsert(
            array_map(fn (array $d) => ['name' => $d['name'], 'slug' => Str::slug($d['name'])], $departments),
            ['slug'],
            ['name'],
        );
        $departmentIds = Department::query()->pluck('id', 'slug');

        $communeRows = [];
        foreach ($departments as $departmentData) {
            foreach ($departmentData['communes'] as $communeData) {
                $communeRows[] = [
                    'department_id' => $departmentIds[Str::slug($departmentData['name'])],
                    'name' => $communeData['name'],
                    'slug' => Str::slug($communeData['name']),
                ];
            }
        }
        Commune::query()->upsert($communeRows, ['department_id', 'slug'], ['name']);
        $communeIds = Commune::query()->get(['id', 'department_id', 'slug'])
            ->mapWithKeys(fn (Commune $c) => [$c->department_id.'|'.$c->slug => $c->id]);

        $arrondissementRows = [];
        foreach ($departments as $departmentData) {
            foreach ($departmentData['communes'] as $communeData) {
                $communeId = $communeIds[$departmentIds[Str::slug($departmentData['name'])].'|'.Str::slug($communeData['name'])];
                foreach ($communeData['arrondissements'] as $name) {
                    $arrondissementRows[] = ['commune_id' => $communeId, 'name' => $name, 'slug' => Str::slug($name)];
                }
            }
        }
        foreach (array_chunk($arrondissementRows, 200) as $chunk) {
            Arrondissement::query()->upsert($chunk, ['commune_id', 'slug'], ['name']);
        }
    }
}
