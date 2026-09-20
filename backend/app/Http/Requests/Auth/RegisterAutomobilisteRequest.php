<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesBeninPhone;
use App\Rules\BeninPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterAutomobilisteRequest extends FormRequest
{
    use NormalizesBeninPhone;

    /**
     * Inscription grand public : ouverte à tout visiteur non authentifié.
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
        ];
    }
}
