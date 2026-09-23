<?php

namespace App\Services;

use App\Enums\RegistrationDocumentType;
use App\Enums\RegistrationStatus;
use App\Exceptions\ApiException;
use App\Mail\RegistrationApprovedMail;
use App\Mail\RegistrationRejectedMail;
use App\Models\ProfessionalRegistration;
use App\Models\RegistrationDocument;
use App\Models\User;
use App\Support\FrontendUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Dossier d'inscription professionnelle (KYC) après la création du compte
 * (CLAUDE.md §5, ajout v0.26) : informations légales privées, soumission par
 * le professionnel, décision de l'administrateur, suspension (ajout v0.6).
 * La création du compte elle-même relève de ProfessionalSignupService.
 */
class ProfessionalRegistrationService
{
    public function __construct(
        private readonly PushNotificationService $notificationService,
    ) {}

    /**
     * Justificatifs stockés sur un disque configurable (local en dev, bascule
     * vers Supabase Storage une fois les identifiants fournis — CLAUDE.md §4).
     */
    private function disk(): string
    {
        return config('filesystems.kyc_documents_disk', 'local');
    }

    /**
     * @param  array{business_registration_number: string, ifu: string, npi: string}  $data
     */
    public function updateLegalInfo(ProfessionalRegistration $registration, array $data): ProfessionalRegistration
    {
        $this->assertLegalInfoEditable($registration);

        $registration->update($data);

        return $registration;
    }

    /**
     * Un seul document du registre de commerce à la fois : le nouveau
     * remplace le précédent, dont le fichier est supprimé du disque.
     */
    public function replaceBusinessRegistrationDocument(ProfessionalRegistration $registration, UploadedFile $file): RegistrationDocument
    {
        $this->assertLegalInfoEditable($registration);

        $previous = $registration->documents()->where('type', RegistrationDocumentType::BusinessRegistration)->get();

        $disk = $this->disk();
        $document = $registration->documents()->create([
            'type' => RegistrationDocumentType::BusinessRegistration,
            'disk' => $disk,
            'path' => $file->store('registration-documents/'.$registration->id, $disk),
        ]);

        foreach ($previous as $old) {
            Storage::disk($old->disk)->delete($old->path);
            $old->delete();
        }

        return $document;
    }

    /**
     * « Soumettre pour validation » : exige un profil public complet (v0.20)
     * et toutes les informations légales. `rejection_reason` est conservé
     * jusqu'à la prochaine décision, pour que l'admin voie le motif précédent.
     *
     * @throws ApiException statut ne permettant pas la soumission (409), ou dossier incomplet (422).
     */
    public function submit(ProfessionalRegistration $registration): ProfessionalRegistration
    {
        if (! $registration->isSubmittable()) {
            throw new ApiException('Ce dossier ne peut pas être soumis dans son état actuel.', 409, 'invalid_status', [
                'registration_status' => $registration->status->value,
            ]);
        }

        $missingProfileFields = $registration->profile()?->missingProfileFields() ?? ['profile'];
        $missingLegalFields = $registration->missingLegalFields();

        if ($missingProfileFields !== [] || $missingLegalFields !== []) {
            throw new ApiException('Votre profil et vos informations légales doivent être complets avant la soumission.', 422, 'registration_incomplete', [
                'missing_fields' => $missingProfileFields,
                'missing_legal_fields' => $missingLegalFields,
            ]);
        }

        $registration->update([
            'status' => RegistrationStatus::Pending,
            'submitted_at' => now(),
        ]);

        return $registration;
    }

    public function approve(ProfessionalRegistration $registration, User $admin): ProfessionalRegistration
    {
        $this->assertReviewable($registration);

        DB::transaction(function () use ($registration, $admin) {
            $registration->update([
                'status' => RegistrationStatus::Approved,
                'rejection_reason' => null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->recordDecision($registration, $admin, RegistrationStatus::Approved);
        });

        $this->notificationService->notifyRegistrationApproved($registration);
        $this->sendMail($registration->user, new RegistrationApprovedMail($registration->user, FrontendUrl::login()));

        return $registration;
    }

    public function reject(ProfessionalRegistration $registration, User $admin, string $reason): ProfessionalRegistration
    {
        $this->assertReviewable($registration);

        DB::transaction(function () use ($registration, $admin, $reason) {
            $registration->update([
                'status' => RegistrationStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->recordDecision($registration, $admin, RegistrationStatus::Rejected, $reason);
        });

        $this->notificationService->notifyRegistrationRejected($registration);
        $this->sendMail($registration->user, new RegistrationRejectedMail(
            $registration->user,
            $reason,
            FrontendUrl::professionalProfile($registration->user->role),
        ));

        return $registration;
    }

    /**
     * Suspend un compte déjà validé (fraude, plaintes répétées, pièces de
     * mauvaise qualité) : le statut d'inscription reste Approved — seule la
     * visibilité mobile est coupée, l'historique (produits, services, avis)
     * est conservé pour une éventuelle réactivation (CLAUDE.md §5, ajout v0.6).
     */
    public function suspend(ProfessionalRegistration $registration, User $admin, string $reason): ProfessionalRegistration
    {
        $registration->update([
            'suspension_reason' => $reason,
            'suspended_by' => $admin->id,
            'suspended_at' => now(),
        ]);

        $this->notificationService->notifyAccountSuspended($registration);

        return $registration;
    }

    public function reactivate(ProfessionalRegistration $registration): ProfessionalRegistration
    {
        $registration->update([
            'suspension_reason' => null,
            'suspended_by' => null,
            'suspended_at' => null,
        ]);

        $this->notificationService->notifyAccountReactivated($registration);

        return $registration;
    }

    /**
     * Une fois le dossier approuvé, seul l'administrateur pourrait changer
     * les informations légales (hors périmètre) ; pendant l'examen, c'est le
     * middleware `registration.editable` qui bloque déjà toute écriture.
     */
    private function assertLegalInfoEditable(ProfessionalRegistration $registration): void
    {
        if ($registration->status === RegistrationStatus::Approved) {
            throw new ApiException('Les informations légales d\'un dossier validé ne sont plus modifiables.', 409, 'legal_info_locked');
        }
    }

    private function assertReviewable(ProfessionalRegistration $registration): void
    {
        if ($registration->status !== RegistrationStatus::Pending) {
            throw new ApiException('Seul un dossier en attente de validation peut être approuvé ou refusé.', 409, 'invalid_status', [
                'registration_status' => $registration->status->value,
            ]);
        }
    }

    private function recordDecision(ProfessionalRegistration $registration, User $admin, RegistrationStatus $decision, ?string $reason = null): void
    {
        $registration->decisions()->create([
            'decision' => $decision,
            'reason' => $reason,
            'decided_by' => $admin->id,
            'decided_at' => now(),
        ]);
    }

    private function sendMail(User $user, Mailable $mail): void
    {
        if ($user->email !== null) {
            Mail::to($user->email)->send($mail);
        }
    }
}
