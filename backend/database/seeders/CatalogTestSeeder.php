<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\QuoteLine;
use App\Models\RepairService;
use App\Models\User;
use App\Services\ProductService;
use App\Services\RepairServiceService;
use Database\Seeders\Concerns\GeneratesFakeKycDocuments;
use Database\Seeders\Concerns\SeedsProfessionalAccounts;
use Illuminate\Database\Eloquent\Collection;
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
 * Idempotent sans jamais effacer d'historique (CLAUDE.md §6) : un nouveau
 * passage supprime puis recrée le service/les produits de test (identifiés
 * par leur nom), sauf ceux référencés par une ligne de devis ou de commande
 * (ou, pour le service, par un RDV), conservés tels quels avec un
 * avertissement et jamais recréés en double. Le compte Market Space
 * approuvé est créé s'il manque, jamais réinitialisé (voir
 * SeedsProfessionalAccounts).
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
     * Chemin fixe (pas d'id de compte) : la photo générée est écrasée par
     * une recréation au lieu de s'accumuler.
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

        if ($this->resetUnreferencedItems($garage->services()->where('name', self::GARAGE_SERVICE_NAME)->get())) {
            $serviceService->create($garage, [
                'name' => self::GARAGE_SERVICE_NAME,
                'description' => "Vidange moteur avec remplacement de l'huile et du filtre à huile. Contrôle des niveaux inclus.",
                'category' => 'entretien_courant',
                'price' => 15000,
                'duration_minutes' => 45,
            ], $this->fakeCatalogImage('vidange-complete.jpg'));
        }

        if ($this->resetUnreferencedItems($garage->products()->where('name', self::GARAGE_PRODUCT_NAME)->get())) {
            $productService->create($garage, [
                'name' => self::GARAGE_PRODUCT_NAME,
                'description' => 'Huile moteur synthétique 5W30, bidon de 5 litres.',
                'sku' => 'HUILE-5W30-5L-TEST',
                'price' => 12000,
                'stock_quantity' => 20,
            ], $this->fakeCatalogImage('huile-moteur-5w30.jpg'));
        }

        $marketSpaceAccount = $this->ensureApprovedMarketSpaceAccount($admin);

        if ($this->resetUnreferencedItems($marketSpaceAccount->products()->where('name', self::MARKET_SPACE_PRODUCT_NAME)->get())) {
            $productService->create($marketSpaceAccount, [
                'name' => self::MARKET_SPACE_PRODUCT_NAME,
                'description' => 'Kit de plaquettes de frein avant, compatible véhicules citadins courants.',
                'sku' => 'PLAQ-FREIN-AV-TEST',
                'price' => 18000,
                'stock_quantity' => 10,
            ], $this->fakeCatalogImage('plaquettes-frein-avant.jpg'));
        }

        foreach ($this->seededAccountOutcomes as $email => $outcome) {
            $this->command?->info("CatalogTestSeeder : {$email} — {$outcome}.");
        }

        $this->command?->info('CatalogTestSeeder : terminé.');
    }

    /**
     * Supprime les exemplaires de test jamais référencés ; conserve tels
     * quels (avec avertissement) ceux qu'une ligne de devis ou de commande,
     * ou un RDV, référence — trace financière (CLAUDE.md §6).
     *
     * @param  Collection<int, RepairService|Product>  $items
     * @return bool true si aucun exemplaire n'a été conservé, donc à recréer
     */
    private function resetUnreferencedItems(Collection $items): bool
    {
        $kept = false;

        foreach ($items as $item) {
            $references = $item instanceof Product
                ? OrderLine::where('product_id', $item->id)->count() + QuoteLine::where('product_id', $item->id)->count()
                : QuoteLine::where('repair_service_id', $item->id)->count() + Appointment::where('repair_service_id', $item->id)->count();

            if ($references === 0) {
                $item->delete();

                continue;
            }

            $kept = true;
            $this->command?->warn(
                "« {$item->name} » (id {$item->id}) n'a pas été recréé : il est référencé {$references} fois ".
                '(devis, commande ou RDV). Il est conservé tel quel, dans son état de validation actuel.',
            );
        }

        return ! $kept;
    }

    /**
     * Compte Market Space approuvé de test, créé s'il manque via le parcours
     * v0.26 (profil, informations légales, soumission, approbation), jamais
     * réinitialisé s'il existe — voir SeedsProfessionalAccounts.
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
                'arrondissement_slug' => '4e-arrondissement',
                'latitude' => 6.3563,
                'longitude' => 2.4329,
                'image_directory' => self::MARKET_SPACE_IMAGE_DIRECTORY,
            ],
            $admin,
        );

        return $user->marketSpaceAccount()->firstOrFail();
    }
}
