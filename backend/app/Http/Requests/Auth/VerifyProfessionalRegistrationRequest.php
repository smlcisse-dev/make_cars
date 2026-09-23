<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyProfessionalRegistrationRequest extends FormRequest
{
    /**
     * Saisie du code reçu par email (CLAUDE.md §5, ajout v0.26) : publique,
     * l'identifiant de la demande (uuid) et le code font office de preuve.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Un code mal formé est refusé ici, sans consommer d'essai : seul un code
     * au bon format mais faux décrémente le compteur.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $length = config('registration.code_length');

        return [
            'code' => ['required', 'string', "regex:/^\\d{{$length}}$/"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Le code doit comporter '.config('registration.code_length').' chiffres.',
        ];
    }
}
