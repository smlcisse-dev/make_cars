<?php

namespace Tests\Feature\MarketSpace;

use App\Enums\ProductStatus;
use App\Models\MarketSpaceAccount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_market_space_account_can_list_its_own_products(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        Product::factory()->forMarketSpace($account)->count(2)->create();
        Product::factory()->forMarketSpace()->count(1)->create();
        Sanctum::actingAs($account->user);

        $response = $this->getJson('/api/market-space/products');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_market_space_account_can_add_a_product_pending_validation(): void
    {
        Storage::fake('public');
        $account = MarketSpaceAccount::factory()->complete()->create();
        Sanctum::actingAs($account->user);

        $response = $this->post('/api/market-space/products', [
            'name' => 'Pneu 195/65 R15',
            'description' => 'Neuf, garantie constructeur.',
            'sku' => 'PNEU-195-65-15',
            'price' => 45000,
            'stock_quantity' => 8,
            'image' => UploadedFile::fake()->image('pneu.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.status', ProductStatus::Pending->value);
        $this->assertDatabaseHas('products', [
            'sellable_type' => MarketSpaceAccount::class,
            'sellable_id' => $account->id,
            'name' => 'Pneu 195/65 R15',
        ]);
    }

    /**
     * L'image devient obligatoire à la création (CLAUDE.md §5, ajout v0.23)
     * — même règle que côté Garage, classe de validation partagée.
     */
    public function test_creating_a_product_without_an_image_is_rejected(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        Sanctum::actingAs($account->user);

        $response = $this->postJson('/api/market-space/products', [
            'name' => 'Pneu 195/65 R15',
            'description' => 'Neuf, garantie constructeur.',
            'sku' => 'PNEU-195-65-15',
            'price' => 45000,
            'stock_quantity' => 8,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_updating_a_product_without_a_new_image_keeps_the_existing_one(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        $product = Product::factory()->forMarketSpace($account)->approved()->create([
            'image_disk' => 'public',
            'image_path' => 'products/existing.jpg',
        ]);
        Sanctum::actingAs($account->user);

        $response = $this->putJson("/api/market-space/products/{$product->id}", [
            'name' => $product->name,
            'description' => $product->description,
            'sku' => $product->sku,
            'price' => $product->price,
        ]);

        $response->assertOk();
        $this->assertSame('products/existing.jpg', $product->fresh()->image_path);
    }

    public function test_updating_a_product_without_an_image_fails_when_it_has_none(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        $product = Product::factory()->forMarketSpace($account)->approved()->create();
        Sanctum::actingAs($account->user);

        $response = $this->putJson("/api/market-space/products/{$product->id}", [
            'name' => $product->name,
            'description' => $product->description,
            'sku' => $product->sku,
            'price' => $product->price,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_no_endpoint_exists_to_delete_a_products_image_alone(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        $product = Product::factory()->forMarketSpace($account)->approved()->create([
            'image_disk' => 'public',
            'image_path' => 'products/existing.jpg',
        ]);
        Sanctum::actingAs($account->user);

        $this->deleteJson("/api/market-space/products/{$product->id}/image")->assertNotFound();
        $this->assertNotNull($product->fresh()->image_path);
    }

    public function test_a_market_space_account_cannot_modify_another_accounts_product(): void
    {
        $product = Product::factory()->forMarketSpace()->create();
        $otherAccount = MarketSpaceAccount::factory()->complete()->create();
        Sanctum::actingAs($otherAccount->user);

        $this->deleteJson("/api/market-space/products/{$product->id}")->assertNotFound();
    }

    public function test_a_garagiste_cannot_access_the_market_space_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/market-space/products')->assertForbidden();
    }
}
