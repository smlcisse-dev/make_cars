<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
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
            // Exactement l'un des deux : garage OU boutique Market Space.
            'garage_id' => ['required_without:market_space_account_id', 'prohibits:market_space_account_id', 'integer', 'exists:garages,id'],
            'market_space_account_id' => ['required_without:garage_id', 'prohibits:garage_id', 'integer', 'exists:market_space_accounts,id'],
        ];
    }
}
