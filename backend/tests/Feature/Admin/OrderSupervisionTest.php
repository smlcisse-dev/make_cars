<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_all_orders(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Order::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/orders');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_an_admin_can_view_an_order(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $order = Order::factory()->create();

        $response = $this->getJson("/api/admin/orders/{$order->id}");

        $response->assertOk()->assertJsonPath('data.id', $order->id);
    }

    public function test_a_non_admin_cannot_list_orders(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/admin/orders')->assertForbidden();
    }
}
