<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Période optionnelle pour filtrer le volume d'activité des statistiques
 * agrégées admin (CLAUDE.md §5, ajout v0.17) — totaux depuis le début si
 * absente.
 */
class StatisticsRequest extends FormRequest
{
    /**
     * Réservé aux administrateurs via le middleware de route (role:admin).
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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
