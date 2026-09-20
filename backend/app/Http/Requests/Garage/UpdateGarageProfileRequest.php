<?php

namespace App\Http\Requests\Garage;

use App\Http\Requests\Concerns\ValidatesAdministrativeLocation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGarageProfileRequest extends FormRequest
{
    use ValidatesAdministrativeLocation;

    /**
     * Réservé aux garagistes via le middleware de route (role:garagiste).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->locationRules(),
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return $this->locationConsistencyChecks();
    }
}
