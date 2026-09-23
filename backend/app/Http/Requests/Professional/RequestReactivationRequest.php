<?php

namespace App\Http\Requests\Professional;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Demande de réactivation d'un compte suspendu (CLAUDE.md §5, ajout v0.28) :
 * le professionnel explique ce qu'il a corrigé, et peut joindre des preuves.
 *
 * Pièces jointes : 5 au plus, 5 Mo chacune. Cinq fichiers de 10 Mo
 * atteindraient 50 Mo, exactement `post_max_size` (PASSATION.md §4), et PHP
 * refuserait la requête avant Laravel ; à 5 Mo, une demande complète reste
 * sous 25 Mo.
 */
class RequestReactivationRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachments.max' => '5 pièces jointes au maximum.',
            'attachments.*.mimes' => 'Chaque pièce jointe doit être une photo (jpg, png) ou un PDF.',
            'attachments.*.max' => 'Chaque pièce jointe ne doit pas dépasser 5 Mo.',
        ];
    }
}
