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
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'commune_id' => ['required', 'integer', 'exists:communes,id'],
            'arrondissement_id' => ['required', 'integer', 'exists:arrondissements,id'],
            'neighborhood' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Cohérence de la cascade : la commune doit appartenir au département
     * choisi, l'arrondissement à la commune choisie.
     *
     * @return array<int, callable(Validator): void>
     */
    protected function locationConsistencyChecks(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (Commune::query()->whereKey($this->input('commune_id'))->where('department_id', $this->input('department_id'))->doesntExist()) {
                $validator->errors()->add('commune_id', 'Cette commune n\'appartient pas au département choisi.');

                return;
            }

            if (Arrondissement::query()->whereKey($this->input('arrondissement_id'))->where('commune_id', $this->input('commune_id'))->doesntExist()) {
                $validator->errors()->add('arrondissement_id', 'Cet arrondissement n\'appartient pas à la commune choisie.');
            }
        }];
    }
}
