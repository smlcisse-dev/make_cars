<?php

namespace App\Http\Requests\Dispute;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Réponse du professionnel dans l'espace d'échange dédié à une réclamation
 * (CLAUDE.md §5, ajout v0.11). Réservé au Garage/Market Space concerné,
 * vérifié dans le contrôleur.
 */
class RespondDisputeRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
