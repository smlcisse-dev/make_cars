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
            'garage_id' => ['required', 'integer', 'exists:garages,id'],
        ];
    }
}
