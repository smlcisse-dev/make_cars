<?php

namespace App\Http\Requests\Admin;

use App\Enums\DisputeResolutionAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Réclamation jugée fondée — motif obligatoire et action librement choisie
 * par l'admin selon la gravité, aucune sanction automatique (CLAUDE.md §5,
 * ajout v0.11).
 */
class ResolveDisputeRequest extends FormRequest
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
            'action' => ['required', new Enum(DisputeResolutionAction::class)],
        ];
    }
}
