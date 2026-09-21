<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
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
    public function __construct(private readonly ProfessionalRegistrationService $registrationService) {}

    /**
     * Liste paginée : renvoyée via l'enveloppe standard de Laravel
     * (data/links/meta), pas via notre success() maison réservé aux
     * réponses ponctuelles (ressource unique, message de confirmation).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProfessionalRegistration::class);

        $registrations = ProfessionalRegistration::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->with(['documents', 'user'])
            ->latest()
            ->paginate();

        return ProfessionalRegistrationResource::collection($registrations);
    }

    public function show(ProfessionalRegistration $registration): JsonResponse
    {
        Gate::authorize('view', $registration);

        return $this->success(new ProfessionalRegistrationResource($registration->load(['documents', 'user'])));
    }

    public function approve(Request $request, ProfessionalRegistration $registration): JsonResponse
    {
        Gate::authorize('review', $registration);

        $registration = $this->registrationService->approve($registration, $request->user())
            ->load(['documents', 'user']);

        return $this->success(new ProfessionalRegistrationResource($registration), 'Inscription validée.');
    }

    public function reject(RejectRegistrationRequest $request, ProfessionalRegistration $registration): JsonResponse
    {
        $registration = $this->registrationService->reject($registration, $request->user(), $request->string('reason')->toString())
            ->load(['documents', 'user']);

        return $this->success(new ProfessionalRegistrationResource($registration), 'Inscription rejetée.');
    }

    /**
     * Suspend un compte déjà validé (fraude, plaintes, pièces de mauvaise
     * qualité) — motif obligatoire, comme un rejet (CLAUDE.md §5, ajout v0.6).
     */
    public function suspend(SuspendRegistrationRequest $request, ProfessionalRegistration $registration): JsonResponse
    {
        $registration = $this->registrationService->suspend($registration, $request->user(), $request->string('reason')->toString())
            ->load(['documents', 'user']);

        return $this->success(new ProfessionalRegistrationResource($registration), 'Compte suspendu.');
    }

    public function reactivate(Request $request, ProfessionalRegistration $registration): JsonResponse
    {
        Gate::authorize('reactivate', $registration);

        $registration = $this->registrationService->reactivate($registration)->load(['documents', 'user']);

        return $this->success(new ProfessionalRegistrationResource($registration), 'Compte réactivé.');
    }

    public function downloadDocument(ProfessionalRegistration $registration, RegistrationDocument $document): StreamedResponse
    {
        Gate::authorize('view', $registration);
        abort_unless($document->professional_registration_id === $registration->id, 404);

        return Storage::disk($document->disk)->download($document->path);
    }
}
