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
 * `client_id` n'est utilisé que par la création directe (sans RDV) — ignoré
 * par la création scopée à un RDV, où le client est déduit de celui-ci.
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
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
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
