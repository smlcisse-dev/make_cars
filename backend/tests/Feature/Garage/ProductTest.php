<?php

namespace Tests\Feature\Garage;

use App\Enums\ProductStatus;
use App\Models\Garage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_a_non_garagiste_cannot_access_the_garage_product_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/garage/products')->assertForbidden();
    }
}
