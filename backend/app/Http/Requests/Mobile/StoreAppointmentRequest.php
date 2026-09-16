<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
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
            'garage_id' => ['required', 'integer', 'exists:garages,id'],
            'repair_service_id' => ['nullable', 'integer', 'exists:repair_services,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'requested_at' => ['required', 'date', 'after:now'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('repair_service_id') && ! $this->filled('description')) {
                $validator->errors()->add(
                    'description',
                    'Précisez un service du catalogue et/ou une description de votre besoin.'
                );
            }
        });
    }
}
