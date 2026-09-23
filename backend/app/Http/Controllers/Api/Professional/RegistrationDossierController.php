<?php

namespace App\Http\Controllers\Api\Professional;

use App\Enums\RegistrationDocumentType;
use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\BuildsProfessionalProfileMeta;
use App\Http\Requests\Professional\UpdateLegalInfoRequest;
use App\Http\Requests\Professional\UploadLegalDocumentRequest;
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

    public function uploadDocument(UploadLegalDocumentRequest $request): JsonResponse
    {
        return $this->storeDocument($request, RegistrationDocumentType::BusinessRegistration, 'Document du registre de commerce enregistré.');
    }

    public function downloadDocument(Request $request): StreamedResponse
    {
        return $this->downloadLatest($request, RegistrationDocumentType::BusinessRegistration, 'Aucun document du registre de commerce n\'a encore été envoyé.');
    }

    /**
     * Certificat d'Identification Personnelle (CLAUDE.md §5, ajout v0.27) :
     * mêmes règles et mêmes verrous que le document du registre de commerce.
     */
    public function uploadIdentityDocument(UploadLegalDocumentRequest $request): JsonResponse
    {
        return $this->storeDocument($request, RegistrationDocumentType::IdentityCertificate, 'Certificat d\'Identification Personnelle enregistré.');
    }

    public function downloadIdentityDocument(Request $request): StreamedResponse
    {
        return $this->downloadLatest($request, RegistrationDocumentType::IdentityCertificate, 'Aucun Certificat d\'Identification Personnelle n\'a encore été envoyé.');
    }

    public function submit(Request $request): JsonResponse
    {
        $registration = $this->registrationService->submit($this->registration($request));

        return $this->success(null, 'Votre dossier a été soumis pour validation.', meta: $this->registrationMeta($registration));
    }

    private function storeDocument(UploadLegalDocumentRequest $request, RegistrationDocumentType $type, string $message): JsonResponse
    {
        $registration = $this->registration($request);
        $this->registrationService->replaceLegalDocument($registration, $type, $request->file('document'));

        return $this->success(null, $message, 201, meta: $this->registrationMeta($registration));
    }

    private function downloadLatest(Request $request, RegistrationDocumentType $type, string $notFoundMessage): StreamedResponse
    {
        $document = $this->registration($request)->latestDocumentOfType($type)->first();

        abort_if($document === null, 404, $notFoundMessage);

        return Storage::disk($document->disk)->download($document->path);
    }

    private function registration(Request $request): ProfessionalRegistration
    {
        $registration = $request->user()->professionalRegistration;

        abort_if($registration === null, 404, 'Dossier d\'inscription introuvable pour ce compte. Contactez le support.');

        return $registration;
    }
}
