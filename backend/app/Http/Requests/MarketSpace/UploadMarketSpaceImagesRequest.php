<?php

namespace App\Http\Requests\MarketSpace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadMarketSpaceImagesRequest extends FormRequest
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
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
