<?php

namespace App\Http\Requests\Product;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le seuil d'alerte de stock bas voyage avec la correction de stock plutôt
 * qu'avec le contenu du produit (nom, prix...) : comme stock_quantity, ce
 * n'est pas une donnée qui remet en cause la validation admin déjà accordée
 * (CLAUDE.md §5, ajout v0.12).
 */
class UpdateProductStockRequest extends FormRequest
{
    /**
     * Réservé aux garagistes/comptes Market Space via le middleware de route
     * (role:garagiste, role:market_space) ; l'appartenance du produit est
     * vérifiée dans le contrôleur.
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
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
