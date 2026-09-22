<?php

namespace App\Http\Requests\Service;

use App\Enums\ServiceCategory;
use App\Models\RepairService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    /**
     * Réservé aux garagistes via le middleware de route (role:garagiste) ;
     * l'appartenance du service est vérifiée dans le contrôleur.
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
        /** @var RepairService $service */
        $service = $this->route('service');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'image' => [
                Rule::requiredIf(fn () => $service->image_path === null),
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120',
            ],
        ];
    }
}
