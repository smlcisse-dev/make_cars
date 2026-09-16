<?php

namespace App\Http\Requests\Admin;

use App\Models\ProfessionalRegistration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SuspendRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProfessionalRegistration $registration */
        $registration = $this->route('registration');

        return $this->user()->can('suspend', $registration);
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
