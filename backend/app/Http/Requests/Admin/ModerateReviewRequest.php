<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Masquage d'un avis abusif/diffamatoire — motif obligatoire, même logique
 * que le rejet/la suspension d'un compte professionnel (CLAUDE.md §5, ajout
 * v0.10). Réservé aux admins via le middleware de route (role:admin).
 */
class ModerateReviewRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
