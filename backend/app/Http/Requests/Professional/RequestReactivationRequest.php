<?php

namespace App\Http\Requests\Professional;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Demande de réactivation d'un compte suspendu (CLAUDE.md §5, ajout v0.28) :
 * le professionnel explique ce qu'il a corrigé.
 */
class RequestReactivationRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
