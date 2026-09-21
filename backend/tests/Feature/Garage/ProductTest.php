<?php

namespace Tests\Feature\Garage;

use App\Enums\ProductStatus;
use App\Models\Garage;
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

    public function test_a_garagiste_can_list_its_own_products(): void
    {
        $garage = Garage::factory()->complete()->create();
        Product::factory()->forGarage($garage)->count(2)->create();
        Product::factory()->forGarage()->count(1)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->getJson('/api/garage/products');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_garagiste_can_add_a_product_pending_validation(): void
    {
        $garage = Garage::factory()->complete()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson('/api/garage/products', [
            'name' => 'Huile moteur 5W30',
            'description' => 'Bidon 5 litres.',
            'sku' => 'HUILE-5W30',
            'price' => 15000,
            'stock_quantity' => 20,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', ProductStatus::Pending->value);
        $this->assertDatabaseHas('products', [
            'sellable_type' => Garage::class,
            'sellable_id' => $garage->id,
            'name' => 'Huile moteur 5W30',
            'status' => ProductStatus::Pending->value,
        ]);
    }

    public function test_updating_a_product_resets_its_validation_status(): void
    {
        $garage = Garage::factory()->complete()->create();
        $product = Product::factory()->forGarage($garage)->approved()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->putJson("/api/garage/products/{$product->id}", [
            'name' => 'Huile moteur 5W40',
            'description' => null,
            'sku' => null,
            'price' => 16000,
        ]);

        $response->assertOk()->assertJsonPath('data.status', ProductStatus::Pending->value);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Huile moteur 5W40',
            'status' => ProductStatus::Pending->value,
            'reviewed_by' => null,
        ]);
    }

    public function test_updating_stock_does_not_reset_validation_status(): void
    {
        $garage = Garage::factory()->complete()->create();
        $product = Product::factory()->forGarage($garage)->approved()->create(['stock_quantity' => 5]);
        Sanctum::actingAs($garage->user);

        $response = $this->putJson("/api/garage/products/{$product->id}/stock", [
            'stock_quantity' => 12,
        ]);

        $response->assertOk()->assertJsonPath('data.status', ProductStatus::Approved->value);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 12,
            'status' => ProductStatus::Approved->value,
        ]);
    }

    /**
     * Le seuil d'alerte de stock bas voyage avec l'endpoint stock, pas avec
     * la mise à jour de contenu — pas de revalidation admin pour ça
     * (CLAUDE.md §5, ajout v0.12).
     */
    public function test_a_garagiste_can_set_a_low_stock_alert_threshold_via_the_stock_endpoint(): void
    {
        $garage = Garage::factory()->complete()->create();
        $product = Product::factory()->forGarage($garage)->approved()->create(['stock_quantity' => 5, 'low_stock_threshold' => null]);
        Sanctum::actingAs($garage->user);

        $response = $this->putJson("/api/garage/products/{$product->id}/stock", [
            'stock_quantity' => 5,
            'low_stock_threshold' => 3,
        ]);

        $response->assertOk()->assertJsonPath('data.low_stock_threshold', 3);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'low_stock_threshold' => 3]);
    }

    public function test_a_garagiste_can_delete_its_own_product(): void
    {
        $garage = Garage::factory()->complete()->create();
        $product = Product::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $this->deleteJson("/api/garage/products/{$product->id}")->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_a_garagiste_cannot_modify_another_garages_product(): void
    {
        $product = Product::factory()->forGarage()->create();
        $otherGarage = Garage::factory()->complete()->create();
        Sanctum::actingAs($otherGarage->user);

        $this->putJson("/api/garage/products/{$product->id}", [
            'name' => 'Piratage',
            'description' => null,
            'sku' => null,
            'price' => 1,
        ])->assertNotFound();
    }

    public function test_creating_a_product_stores_its_initial_stock_and_threshold_without_embedding_the_seller(): void
    {
        Storage::fake('public');
        $garage = Garage::factory()->complete()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->post('/api/garage/products', [
            'name' => 'Filtre à huile',
            'description' => 'Compatible citadines.',
            'sku' => 'FILTRE-01',
            'price' => 4500,
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'image' => UploadedFile::fake()->image('filtre.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.stock_quantity', 20)
            ->assertJsonPath('data.low_stock_threshold', 5)
            ->assertJsonPath('data.sku', 'FILTRE-01');
        $this->assertNotNull($response->json('data.image_url'));
        $this->assertArrayNotHasKey('sellable', $response->json('data'));
        $this->assertDatabaseHas('products', ['name' => 'Filtre à huile', 'stock_quantity' => 20, 'low_stock_threshold' => 5]);
    }

    public function test_the_catalog_never_embeds_the_seller_in_its_responses(): void
    {
        $garage = Garage::factory()->complete()->create();
        $product = Product::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $listed = $this->getJson('/api/garage/products')->assertOk();
        $this->assertArrayNotHasKey('sellable', $listed->json('data.0'));

        $stock = $this->putJson("/api/garage/products/{$product->id}/stock", ['stock_quantity' => 3])->assertOk();
        $this->assertArrayNotHasKey('sellable', $stock->json('data'));
    }

    public function test_updating_content_through_method_spoofing_never_touches_the_stock_and_can_clear_optional_fields(): void
    {
        Storage::fake('public');
        $garage = Garage::factory()->complete()->create();
        $product = Product::factory()->forGarage($garage)->approved()->create([
            'description' => 'Ancienne description.',
            'sku' => 'ANCIEN-SKU',
            'stock_quantity' => 7,
            'low_stock_threshold' => 2,
        ]);
        Sanctum::actingAs($garage->user);

        $response = $this->post("/api/garage/products/{$product->id}", [
            '_method' => 'PUT',
            'name' => 'Huile renommée',
            'description' => '',
            'sku' => '',
            'price' => 9000,
            'stock_quantity' => 999,
            'low_stock_threshold' => 999,
            'image' => UploadedFile::fake()->image('huile.png'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Huile renommée')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.sku', null)
            ->assertJsonPath('data.status', ProductStatus::Pending->value)
            ->assertJsonPath('data.stock_quantity', 7)
            ->assertJsonPath('data.low_stock_threshold', 2);
        $this->assertNotNull($response->json('data.image_url'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 7, 'low_stock_threshold' => 2]);
    }

    public function test_updating_stock_never_touches_the_content_and_can_clear_the_threshold(): void
    {
        $garage = Garage::factory()->complete()->create();
        $product = Product::factory()->forGarage($garage)->approved()->create([
            'name' => 'Nom inchangé',
            'price' => 12000,
            'stock_quantity' => 5,
            'low_stock_threshold' => 3,
        ]);
        Sanctum::actingAs($garage->user);

        $response = $this->putJson("/api/garage/products/{$product->id}/stock", [
            'stock_quantity' => 40,
            'low_stock_threshold' => null,
            'name' => 'Tentative de renommage',
            'price' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.stock_quantity', 40)
            ->assertJsonPath('data.low_stock_threshold', null)
            ->assertJsonPath('data.name', 'Nom inchangé');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Nom inchangé', 'price' => 12000, 'low_stock_threshold' => null]);
    }

    public function test_a_garagiste_cannot_adjust_the_stock_of_another_garages_product(): void
    {
        $product = Product::factory()->forGarage()->create(['stock_quantity' => 5]);
        Sanctum::actingAs(Garage::factory()->complete()->create()->user);

        $this->putJson("/api/garage/products/{$product->id}/stock", ['stock_quantity' => 0])->assertNotFound();

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_a_garagiste_cannot_delete_another_garages_product(): void
    {
        $product = Product::factory()->forGarage()->create();
        Sanctum::actingAs(Garage::factory()->complete()->create()->user);

        $this->deleteJson("/api/garage/products/{$product->id}")->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_a_non_garagiste_cannot_access_the_garage_product_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/garage/products')->assertForbidden();
    }
}
