<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ReactivationRequestStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Admin\RefuseReactivationRequestRequest;
use App\Http\Requests\Admin\RejectRegistrationRequest;
use App\Http\Requests\Admin\SuspendRegistrationRequest;
use App\Http\Resources\ProfessionalRegistrationResource;
use App\Models\ProfessionalRegistration;
use App\Models\RegistrationDocument;
use App\Services\ProfessionalRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    /**
     * Le profil est chargé pour exposer `structure_name`/`address`, lus
     * depuis lui (CLAUDE.md §5, ajout v0.26).
     */
    private const LIST_RELATIONS = ['documents', 'user.garage', 'user.marketSpaceAccount', 'latestReactivationRequest'];

    /**
     * Fiche d'examen : profil complet, documents et historique des décisions.
     */
    private const DETAIL_RELATIONS = [
        'documents',
        'decisions.decidedBy',
        'latestReactivationRequest',
        'reactivationRequests.decidedBy',
        'user.garage.openingHours', 'user.garage.images', 'user.garage.department', 'user.garage.commune', 'user.garage.arrondissement',
        'user.marketSpaceAccount.openingHours', 'user.marketSpaceAccount.images', 'user.marketSpaceAccount.department',
        'user.marketSpaceAccount.commune', 'user.marketSpaceAccount.arrondissement',
    ];

    public function __construct(private readonly ProfessionalRegistrationService $registrationService) {}

    /**
     * Liste paginée : renvoyée via l'enveloppe standard de Laravel
     * (data/links/meta), pas via notre success() maison réservé aux
     * réponses ponctuelles (ressource unique, message de confirmation).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProfessionalRegistration::class);

        // Par défaut, les dossiers encore en cours de remplissage par le
        // professionnel (`profile_incomplete`) sont exclus : rien à examiner
        // (CLAUDE.md §5, ajout v0.26). Le filtre `status` permet de les voir.
        $registrations = ProfessionalRegistration::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
                fn ($query) => $query->where('status', '!=', RegistrationStatus::ProfileIncomplete),
            )
            // Dossiers ayant une demande de réactivation en attente (v0.28).
            ->when(
                $request->boolean('reactivation_requested'),
                fn ($query) => $query->whereHas('reactivationRequests', fn ($requests) => $requests->where('status', ReactivationRequestStatus::Pending)),
            )
            ->with(self::LIST_RELATIONS)
            ->latest()
            ->paginate();

        return ProfessionalRegistrationResource::collection($registrations);
    }

    public function show(ProfessionalRegistration $registration): JsonResponse
    {
        Gate::authorize('view', $registration);

        return $this->success(new ProfessionalRegistrationResource($registration->load(self::DETAIL_RELATIONS)));
    }

    public function approve(Request $request, ProfessionalRegistration $registration): JsonResponse
    {
        Gate::authorize('review', $registration);

        $registration = $this->registrationService->approve($registration, $request->user())
            ->load(self::LIST_RELATIONS);

        return $this->success(new ProfessionalRegistrationResource($registration), 'Inscription validée.');
    }

    public function reject(RejectRegistrationRequest $request, ProfessionalRegistration $registration): JsonResponse
    {
        $registration = $this->registrationService->reject($registration, $request->user(), $request->string('reason')->toString())
            ->load(self::LIST_RELATIONS);

        return $this->success(new ProfessionalRegistrationResource($registration), 'Inscription rejetée.');
    }

    /**
     * Suspend un compte déjà validé (fraude, plaintes, pièces de mauvaise
     * qualité) — motif obligatoire, comme un rejet (CLAUDE.md §5, ajout v0.6).
     */
    public function suspend(SuspendRegistrationRequest $request, ProfessionalRegistration $registration): JsonResponse
    {
        $registration = $this->registrationService->suspend($registration, $request->user(), $request->string('reason')->toString())
            ->load(self::LIST_RELATIONS);

        return $this->success(new ProfessionalRegistrationResource($registration), 'Compte suspendu.');
    }

    public function reactivate(Request $request, ProfessionalRegistration $registration): JsonResponse
    {
        Gate::authorize('reactivate', $registration);

        $registration = $this->registrationService->reactivate($registration, $request->user())->load(self::LIST_RELATIONS);

        return $this->success(new ProfessionalRegistrationResource($registration), 'Compte réactivé.');
    }

    /**
     * Refuse la demande de réactivation en attente (CLAUDE.md §5, ajout
     * v0.28) : motif obligatoire, le compte reste suspendu.
     */
    public function refuseReactivationRequest(RefuseReactivationRequestRequest $request, ProfessionalRegistration $registration): JsonResponse
    {
        $this->registrationService->refuseReactivationRequest($registration, $request->user(), $request->string('reason')->toString());

        return $this->success(
            new ProfessionalRegistrationResource($registration->load(self::DETAIL_RELATIONS)),
            'Demande de réactivation refusée.',
        );
    }

    public function downloadDocument(ProfessionalRegistration $registration, RegistrationDocument $document): StreamedResponse
    {
        Gate::authorize('view', $registration);
        abort_unless($document->professional_registration_id === $registration->id, 404);

        return Storage::disk($document->disk)->download($document->path);
    }
}
