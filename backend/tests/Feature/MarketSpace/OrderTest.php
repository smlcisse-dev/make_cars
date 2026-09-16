<?php

namespace Tests\Feature\MarketSpace;

use App\Enums\OrderStatus;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Le Market Space garde son propre stock, totalement distinct de celui d'un
 * garage (CLAUDE.md §5, ajout v0.4) ; pas de chat pour cette paire pour
 * l'instant (CLAUDE.md §5, ajout v0.8).
 */
class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function approvedMarketSpaceAccount(): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->approved()->create([
            'user_id' => User::factory()->marketSpace(),
        ]);

        return MarketSpaceAccount::factory()->for($registration->user)->create();
    }

    public function test_a_market_space_account_can_list_and_view_its_orders(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        Order::factory()->forMarketSpace($account)->create();
        Order::factory()->create();
        Sanctum::actingAs($account->user);

        $this->getJson('/api/market-space/orders')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_marking_an_order_paid_decrements_stock_and_generates_invoice_without_chat(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        $product = Product::factory()->forMarketSpace($account)->approved()->create(['stock_quantity' => 5]);
        $order = Order::factory()->forMarketSpace($account)->create();
        OrderLine::factory()->forOrder($order)->forProduct($product, 2)->create();
        Sanctum::actingAs($account->user);

        $response = $this->postJson("/api/market-space/orders/{$order->id}/mark-paid");

        $response->assertOk()->assertJsonPath('data.status', OrderStatus::Paid->value);
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertNotNull($order->fresh()->pdf_path);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_a_market_space_account_cannot_manage_anothers_order(): void
    {
        $order = Order::factory()->create();
        Sanctum::actingAs($this->approvedMarketSpaceAccount()->user);

        $this->getJson("/api/market-space/orders/{$order->id}")->assertNotFound();
    }
}
