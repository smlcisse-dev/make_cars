<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesBeninPhone;
use App\Rules\BeninPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterProfessionalRequest extends FormRequest
{
    use NormalizesBeninPhone;

    /**
     * Formulaire court d'inscription professionnelle (CLAUDE.md §5, ajout
     * v0.26) : ouvert à tout visiteur, ne crée qu'une demande en attente de
     * vérification de l'email — aucun compte, aucun justificatif à ce stade.
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', new BeninPhoneNumber, 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'account_type' => ['required', Rule::in(['garagiste', 'market_space'])],
        ];
    }
}
