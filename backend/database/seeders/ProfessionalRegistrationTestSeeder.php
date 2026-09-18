<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\ProfessionalRegistrationService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

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
 */
class ProfessionalRegistrationTestSeeder extends Seeder
{
    public function run(ProfessionalRegistrationService $registrationService): void
    {
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

    private function fakeBusinessRegistrationDocument(): UploadedFile
    {
        return UploadedFile::fake()->create('registre-commerce.pdf', 50, 'application/pdf');
    }

    private function fakePremisesPhoto(): UploadedFile
    {
        return UploadedFile::fake()->image('photo-local.jpg', 640, 480);
    }
}
