<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Arrondissement;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\User;
use App\Services\ProductService;
use App\Services\ProfessionalRegistrationService;
use App\Services\RepairServiceService;
use Database\Seeders\Concerns\GeneratesFakeKycDocuments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de données de test pour la validation admin des services et produits
 * (CLAUDE.md §5, règle 5) : un service et un produit "pending" sur la
 * mini-boutique d'un garage déjà approuvé, et un produit "pending" côté
 * Market Space. Suppose que ProfessionalRegistrationTestSeeder a déjà tourné
 * (Garage Excellence Akpakpa doit exister et être approuvé) :
 *
 *   php artisan db:seed --class=ProfessionalRegistrationTestSeeder
 *   php artisan db:seed --class=CatalogTestSeeder
 *
 * Passe exclusivement par RepairServiceService/ProductService/
 * ProfessionalRegistrationService (jamais d'insertion directe Eloquent/DB),
 * même principe que ProfessionalRegistrationTestSeeder.
 *
 * Idempotent : un nouveau passage supprime d'abord le service/les produits
 * de test déjà présents (identifiés par leur nom) avant de les recréer, et
 * repart de zéro sur le compte Market Space de test s'il existe déjà — même
 * logique que ProfessionalRegistrationTestSeeder pour ses 4 comptes.
 */
class CatalogTestSeeder extends Seeder
{
    use GeneratesFakeKycDocuments;

    private const GARAGE_NAME = 'Garage Excellence Akpakpa';

    private const GARAGE_SERVICE_NAME = 'Vidange complète (test validation admin)';

    private const GARAGE_PRODUCT_NAME = 'Huile moteur 5W30 - 5L (test validation admin)';

    private const MARKET_SPACE_EMAIL = 'marche.pieces.approved@makecars.test';

    private const MARKET_SPACE_STRUCTURE_NAME = 'Marché Pièces Cotonou';

    /**
     * Chemin fixe (pas d'id de compte, recréé à chaque passage) : la photo
     * générée est écrasée par le passage suivant au lieu de s'accumuler.
     */
    private const MARKET_SPACE_IMAGE_DIRECTORY = 'market-space/catalog-test-seeder';

    private const MARKET_SPACE_PRODUCT_NAME = 'Kit plaquettes de frein avant (test validation admin)';

    public function run(
        ProfessionalRegistrationService $registrationService,
        RepairServiceService $serviceService,
        ProductService $productService,
    ): void {
        $admin = User::where('role', AccountType::Admin)->first()
            ?? User::factory()->admin()->create([
                'name' => 'Admin Make Cars',
                'email' => 'admin@makecars.test',
            ]);

        $garage = Garage::where('name', self::GARAGE_NAME)->first();

        if (! $garage) {
            $this->command?->error(
                'CatalogTestSeeder : "'.self::GARAGE_NAME.'" introuvable. '.
                'Lancez d\'abord php artisan db:seed --class=ProfessionalRegistrationTestSeeder.',
            );

            return;
        }

        $garage->services()->where('name', self::GARAGE_SERVICE_NAME)->delete();
        $serviceService->create($garage, [
            'name' => self::GARAGE_SERVICE_NAME,
            'description' => "Vidange moteur avec remplacement de l'huile et du filtre à huile. Contrôle des niveaux inclus.",
            'category' => 'entretien_courant',
            'price' => 15000,
            'duration_minutes' => 45,
        ], $this->fakeCatalogImage('vidange-complete.jpg'));

        $garage->products()->where('name', self::GARAGE_PRODUCT_NAME)->delete();
        $productService->create($garage, [
            'name' => self::GARAGE_PRODUCT_NAME,
            'description' => 'Huile moteur synthétique 5W30, bidon de 5 litres.',
            'sku' => 'HUILE-5W30-5L-TEST',
            'price' => 12000,
            'stock_quantity' => 20,
        ], $this->fakeCatalogImage('huile-moteur-5w30.jpg'));

        $marketSpaceAccount = $this->ensureApprovedMarketSpaceAccount($registrationService, $admin);

        $marketSpaceAccount->products()->where('name', self::MARKET_SPACE_PRODUCT_NAME)->delete();
        $productService->create($marketSpaceAccount, [
            'name' => self::MARKET_SPACE_PRODUCT_NAME,
            'description' => 'Kit de plaquettes de frein avant, compatible véhicules citadins courants.',
            'sku' => 'PLAQ-FREIN-AV-TEST',
            'price' => 18000,
            'stock_quantity' => 10,
        ], $this->fakeCatalogImage('plaquettes-frein-avant.jpg'));

        $this->command?->info(
            'CatalogTestSeeder : 1 service pending (garage) + 2 produits pending (mini-boutique garage + Market Space) créés.',
        );
    }

    /**
     * Aucun compte Market Space de test n'est approuvé (les seeders existants
     * n'en laissent qu'un pending et un rejected) : on inscrit puis approuve
     * un compte dédié, en repartant de zéro s'il existe déjà (même logique
     * que ProfessionalRegistrationTestSeeder::resetExistingTestAccounts).
     */
    private function ensureApprovedMarketSpaceAccount(
        ProfessionalRegistrationService $registrationService,
        User $admin,
    ): MarketSpaceAccount {
        $existingUser = User::where('email', self::MARKET_SPACE_EMAIL)->first();

        if ($existingUser) {
            $existingUser->marketSpaceAccount?->products()->delete();
            $existingUser->delete();
        }

        $user = $registrationService->register(
            [
                'name' => self::MARKET_SPACE_STRUCTURE_NAME,
                'email' => self::MARKET_SPACE_EMAIL,
                'phone' => '+229 01 90 04 05 '.random_int(10, 99),
                'password' => Hash::make('password'),
                'account_type' => AccountType::MarketSpace->value,
                'structure_name' => self::MARKET_SPACE_STRUCTURE_NAME,
                'address' => 'Quartier Ganhi, Cotonou, Bénin',
                'business_registration_number' => 'IFU-'.random_int(10000000, 99999999),
            ],
            $this->fakeBusinessRegistrationDocument(),
            [$this->fakePremisesPhoto()],
        );

        $registrationService->approve($user->professionalRegistration, $admin);

        $account = $user->marketSpaceAccount()->firstOrFail();
        $this->completeProfile($account);

        return $account;
    }

    /**
     * Complète le profil du compte Market Space de test (CLAUDE.md §5, ajout
     * v0.20) pour qu'il puisse accéder à tout son espace sans passer par
     * l'écran de profil : téléphone, position, localisation administrative,
     * 7 jours d'horaires et une photo.
     */
    private function completeProfile(MarketSpaceAccount $account): void
    {
        $arrondissement = Arrondissement::query()->with('commune')->orderBy('id')->firstOrFail();

        $account->update([
            'phone' => '+2290190040599',
            'latitude' => 6.3654,
            'longitude' => 2.4183,
            'department_id' => $arrondissement->commune->department_id,
            'commune_id' => $arrondissement->commune_id,
            'arrondissement_id' => $arrondissement->id,
            'neighborhood' => 'Ganhi',
        ]);

        $account->openingHours()->delete();
        $account->openingHours()->createMany(
            array_map(fn (int $day) => [
                'day_of_week' => $day,
                'is_closed' => $day === 7,
                'opens_at' => $day === 7 ? null : '08:00',
                'closes_at' => $day === 7 ? null : '18:00',
            ], range(1, 7)),
        );

        $account->images()->delete();
        $account->images()->create([
            'disk' => 'public',
            'path' => $this->fakeCatalogImage('boutique.jpg')->storeAs(self::MARKET_SPACE_IMAGE_DIRECTORY, 'boutique.jpg', 'public'),
            'position' => 1,
        ]);
    }
}
