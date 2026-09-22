<?php

namespace App\Http\Requests\Garage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectAppointmentRequest extends FormRequest
{
    /**
     * Réservé aux garagistes via le middleware de route (role:garagiste) ;
     * l'appartenance du RDV est vérifiée dans le contrôleur. Motif
     * obligatoire — traçabilité pour l'automobiliste, même principe que les
     * rejets/suspensions admin.
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
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
