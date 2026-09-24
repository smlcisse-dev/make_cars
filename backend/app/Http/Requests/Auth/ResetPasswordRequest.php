<?php

namespace App\Http\Requests\Auth;

use App\Support\EmailCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Nouveau mot de passe avec le code reçu par email (CLAUDE.md §5, ajout
     * v0.29) : publique, l'email et le code font office de preuve.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Un code mal formé est refusé ici, sans consommer d'essai.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', EmailCode::formatRule()],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => EmailCode::formatMessage(),
        ];
    }
}
