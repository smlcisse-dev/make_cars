<?php

namespace App\Http\Requests\Mobile;

use App\Enums\ServiceCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recherche de garages/Market Space (CLAUDE.md §5, ajouts v0.13 à v0.16) :
 * position, nom, service, tri, nom de produit et tranche de prix sont tous
 * optionnels et combinables — seuls le rayon et le tri par distance
 * exigent une position, puisqu'ils n'ont pas de sens sans elle.
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
            'product_name' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * `gte:min_price` sur `max_price` ne convient pas ici : cette règle
     * Laravel échoue dès que le champ comparé (`min_price`) est absent, alors
     * que `max_price` seul (sans `min_price`) est un cas valide (tranche
     * ouverte vers le bas — CLAUDE.md §5, ajout v0.16). Comparaison faite
     * manuellement, seulement quand les deux sont fournis.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('min_price') && $this->filled('max_price') && (float) $this->input('min_price') > (float) $this->input('max_price')) {
                $validator->errors()->add('max_price', 'Le prix maximum doit être supérieur ou égal au prix minimum.');
            }
        });
    }
}
