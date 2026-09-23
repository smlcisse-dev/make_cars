<?php

namespace App\Http\Requests\Professional;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalInfoRequest extends FormRequest
{
    /**
     * Informations légales privées du dossier KYC (CLAUDE.md §5, ajout
     * v0.26) — l'accès est déjà restreint par les middlewares de rôle.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * IFU et NPI ne sont volontairement pas uniques : un garage qui ouvre
     * aussi une boutique (règle 2) utilise les mêmes pour ses deux comptes.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_registration_number' => ['required', 'string', 'max:100'],
            // Identifiant Fiscal Unique : 13 chiffres (décret n° 2006-201,
            // direction générale des impôts).
            'ifu' => ['required', 'string', 'regex:/^\d{13}$/'],
            // Numéro Personnel d'Identification : 10 chiffres, tel qu'imprimé
            // sur le Certificat d'Identification Personnelle de l'ANIP — à ne
            // pas confondre avec le numéro du certificat (« N° », 14 chiffres).
            'npi' => ['required', 'string', 'regex:/^\d{10}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ifu.regex' => 'L\'IFU doit comporter exactement 13 chiffres.',
            'npi.regex' => 'Le NPI doit comporter exactement 10 chiffres.',
        ];
    }
}
