<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Réclamation sur une transaction terminée (devis facturé ou commande
 * payée) — la cible et l'éligibilité sont résolues par DisputeService, pas
 * transmises par le client (CLAUDE.md §5, ajout v0.11). Photos en preuve
 * optionnelles, réutilisant le mécanisme d'upload du chat.
 */
class StoreDisputeRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
