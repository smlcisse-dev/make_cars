<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Achat isolé de pièces/produits, sans prestation associée (CLAUDE.md §5,
 * règle 11 et ajout v0.9). Le vendeur (Garage ou Market Space) est déduit
 * des produits par OrderService, pas transmis par le client.
 */
class StoreOrderRequest extends FormRequest
{
    /**
     * Réservé aux automobilistes via le middleware de route (role:automobiliste).
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
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
