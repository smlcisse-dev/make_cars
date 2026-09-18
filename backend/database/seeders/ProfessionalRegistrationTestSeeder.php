<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\ProfessionalRegistrationService;
use Database\Seeders\Concerns\GeneratesFakeKycDocuments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Jeu de données de test pour le workflow d'inscription professionnelle
 * (pending / approved / rejected). Non appelé depuis DatabaseSeeder — se
 * déclenche manuellement :
 *
 *   php artisan db:seed --class=ProfessionalRegistrationTestSeeder
 *
 * Passe exclusivement par ProfessionalRegistrationService (jamais d'insertion
 * directe Eloquent/DB) pour rester fidèle au workflow réel (documents,
 * notifications, création du profil Garage/Market Space à l'approbation).
 *
 * Idempotent : un nouveau passage supprime d'abord les 4 comptes de test
 * s'ils existent déjà (quel que soit l'état où ils ont été laissés par des
 * tests manuels — ex. un dossier pending rejeté entre-temps) puis les
 * recrée à l'identique, plutôt que d'échouer sur un doublon d'email.
 */
class ProfessionalRegistrationTestSeeder extends Seeder
{
    use GeneratesFakeKycDocuments;

    private const TEST_EMAILS = [
        'garage.etoile.pending@makecars.test',
        'pieces.express.pending@makecars.test',
        'garage.excellence.approved@makecars.test',
        'auto.pieces.rejected@makecars.test',
    ];

    public function run(ProfessionalRegistrationService $registrationService): void
    {
        $this->resetExistingTestAccounts();

        $admin = User::where('role', AccountType::Admin)->first()
            ?? User::factory()->admin()->create([
                'name' => 'Admin Make Cars',
                'email' => 'admin@makecars.test',
            ]);

        // 1. Garagiste, pending
        $registrationService->register(
            $this->garagisteData('Garage Étoile du Bénin', 'garage.etoile.pending'),
            $this->fakeBusinessRegistrationDocument(),
            [$this->fakePremisesPhoto()],
        );

        // 2. Market Space, pending
        $registrationService->register(
            $this->marketSpaceData('Pièces Express Cotonou', 'pieces.express.pending'),
            $this->fakeBusinessRegistrationDocument(),
            [$this->fakePremisesPhoto()],
        );

        // 3. Garagiste, approved (inscription puis approbation via le service)
        $approvedGaragisteUser = $registrationService->register(
            $this->garagisteData('Garage Excellence Akpakpa', 'garage.excellence.approved'),
            $this->fakeBusinessRegistrationDocument(),
            [$this->fakePremisesPhoto(), $this->fakePremisesPhoto()],
        );
        $registrationService->approve($approvedGaragisteUser->professionalRegistration, $admin);

        // 4. Market Space, rejected (inscription puis rejet via le service, motif réaliste)
        $rejectedMarketSpaceUser = $registrationService->register(
            $this->marketSpaceData('Auto Pièces Fidjrossè', 'auto.pieces.rejected'),
            $this->fakeBusinessRegistrationDocument(),
            [$this->fakePremisesPhoto()],
        );
        $registrationService->reject(
            $rejectedMarketSpaceUser->professionalRegistration,
            $admin,
            "Le registre de commerce fourni est illisible et la photo du local ne correspond pas à l'adresse déclarée. Merci de soumettre à nouveau un dossier complet.",
        );

        $this->command?->info('ProfessionalRegistrationTestSeeder : 4 dossiers créés (1 pending garagiste, 1 pending market_space, 1 approved garagiste, 1 rejected market_space).');
    }

    /**
     * @return array{name: string, email: string, phone: string, password: string, account_type: string, structure_name: string, address: string, business_registration_number: string}
     */
    private function garagisteData(string $structureName, string $emailPrefix): array
    {
        return $this->registrationData(AccountType::Garagiste, $structureName, $emailPrefix, '229 90 01 02 '.random_int(10, 99));
    }

    /**
     * @return array{name: string, email: string, phone: string, password: string, account_type: string, structure_name: string, address: string, business_registration_number: string}
     */
    private function marketSpaceData(string $structureName, string $emailPrefix): array
    {
        return $this->registrationData(AccountType::MarketSpace, $structureName, $emailPrefix, '229 90 02 03 '.random_int(10, 99));
    }

    /**
     * @return array{name: string, email: string, phone: string, password: string, account_type: string, structure_name: string, address: string, business_registration_number: string}
     */
    private function registrationData(AccountType $accountType, string $structureName, string $emailPrefix, string $phone): array
    {
        return [
            'name' => $structureName,
            'email' => $emailPrefix.'@makecars.test',
            'phone' => $phone,
            'password' => Hash::make('password'),
            'account_type' => $accountType->value,
            'structure_name' => $structureName,
            'address' => 'Quartier Akpakpa, Cotonou, Bénin',
            'business_registration_number' => 'IFU-'.random_int(10000000, 99999999),
        ];
    }

    /**
     * Supprime les 4 comptes de test s'ils existent déjà (et, avec eux, via
     * les FK cascadeOnDelete : dossier d'inscription, documents, profil
     * Garage/Market Space) avant de les recréer — rend le seeder rejouable
     * sans erreur de doublon d'email, et remet un dossier manipulé
     * manuellement pendant des tests (ex. approuvé/rejeté) dans son état
     * initial. Les fichiers physiques des justificatifs sont aussi supprimés
     * du disque avant la suppression en base, qui ne les efface pas.
     */
    private function resetExistingTestAccounts(): void
    {
        foreach (self::TEST_EMAILS as $email) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                continue;
            }

            foreach ($user->professionalRegistration?->documents ?? [] as $document) {
                Storage::disk($document->disk)->delete($document->path);
            }

            $user->delete();
        }
    }
}
