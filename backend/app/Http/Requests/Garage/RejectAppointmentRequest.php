<?php

namespace App\Http\Requests\Garage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectAppointmentRequest extends FormRequest
{
    /**
     * Réservé aux garagistes via le middleware de route (role:garagiste) ;
     * l'appartenance du RDV est vérifiée dans le contrôleur. Motif optionnel
     * (contrairement aux rejets admin) — le cahier des charges ne l'exige
     * pas pour un refus de RDV entre garagiste et client.
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
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
