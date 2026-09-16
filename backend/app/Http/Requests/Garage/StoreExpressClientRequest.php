<?php

namespace App\Http\Requests\Garage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création/rattachement d'un compte automobiliste "express" pour un client
 * walk-in sans app (CLAUDE.md §5, ajout v0.9). L'email reste obligatoire
 * pour ce flux précis même si la colonne est nullable en base — l'inscription
 * classique (RegisterAutomobilisteRequest) n'est pas concernée par cet
 * assouplissement.
 */
class StoreExpressClientRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ];
    }
}
