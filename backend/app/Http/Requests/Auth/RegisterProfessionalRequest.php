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
     * Inscription professionnelle : ouverte à tout visiteur non authentifié,
     * mais le dossier créé reste en attente de validation admin
     * (CLAUDE.md §5, règle 4).
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', new BeninPhoneNumber, 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'account_type' => ['required', Rule::in(['garagiste', 'market_space'])],
            'structure_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'business_registration_number' => ['required', 'string', 'max:100'],
            'business_registration_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'premises_photos' => ['required', 'array', 'min:1'],
            'premises_photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
