<?php

namespace Tests\Feature\MarketSpace;

use App\Enums\ProductStatus;
use App\Models\MarketSpaceAccount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_market_space_account_can_list_its_own_products(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Product::factory()->forMarketSpace($account)->count(2)->create();
        Product::factory()->forMarketSpace()->count(1)->create();
        Sanctum::actingAs($account->user);

        $response = $this->getJson('/api/market-space/products');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_market_space_account_can_add_a_product_pending_validation(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $response = $this->postJson('/api/market-space/products', [
            'name' => 'Pneu 195/65 R15',
            'description' => 'Neuf, garantie constructeur.',
            'sku' => 'PNEU-195-65-15',
            'price' => 45000,
            'stock_quantity' => 8,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', ProductStatus::Pending->value);
        $this->assertDatabaseHas('products', [
            'sellable_type' => MarketSpaceAccount::class,
            'sellable_id' => $account->id,
            'name' => 'Pneu 195/65 R15',
        ]);
    }

    public function test_a_market_space_account_cannot_modify_another_accounts_product(): void
    {
        $product = Product::factory()->forMarketSpace()->create();
        $otherAccount = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($otherAccount->user);

        $this->deleteJson("/api/market-space/products/{$product->id}")->assertNotFound();
    }

    public function test_a_garagiste_cannot_access_the_market_space_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/market-space/products')->assertForbidden();
    }
}
