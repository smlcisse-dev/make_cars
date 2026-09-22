<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sku' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => [
                Rule::requiredIf(fn () => $product->image_path === null),
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120',
            ],
        ];
    }
}
