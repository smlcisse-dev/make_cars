<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\User;
use App\Services\ProductService;
use App\Services\RepairServiceService;
use Database\Seeders\Concerns\GeneratesFakeKycDocuments;
use Database\Seeders\Concerns\SeedsProfessionalAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

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
 * Passe par RepairServiceService/ProductService et, pour le compte Market
 * Space, par SeedsProfessionalAccounts (même parcours que
 * ProfessionalRegistrationTestSeeder). Aucun email réel (Mail::fake).
 *
 * Idempotent : un nouveau passage supprime d'abord le service/les produits
 * de test déjà présents (identifiés par leur nom) avant de les recréer, et
 * repart de zéro sur le compte Market Space de test s'il existe déjà — même
 * logique que ProfessionalRegistrationTestSeeder pour ses 4 comptes.
 */
class CatalogTestSeeder extends Seeder
{
    use GeneratesFakeKycDocuments, SeedsProfessionalAccounts;

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
        RepairServiceService $serviceService,
        ProductService $productService,
    ): void {
        Mail::fake();

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

        $marketSpaceAccount = $this->ensureApprovedMarketSpaceAccount($admin);

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
     * Compte Market Space approuvé de test, recréé à chaque passage via le
     * parcours v0.26 (profil, informations légales, soumission, approbation
     * — voir SeedsProfessionalAccounts).
     */
    private function ensureApprovedMarketSpaceAccount(User $admin): MarketSpaceAccount
    {
        $user = $this->seedProfessionalAccount(
            AccountType::MarketSpace,
            self::MARKET_SPACE_EMAIL,
            'Géraud',
            'Ahouansou',
            '+22901900405'.random_int(10, 99),
            RegistrationStatus::Approved,
            [
                'structure_name' => self::MARKET_SPACE_STRUCTURE_NAME,
                'address' => 'Quartier Ganhi, Cotonou, Bénin',
                'neighborhood' => 'Ganhi',
                'image_directory' => self::MARKET_SPACE_IMAGE_DIRECTORY,
            ],
            $admin,
        );

        return $user->marketSpaceAccount()->firstOrFail();
    }
}
