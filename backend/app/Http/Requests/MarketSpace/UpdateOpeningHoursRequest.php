<?php

namespace App\Http\Requests\MarketSpace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpeningHoursRequest extends FormRequest
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
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.day_of_week' => ['required', 'integer', Rule::in(range(1, 7)), 'distinct'],
            'hours.*.is_closed' => ['required', 'boolean'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * `required_if`/`after` ne se combinent pas fiablement avec des champs
     * frères sous un wildcard : la cohérence horaire est donc vérifiée ici.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->array('hours') as $index => $hour) {
                if (! ($hour['is_closed'] ?? false)) {
                    if (empty($hour['opens_at'])) {
                        $validator->errors()->add("hours.{$index}.opens_at", "L'heure d'ouverture est requise.");
                    }
                    if (empty($hour['closes_at'])) {
                        $validator->errors()->add("hours.{$index}.closes_at", "L'heure de fermeture est requise.");
                    }
                    if (! empty($hour['opens_at']) && ! empty($hour['closes_at']) && $hour['closes_at'] <= $hour['opens_at']) {
                        $validator->errors()->add("hours.{$index}.closes_at", "L'heure de fermeture doit être après l'heure d'ouverture.");
                    }
                }
            }
        });
    }
}
