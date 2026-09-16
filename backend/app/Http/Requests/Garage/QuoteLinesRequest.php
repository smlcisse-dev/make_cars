<?php

namespace App\Http\Requests\Garage;

use App\Enums\QuoteLineType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lignes d'une version de devis (création, édition en brouillon, ou
 * renégociation — même forme dans les trois cas). Le prix des lignes
 * service/produit n'est jamais accepté depuis le client : il est relu
 * depuis le catalogue du garage au moment de la résolution (QuoteService).
 */
class QuoteLinesRequest extends FormRequest
{
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
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.type' => ['required', Rule::enum(QuoteLineType::class)],
            'lines.*.repair_service_id' => ['required_if:lines.*.type,service', 'integer'],
            'lines.*.product_id' => ['required_if:lines.*.type,product', 'integer'],
            'lines.*.label' => ['nullable', 'string', 'max:255'],
            'lines.*.unit_price' => ['required_if:lines.*.type,diagnosis_fee', 'numeric', 'min:0'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
