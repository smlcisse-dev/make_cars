<?php

namespace Tests\Feature\Mobile;

use App\Enums\OrderStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Achat isolé de pièces/produits, sans prestation associée (CLAUDE.md §5,
 * règle 11 et ajout v0.9) : commande → paiement immédiat → facture.
 */
class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create();
    }

    private function approvedMarketSpaceAccount(): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->approved()->create([
            'user_id' => User::factory()->marketSpace(),
        ]);

        return MarketSpaceAccount::factory()->for($registration->user)->create();
    }

    public function test_an_automobiliste_can_order_a_product_from_a_garage_mini_boutique(): void
    {
        $garage = $this->approvedGarage();
        $product = Product::factory()->forGarage($garage)->approved()->create(['price' => 5000, 'stock_quantity' => 10]);
        Sanctum::actingAs($client = User::factory()->create());

        $response = $this->postJson('/api/mobile/orders', [
            'lines' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()->assertJsonPath('data.sellable_type', 'garage');
        $this->assertDatabaseHas('orders', ['sellable_type' => Garage::class, 'sellable_id' => $garage->id, 'user_id' => $client->id]);
        $this->assertDatabaseHas('order_lines', ['product_id' => $product->id, 'unit_price' => 5000, 'quantity' => 2, 'line_total' => 10000]);
        $this->assertSame(10, $product->fresh()->stock_quantity, 'Le stock ne bouge qu\'au paiement, pas à la création.');
    }

    public function test_an_automobiliste_can_order_a_product_from_a_market_space_account(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        $product = Product::factory()->forMarketSpace($account)->approved()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mobile/orders', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertCreated()->assertJsonPath('data.sellable_type', 'market_space');
    }

    public function test_ordering_more_than_available_stock_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        $product = Product::factory()->forGarage($garage)->approved()->create(['stock_quantity' => 1]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/mobile/orders', [
            'lines' => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertUnprocessable();
    }

    public function test_mixing_products_from_two_different_sellers_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        $productA = Product::factory()->forGarage($garage)->approved()->create();
        $productB = Product::factory()->forGarage($this->approvedGarage())->approved()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/mobile/orders', [
            'lines' => [
                ['product_id' => $productA->id, 'quantity' => 1],
                ['product_id' => $productB->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable();
    }

    public function test_ordering_a_non_approved_product_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        $product = Product::factory()->forGarage($garage)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/mobile/orders', [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertNotFound();
    }

    public function test_an_automobiliste_can_cancel_a_pending_order(): void
    {
        $client = User::factory()->create();
        $order = Order::factory()->forClient($client)->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);
    }

    public function test_cancelling_a_paid_order_is_forbidden(): void
    {
        $client = User::factory()->create();
        $order = Order::factory()->forClient($client)->paid()->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/orders/{$order->id}/cancel")->assertForbidden();
    }

    public function test_an_automobiliste_cannot_manage_anothers_order(): void
    {
        $order = Order::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/mobile/orders/{$order->id}")->assertNotFound();
    }

    public function test_an_automobiliste_can_download_the_invoice_once_generated(): void
    {
        $client = User::factory()->create();
        $order = Order::factory()->forClient($client)->paid()->create([
            'pdf_disk' => 'local',
            'pdf_path' => 'orders/test.pdf',
        ]);
        Storage::disk('local')->put('orders/test.pdf', '%PDF-1.7 fake');
        Sanctum::actingAs($client);

        $this->get("/api/mobile/orders/{$order->id}/pdf")->assertOk();
    }
}
