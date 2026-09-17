<?php

namespace App\Http\Requests\Mobile;

use App\Enums\ServiceCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recherche de garages/Market Space (CLAUDE.md §5, ajouts v0.13 et v0.14) :
 * position, nom, service et tri sont tous optionnels et combinables — seuls
 * le rayon et le tri par distance exigent une position, puisqu'ils n'ont pas
 * de sens sans elle.
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude,radius_km', 'required_if:sort,distance'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude,radius_km', 'required_if:sort,distance'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1'],
            'type' => ['nullable', Rule::in(['garage', 'market_space', 'both'])],
            'name' => ['nullable', 'string', 'max:255'],
            'service_category' => ['nullable', Rule::enum(ServiceCategory::class)],
            'service_id' => ['nullable', 'integer', 'exists:repair_services,id'],
            'sort' => ['nullable', Rule::in(['distance', 'rating'])],
        ];
    }
}
