<?php

namespace App\Http\Controllers\Api\Professional;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\BuildsProfessionalProfileMeta;
use App\Http\Requests\Professional\UpdateLegalInfoRequest;
use App\Http\Requests\Professional\UploadBusinessRegistrationDocumentRequest;
use App\Models\ProfessionalRegistration;
use App\Services\ProfessionalRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dossier d'inscription du professionnel connecté, commun aux espaces
 * Garagiste et Market Space (CLAUDE.md §5, ajout v0.26) : la logique ne porte
 * que sur le dossier KYC, identique pour les deux types de comptes — un seul
 * contrôleur plutôt qu'une copie par espace.
 */
class RegistrationDossierController extends Controller
{
    use BuildsProfessionalProfileMeta;

    public function __construct(private readonly ProfessionalRegistrationService $registrationService) {}

    public function updateLegal(UpdateLegalInfoRequest $request): JsonResponse
    {
        $registration = $this->registrationService->updateLegalInfo($this->registration($request), $request->validated());

        return $this->success(null, 'Informations légales enregistrées.', meta: $this->registrationMeta($registration));
    }

    public function uploadDocument(UploadBusinessRegistrationDocumentRequest $request): JsonResponse
    {
        $registration = $this->registration($request);
        $this->registrationService->replaceBusinessRegistrationDocument($registration, $request->file('document'));

        return $this->success(null, 'Document du registre de commerce enregistré.', 201, meta: $this->registrationMeta($registration));
    }

    public function downloadDocument(Request $request): StreamedResponse
    {
        $document = $this->registration($request)->businessRegistrationDocument()->first();

        abort_if($document === null, 404, 'Aucun document du registre de commerce n\'a encore été envoyé.');

        return Storage::disk($document->disk)->download($document->path);
    }

    public function submit(Request $request): JsonResponse
    {
        $registration = $this->registrationService->submit($this->registration($request));

        return $this->success(null, 'Votre dossier a été soumis pour validation.', meta: $this->registrationMeta($registration));
    }

    private function registration(Request $request): ProfessionalRegistration
    {
        $registration = $request->user()->professionalRegistration;

        abort_if($registration === null, 404, 'Dossier d\'inscription introuvable pour ce compte. Contactez le support.');

        return $registration;
    }
}
