<?php

namespace App\Http\Resources;

use App\Models\RegistrationDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RegistrationDocument
 */
class RegistrationDocumentResource extends JsonResource
{
    /**
     * Le fichier lui-même reste privé (disque non public) : il se récupère
     * via l'endpoint de téléchargement dédié, pas par une URL directe.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'download_url' => route('admin.registrations.documents.download', [
                'registration' => $this->professional_registration_id,
                'document' => $this->id,
            ]),
        ];
    }
}
