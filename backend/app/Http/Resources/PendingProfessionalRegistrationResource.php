<?php

namespace App\Http\Resources;

use App\Models\PendingProfessionalRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Demande d'inscription en attente de vérification (CLAUDE.md §5, ajout
 * v0.26) : seul l'uuid sert d'identifiant public, jamais l'id interne.
 *
 * @mixin PendingProfessionalRegistration
 */
class PendingProfessionalRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'verification_id' => $this->uuid,
            'email' => $this->email,
            'code_expires_at' => $this->code_expires_at->toIso8601String(),
            'resend_available_at' => $this->resendAvailableAt()->toIso8601String(),
        ];
    }
}
