<?php

namespace App\Http\Requests\Admin;

use App\Models\RepairService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RepairService $service */
        $service = $this->route('service');

        return $this->user()->can('review', $service);
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
