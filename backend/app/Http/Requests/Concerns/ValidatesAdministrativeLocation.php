<?php

namespace App\Http\Requests\Concerns;

use App\Models\Arrondissement;
use App\Models\Commune;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

/**
 * Règles partagées de localisation structurée d'un profil Garage/Market
 * Space : Département → Commune → Arrondissement + quartier libre
 * (CLAUDE.md §5, ajout v0.19).
 */
trait ValidatesAdministrativeLocation
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function locationRules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'commune_id' => ['nullable', 'integer', 'exists:communes,id'],
            'arrondissement_id' => ['nullable', 'integer', 'exists:arrondissements,id'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * La localisation forme un bloc : dès qu'un niveau est envoyé, les
     * niveaux absents sont remis à null, pour ne jamais garder une commune
     * ou un arrondissement d'un ancien département (mise à jour partielle).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->hasAny(['department_id', 'commune_id', 'arrondissement_id'])) {
            return;
        }

        $this->merge(array_merge(
            ['department_id' => null, 'commune_id' => null, 'arrondissement_id' => null],
            $this->only(['department_id', 'commune_id', 'arrondissement_id']),
        ));
    }

    /**
     * Cohérence de la cascade : chaque niveau exige son parent et doit lui
     * appartenir.
     *
     * @return array<int, callable(Validator): void>
     */
    protected function locationConsistencyChecks(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $departmentId = $this->input('department_id');
            $communeId = $this->input('commune_id');
            $arrondissementId = $this->input('arrondissement_id');

            if ($communeId !== null) {
                if ($departmentId === null) {
                    $validator->errors()->add('department_id', 'Le département est requis pour choisir une commune.');
                } elseif (Commune::query()->whereKey($communeId)->where('department_id', $departmentId)->doesntExist()) {
                    $validator->errors()->add('commune_id', 'Cette commune n\'appartient pas au département choisi.');
                }
            }

            if ($arrondissementId !== null) {
                if ($communeId === null) {
                    $validator->errors()->add('commune_id', 'La commune est requise pour choisir un arrondissement.');
                } elseif (Arrondissement::query()->whereKey($arrondissementId)->where('commune_id', $communeId)->doesntExist()) {
                    $validator->errors()->add('arrondissement_id', 'Cet arrondissement n\'appartient pas à la commune choisie.');
                }
            }
        }];
    }
}
