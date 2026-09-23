<?php

namespace App\Http\Resources;

use App\Enums\AccountType;
use App\Enums\ReactivationRequestStatus;
use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProfessionalRegistration
 */
class ProfessionalRegistrationResource extends JsonResource
{
    /**
     * `structure_name` et `address` ne sont plus stockés sur le dossier mais
     * lus depuis le profil (CLAUDE.md §5, ajout v0.26) : les clés sont
     * conservées pour que le dashboard Admin existant continue de fonctionner
     * sans modification. IFU, NPI, profil détaillé et historique des
     * décisions ne sont ajoutés que pour un administrateur (fiche de
     * dossier) — cette ressource est aussi embarquée dans UserResource
     * (/auth/me, connexion).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->relationLoaded('user') ? $this->user?->professionalProfile() : null;
        $isAdmin = $request->user()?->role === AccountType::Admin;

        return [
            'id' => $this->id,
            'account_type' => $this->whenLoaded('user', fn () => $this->user->role->value),
            'account_type_label' => $this->whenLoaded('user', fn () => $this->user->role->label()),
            'structure_name' => $profile?->name,
            'address' => $profile?->address,
            'business_registration_number' => $this->business_registration_number,
            'ifu' => $this->when($isAdmin, fn () => $this->ifu),
            'npi' => $this->when($isAdmin, fn () => $this->npi),
            'legal_status' => $this->when($isAdmin, fn () => $this->legalStatus()),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'is_suspended' => $this->isSuspended(),
            'suspension_reason' => $this->suspension_reason,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            // Demande de réactivation (CLAUDE.md §5, ajout v0.28) : la
            // dernière, pour le professionnel (bandeau de suspension) comme
            // pour l'admin. Une seule demande peut être en attente, donc la
            // dernière suffit à savoir s'il y en a une.
            'latest_reactivation_request' => $this->whenLoaded(
                'latestReactivationRequest',
                fn () => $this->latestReactivationRequest ? new ReactivationRequestResource($this->latestReactivationRequest) : null,
            ),
            // `when(relationLoaded)` plutôt que `whenLoaded` : ce dernier
            // renvoie null (et non false) quand il n'y a aucune demande.
            'has_pending_reactivation_request' => $this->when(
                $this->relationLoaded('latestReactivationRequest'),
                fn () => $this->latestReactivationRequest?->status === ReactivationRequestStatus::Pending,
            ),
            // Historique complet, du plus récent au plus ancien : fiche admin.
            'reactivation_requests' => $this->when(
                $isAdmin && $this->relationLoaded('reactivationRequests'),
                fn () => ReactivationRequestResource::collection($this->reactivationRequests),
            ),
            'documents' => RegistrationDocumentResource::collection($this->whenLoaded('documents')),
            'decisions' => $this->when(
                $isAdmin && $this->relationLoaded('decisions'),
                fn () => RegistrationDecisionResource::collection($this->decisions),
            ),
            // Profil complet (infos, localisation, horaires, photos) : fiche
            // admin uniquement, quand le contrôleur en a chargé le détail.
            'profile' => $this->when(
                $isAdmin && $profile !== null && $profile->relationLoaded('openingHours'),
                fn () => $profile instanceof Garage ? new GarageResource($profile) : new MarketSpaceAccountResource($profile),
            ),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
