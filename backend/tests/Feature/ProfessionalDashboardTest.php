<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\Appointment;
use App\Models\Dispute;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteVersion;
use App\Models\RepairService;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tableau de bord des espaces professionnels (ajout 2026-09-24). Chaque
 * test crée aussi des données chez un AUTRE professionnel, qui ne doivent
 * jamais être comptées.
 */
class ProfessionalDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->complete()->for($registration->user)->create();
    }

    private function approvedMarketSpace(): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->marketSpace()->approved()->create();

        return MarketSpaceAccount::factory()->complete()->for($registration->user)->create();
    }

    /**
     * Crée pour ce garage un exemplaire de chaque élément compté, avec des
     * quantités distinctes pour reconnaître chaque compteur.
     */
    private function seedGarageData(Garage $garage): void
    {
        Appointment::factory()->forGarage($garage)->count(2)->create();
        Appointment::factory()->forGarage($garage)->confirmed()->create();

        Quote::factory()->forGarage($garage)->accepted()->count(3)->create();
        Quote::factory()->forGarage($garage)->count(4)->create(['status' => QuoteStatus::InProgress]);
        Quote::factory()->forGarage($garage)->sent()->create();
        Quote::factory()->forGarage($garage)->create(['status' => QuoteStatus::Negotiating]);
        Quote::factory()->forGarage($garage)->create();

        RepairService::factory()->forGarage($garage)->rejected()->count(2)->create();
        RepairService::factory()->forGarage($garage)->count(3)->create();
        RepairService::factory()->forGarage($garage)->approved()->create();
    }

    /**
     * Données communes aux deux types de vendeurs.
     */
    private function seedSellerData(Garage|MarketSpaceAccount $seller): void
    {
        $forSeller = $seller instanceof Garage ? 'forGarage' : 'forMarketSpace';

        Order::factory()->{$forSeller}($seller)->count(2)->create();
        Order::factory()->{$forSeller}($seller)->cancelled()->create();

        Product::factory()->{$forSeller}($seller)->approved()->create(['name' => 'Huile', 'stock_quantity' => 3, 'low_stock_threshold' => 5]);
        Product::factory()->{$forSeller}($seller)->approved()->create(['name' => 'Filtre', 'stock_quantity' => 5, 'low_stock_threshold' => 5]);
        Product::factory()->{$forSeller}($seller)->approved()->create(['stock_quantity' => 6, 'low_stock_threshold' => 5]);
        Product::factory()->{$forSeller}($seller)->approved()->create(['stock_quantity' => 0, 'low_stock_threshold' => null]);
        Product::factory()->{$forSeller}($seller)->rejected()->count(2)->create(['low_stock_threshold' => null]);
        Product::factory()->{$forSeller}($seller)->count(3)->create(['low_stock_threshold' => null]);

        Dispute::factory()->{$forSeller}($seller)->create();
        Dispute::factory()->{$forSeller}($seller)->underReview()->create();
        Dispute::factory()->{$forSeller}($seller)->resolvedRejected()->create();

        Review::factory()->{$forSeller}($seller)->create(['rating' => 5]);
        Review::factory()->{$forSeller}($seller)->create(['rating' => 4]);
        Review::factory()->{$forSeller}($seller)->hidden()->create(['rating' => 1]);
    }

    public function test_a_garagiste_sees_all_its_counters_and_never_another_garages(): void
    {
        $garage = $this->approvedGarage();
        $this->seedGarageData($garage);
        $this->seedSellerData($garage);

        $other = $this->approvedGarage();
        $this->seedGarageData($other);
        $this->seedSellerData($other);

        Sanctum::actingAs($garage->user);

        $response = $this->getJson('/api/garage/dashboard')->assertOk();

        $response
            ->assertJsonPath('data.structure_name', $garage->name)
            ->assertJsonPath('data.to_handle.pending_appointments', 2)
            ->assertJsonPath('data.to_handle.quotes_to_start', 3)
            ->assertJsonPath('data.to_handle.quotes_to_invoice', 4)
            ->assertJsonPath('data.to_handle.orders_to_collect', 2)
            ->assertJsonPath('data.to_handle.low_stock_products.count', 2)
            ->assertJsonPath('data.to_handle.low_stock_products.items.0.name', 'Huile')
            ->assertJsonPath('data.to_handle.low_stock_products.items.0.stock_quantity', 3)
            ->assertJsonPath('data.to_handle.low_stock_products.items.0.low_stock_threshold', 5)
            ->assertJsonPath('data.to_handle.low_stock_products.items.1.name', 'Filtre')
            ->assertJsonPath('data.to_handle.rejected_services', 2)
            ->assertJsonPath('data.to_handle.rejected_products', 2)
            ->assertJsonPath('data.to_handle.open_disputes', 2)
            ->assertJsonPath('data.activity.reviews.count', 2)
            ->assertJsonPath('data.activity.reviews.average_rating', 4.5)
            ->assertJsonPath('data.activity.quotes_awaiting_client', 2)
            ->assertJsonPath('data.activity.pending_services', 3)
            ->assertJsonPath('data.activity.pending_products', 3);
    }

    public function test_the_low_stock_list_is_limited_to_five_products(): void
    {
        $garage = $this->approvedGarage();
        Product::factory()->forGarage($garage)->count(7)->create(['stock_quantity' => 1, 'low_stock_threshold' => 2]);
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/dashboard')
            ->assertOk()
            ->assertJsonPath('data.to_handle.low_stock_products.count', 7)
            ->assertJsonCount(5, 'data.to_handle.low_stock_products.items');
    }

    public function test_a_market_space_sees_its_counters_without_the_garage_only_blocks(): void
    {
        $account = $this->approvedMarketSpace();
        $this->seedSellerData($account);

        $otherAccount = $this->approvedMarketSpace();
        $this->seedSellerData($otherAccount);
        // Un garage portant le même identifiant ne doit pas être confondu (vendeur polymorphe).
        $garage = $this->approvedGarage();
        $this->seedSellerData($garage);

        Sanctum::actingAs($account->user);

        $response = $this->getJson('/api/market-space/dashboard')->assertOk();

        $response
            ->assertJsonPath('data.structure_name', $account->name)
            ->assertJsonPath('data.to_handle.orders_to_collect', 2)
            ->assertJsonPath('data.to_handle.low_stock_products.count', 2)
            ->assertJsonPath('data.to_handle.rejected_products', 2)
            ->assertJsonPath('data.to_handle.open_disputes', 2)
            ->assertJsonPath('data.activity.reviews.count', 2)
            ->assertJsonPath('data.activity.pending_products', 3)
            ->assertJsonMissingPath('data.to_handle.pending_appointments')
            ->assertJsonMissingPath('data.to_handle.quotes_to_start')
            ->assertJsonMissingPath('data.to_handle.quotes_to_invoice')
            ->assertJsonMissingPath('data.to_handle.rejected_services')
            ->assertJsonMissingPath('data.activity.quotes_awaiting_client')
            ->assertJsonMissingPath('data.activity.pending_services');
    }

    public function test_the_invoiced_amount_only_counts_the_current_month_for_this_seller(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');

        $garage = $this->approvedGarage();
        $this->invoicedQuote($garage, 10000, now());
        $this->invoicedQuote($garage, 99999, now()->subMonth());
        $this->paidOrder($garage, 2500, now()->startOfMonth()->addHours(2));
        $this->paidOrder($garage, 88888, now()->subMonth());
        // Commande en attente : jamais comptée.
        OrderLine::factory()->forOrder(Order::factory()->forGarage($garage)->create())->create(['line_total' => 77777]);

        $other = $this->approvedGarage();
        $this->invoicedQuote($other, 55555, now());
        $this->paidOrder($other, 44444, now());

        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/dashboard')
            ->assertOk()
            ->assertJsonPath('data.activity.month_start', '2026-09-01')
            ->assertJsonPath('data.activity.invoiced_amount_this_month', '12500.00');

        Carbon::setTestNow();
    }

    public function test_the_market_space_invoiced_amount_counts_its_paid_orders(): void
    {
        $account = $this->approvedMarketSpace();
        $this->paidOrder($account, 3000, now());
        $this->paidOrder($this->approvedMarketSpace(), 1000, now());

        Sanctum::actingAs($account->user);

        $this->getJson('/api/market-space/dashboard')
            ->assertOk()
            ->assertJsonPath('data.activity.invoiced_amount_this_month', '3000.00');
    }

    public function test_a_professional_whose_dossier_is_not_approved_is_refused(): void
    {
        $registration = ProfessionalRegistration::factory()->pending()->create();
        $garage = Garage::factory()->complete()->for($registration->user)->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'registration_not_approved');
    }

    public function test_the_dashboard_is_reserved_to_its_own_space(): void
    {
        Sanctum::actingAs($this->approvedGarage()->user);

        $this->getJson('/api/market-space/dashboard')->assertForbidden();
    }

    private function invoicedQuote(Garage $garage, float $amount, Carbon $paidAt): void
    {
        $quote = Quote::factory()->forGarage($garage)->invoiced()->create(['paid_at' => $paidAt]);
        $invoice = QuoteVersion::factory()->forQuote($quote, 2)->invoice()->create();
        QuoteLine::factory()->forVersion($invoice)->create(['unit_price' => $amount, 'line_total' => $amount]);
    }

    private function paidOrder(Garage|MarketSpaceAccount $seller, float $amount, Carbon $paidAt): void
    {
        $order = ($seller instanceof Garage
            ? Order::factory()->forGarage($seller)
            : Order::factory()->forMarketSpace($seller)
        )->paid()->create(['paid_at' => $paidAt]);
        OrderLine::factory()->forOrder($order)->create(['unit_price' => $amount, 'line_total' => $amount]);
    }
}
