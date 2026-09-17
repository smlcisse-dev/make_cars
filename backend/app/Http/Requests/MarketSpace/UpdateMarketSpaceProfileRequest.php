<?php

namespace App\Http\Requests\MarketSpace;

use App\Enums\City;
use App\Enums\Region;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarketSpaceProfileRequest extends FormRequest
{
    /**
     * Réservé aux comptes Market Space via le middleware de route (role:market_space).
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', Rule::enum(City::class)],
            'region' => ['nullable', Rule::enum(Region::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
