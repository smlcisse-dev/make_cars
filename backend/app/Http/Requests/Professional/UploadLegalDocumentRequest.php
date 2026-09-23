<?php

namespace App\Http\Requests\Professional;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Justificatif légal du dossier d'inscription : document du registre de
 * commerce (CLAUDE.md §5, ajout v0.26) ou Certificat d'Identification
 * Personnelle (ajout v0.27) — mêmes règles pour les deux.
 */
class UploadLegalDocumentRequest extends FormRequest
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
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
