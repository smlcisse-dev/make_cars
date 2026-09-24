<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteEmailDecisionRequest extends FormRequest
{
    /**
     * Route publique protégée par le middleware `signed:relative` (pas de
     * Sanctum) : la signature fait office d'authentification.
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
            'decision' => ['required', 'string', Rule::in(['accept', 'reject'])],
        ];
    }
}
