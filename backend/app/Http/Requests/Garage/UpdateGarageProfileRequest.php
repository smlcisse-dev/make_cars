<?php

namespace App\Http\Requests\Garage;

use App\Http\Requests\Concerns\NormalizesBeninPhone;
use App\Http\Requests\Concerns\ValidatesAdministrativeLocation;
use App\Rules\BeninPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGarageProfileRequest extends FormRequest
{
    use NormalizesBeninPhone;
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'phone' => ['required', 'string', new BeninPhoneNumber],
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
