<?php

namespace Database\Seeders\Concerns;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Models\Arrondissement;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\User;
use App\Services\GarageService;
use App\Services\MarketSpaceAccountService;
use App\Services\ProfessionalRegistrationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Comptes professionnels de test suivant le parcours v0.26 (CLAUDE.md §5) :
 * compte au dossier `profile_incomplete` avec profil vide (état atteint après
 * la vérification de l'email), puis profil public, informations légales,
 * soumission et décision admin via les services réels. Seule la
 * vérification par code est court-circuitée (pas d'email à lire dans un
 * seeder) : le compte est créé directement, email déjà vérifié.
 *
 * Suppose que la classe utilisatrice utilise aussi GeneratesFakeKycDocuments.
 */
trait SeedsProfessionalAccounts
{
    /**
     * Crée un compte professionnel au statut demandé. Mot de passe : « password ».
     *
     * @param  array{structure_name: string, address: string, neighborhood: string, image_directory: string}|null  $profile  null = profil laissé vide
     */
    private function seedProfessionalAccount(
        AccountType $accountType,
        string $email,
        string $firstName,
        string $lastName,
        string $phone,
        RegistrationStatus $status,
        ?array $profile,
        User $admin,
        ?string $rejectionReason = null,
    ): User {
        $this->deleteProfessionalAccount($email);

        $user = new User([
            'role' => $accountType,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password'),
        ]);
        $user->email_verified_at = now();
        $user->save();

        $registration = $user->professionalRegistration()->create(['status' => RegistrationStatus::ProfileIncomplete]);
        $profileModel = $accountType === AccountType::MarketSpace
            ? app(MarketSpaceAccountService::class)->createEmpty($user)
            : app(GarageService::class)->createEmpty($user);

        if ($profile === null) {
            return $user;
        }

        $this->completePublicProfile($profileModel, $profile);

        $registrationService = app(ProfessionalRegistrationService::class);
        $registrationService->updateLegalInfo($registration, [
            'business_registration_number' => 'RB/COT/24 B '.random_int(10000, 99999),
            // IFU (13 chiffres) et NPI (10 chiffres) fictifs mais au bon format.
            'ifu' => '32024'.random_int(10000000, 99999999),
            'npi' => (string) random_int(1000000000, 9999999999),
        ]);
        $registrationService->replaceBusinessRegistrationDocument($registration, $this->fakeBusinessRegistrationDocument());

        if ($status === RegistrationStatus::ProfileIncomplete) {
            return $user;
        }

        $registrationService->submit($registration);

        match ($status) {
            RegistrationStatus::Approved => $registrationService->approve($registration, $admin),
            RegistrationStatus::Rejected => $registrationService->reject($registration, $admin, $rejectionReason ?? 'Dossier incomplet.'),
            default => null,
        };

        return $user->fresh();
    }

    /**
     * Profil public complet (CLAUDE.md §5, ajout v0.20) : photo à chemin fixe,
     * écrasée à chaque passage plutôt qu'accumulée.
     *
     * @param  array{structure_name: string, address: string, neighborhood: string, image_directory: string}  $data
     */
    private function completePublicProfile(Garage|MarketSpaceAccount $profile, array $data): void
    {
        $arrondissement = Arrondissement::query()->with('commune')->orderBy('id')->firstOrFail();

        $profile->update([
            'name' => $data['structure_name'],
            'address' => $data['address'],
            'latitude' => 6.3654,
            'longitude' => 2.4183,
            'department_id' => $arrondissement->commune->department_id,
            'commune_id' => $arrondissement->commune_id,
            'arrondissement_id' => $arrondissement->id,
            'neighborhood' => $data['neighborhood'],
        ]);

        $profile->openingHours()->delete();
        $profile->openingHours()->createMany(
            array_map(fn (int $day) => [
                'day_of_week' => $day,
                'is_closed' => $day === 7,
                'opens_at' => $day === 7 ? null : '08:00',
                'closes_at' => $day === 7 ? null : '18:00',
            ], range(1, 7)),
        );

        $profile->images()->delete();
        $profile->images()->create([
            'disk' => 'public',
            'path' => $this->fakeCatalogImage('photo.jpg')->storeAs($data['image_directory'], 'photo.jpg', 'public'),
            'position' => 1,
        ]);
    }

    /**
     * Supprime un compte de test existant (et, par les FK cascadeOnDelete,
     * son dossier, ses documents et son profil) ainsi que les fichiers
     * physiques des justificatifs, que la suppression en base n'efface pas.
     */
    private function deleteProfessionalAccount(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        foreach ($user->professionalRegistration?->documents ?? [] as $document) {
            Storage::disk($document->disk)->delete($document->path);
        }

        $user->professionalProfile()?->products()->delete();
        $user->delete();
    }
}
