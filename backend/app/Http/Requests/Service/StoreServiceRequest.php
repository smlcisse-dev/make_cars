<?php

namespace App\Http\Requests\Service;

use App\Enums\ServiceCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    /**
     * Réservé aux garagistes via le middleware de route (role:garagiste).
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
            'description' => ['required', 'string', 'max:500'],
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
