<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\RegistrationDocumentType;
use App\Enums\RegistrationStatus;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProfessionalRegistrationService
{
    public function __construct(
        private readonly GarageService $garageService,
        private readonly MarketSpaceAccountService $marketSpaceAccountService,
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
     * @param  array{name: string, email: string, phone: ?string, password: string, account_type: string, structure_name: string, address: string, business_registration_number: string}  $data
     * @param  UploadedFile[]  $premisesPhotos
     */
    public function register(array $data, UploadedFile $businessRegistrationDocument, array $premisesPhotos): User
    {
        return DB::transaction(function () use ($data, $businessRegistrationDocument, $premisesPhotos) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => AccountType::from($data['account_type']),
            ]);

            $registration = $user->professionalRegistration()->create([
                'structure_name' => $data['structure_name'],
                'address' => $data['address'],
                'business_registration_number' => $data['business_registration_number'],
                'status' => RegistrationStatus::Pending,
            ]);

            $this->storeDocument($registration, $businessRegistrationDocument, RegistrationDocumentType::BusinessRegistration);

            foreach ($premisesPhotos as $photo) {
                $this->storeDocument($registration, $photo, RegistrationDocumentType::PremisesPhoto);
            }

            return $user;
        });
    }

    private function storeDocument(ProfessionalRegistration $registration, UploadedFile $file, RegistrationDocumentType $type): void
    {
        $disk = $this->disk();
        $path = $file->store('registration-documents/'.$registration->id, $disk);

        $registration->documents()->create([
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
        ]);
    }

    public function approve(ProfessionalRegistration $registration, User $admin): ProfessionalRegistration
    {
        $registration->update([
            'status' => RegistrationStatus::Approved,
            'rejection_reason' => null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // Le profil Garage (ou Market Space) est le pendant "public" du
        // dossier KYC ; il est pré-rempli ici puis complété par le
        // professionnel (géoloc, horaires, photos — voir GarageService).
        if ($registration->user->role === AccountType::Garagiste && ! $registration->user->garage) {
            $this->garageService->createFromRegistration($registration);
        }

        if ($registration->user->role === AccountType::MarketSpace && ! $registration->user->marketSpaceAccount) {
            $this->marketSpaceAccountService->createFromRegistration($registration);
        }

        return $registration;
    }

    public function reject(ProfessionalRegistration $registration, User $admin, string $reason): ProfessionalRegistration
    {
        $registration->update([
            'status' => RegistrationStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $registration;
    }
}
