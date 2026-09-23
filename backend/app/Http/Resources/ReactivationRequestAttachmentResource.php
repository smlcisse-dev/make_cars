<?php

namespace App\Http\Resources;

use App\Enums\AccountType;
use App\Models\ReactivationRequestAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pièce jointe d'une demande de réactivation (CLAUDE.md §5, ajout v0.28). Le
 * fichier reste privé : il se récupère par l'endpoint de téléchargement de
 * l'espace de celui qui consulte (admin, ou le professionnel propriétaire).
 *
 * @mixin ReactivationRequestAttachment
 */
class ReactivationRequestAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'download_url' => $this->downloadUrl($request),
        ];
    }

    private function downloadUrl(Request $request): ?string
    {
        $parameters = ['reactivationRequest' => $this->reactivation_request_id, 'attachment' => $this->id];

        return match ($request->user()?->role) {
            AccountType::Admin => route('admin.registrations.reactivation-requests.attachments.download', [
                'registration' => $this->reactivationRequest->professional_registration_id,
                ...$parameters,
            ]),
            AccountType::Garagiste => route('garage.reactivation-requests.attachments.download', $parameters),
            AccountType::MarketSpace => route('market-space.reactivation-requests.attachments.download', $parameters),
            default => null,
        };
    }
}
