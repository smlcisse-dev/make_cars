<?php

namespace App\Http\Requests\DeviceToken;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement/mise à jour du jeton FCM de l'appareil courant, partagé
 * entre Mobile/Garage/Market Space — même mécanisme quel que soit le rôle du
 * compte authentifié (CLAUDE.md §5, ajout v0.12).
 */
class StoreDeviceTokenRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ];
    }
}
