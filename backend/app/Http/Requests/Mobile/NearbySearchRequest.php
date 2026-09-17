<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recherche par proximité (CLAUDE.md §5, ajout v0.13) : position actuelle
 * obligatoire, rayon et type de résultat optionnels.
 */
class NearbySearchRequest extends FormRequest
{
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1'],
            'type' => ['nullable', Rule::in(['garage', 'market_space', 'both'])],
        ];
    }
}
