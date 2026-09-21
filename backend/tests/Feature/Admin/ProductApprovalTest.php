<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_pending_products_across_garages_and_market_space(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Product::factory()->forGarage()->create();
        Product::factory()->forMarketSpace()->create();
        Product::factory()->forGarage()->approved()->create();

        $response = $this->getJson('/api/admin/products?status=pending');

        $response->assertOk()->assertJsonCount(2, 'data');
        // Le vendeur (Garage ou Market Space) doit être exposé (pas juste
        // sellable_id) pour que le dashboard admin affiche un nom sans
        // requête supplémentaire, quel que soit son type polymorphe.
        $response->assertJsonStructure(['data' => ['*' => ['sellable' => ['id', 'name']]]]);
    }

    public function test_a_non_admin_cannot_list_products(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/admin/products')->assertForbidden();
    }

    public function test_an_admin_can_approve_a_pending_product(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $product = Product::factory()->forGarage()->create();

        $response = $this->postJson("/api/admin/products/{$product->id}/approve");

        $response->assertOk()->assertJsonPath('data.status', ProductStatus::Approved->value);
        $response->assertJsonPath('data.sellable.id', $product->sellable_id);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => ProductStatus::Approved->value,
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_an_admin_can_reject_a_pending_product_with_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->forGarage()->create();

        $response = $this->postJson("/api/admin/products/{$product->id}/reject", [
            'reason' => 'Pièce non homologuée.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', ProductStatus::Rejected->value);
        $response->assertJsonPath('data.sellable.id', $product->sellable_id);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => ProductStatus::Rejected->value,
            'rejection_reason' => 'Pièce non homologuée.',
        ]);
    }

    public function test_rejecting_a_product_requires_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->forGarage()->create();

        $this->postJson("/api/admin/products/{$product->id}/reject")->assertUnprocessable();
    }

    public function test_an_already_approved_product_cannot_be_reviewed_again(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->forGarage()->approved()->create();

        $this->postJson("/api/admin/products/{$product->id}/approve")->assertForbidden();
    }
}
