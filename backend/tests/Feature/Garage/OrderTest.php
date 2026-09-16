<?php

namespace Tests\Feature\Garage;

use App\Enums\OrderStatus;
use App\Models\Garage;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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

    public function test_a_garagiste_can_list_and_view_its_orders(): void
    {
        $garage = $this->approvedGarage();
        Order::factory()->forGarage($garage)->create();
        Order::factory()->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/orders')->assertOk()->assertJsonCount(1, 'data');
    }

    /**
     * Paiement manuel V1 : décrémente le stock (même mécanisme unique que le
     * module Devis — CLAUDE.md §5, ajout v0.4) et notifie via le chat.
     */
    public function test_marking_an_order_paid_decrements_stock_generates_invoice_and_notifies_via_chat(): void
    {
        $garage = $this->approvedGarage();
        $product = Product::factory()->forGarage($garage)->approved()->create(['stock_quantity' => 10]);
        $order = Order::factory()->forGarage($garage)->create();
        OrderLine::factory()->forOrder($order)->forProduct($product, 3)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/orders/{$order->id}/mark-paid");

        $response->assertOk()->assertJsonPath('data.status', OrderStatus::Paid->value);
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertNotNull($order->fresh()->pdf_path);
        $this->assertDatabaseHas('messages', [
            'attachment_type' => Message::ATTACHMENT_ORDER_PDF,
            'order_id' => $order->id,
            'sender_id' => null,
        ]);
    }

    public function test_marking_an_already_paid_order_paid_is_forbidden(): void
    {
        $garage = $this->approvedGarage();
        $order = Order::factory()->forGarage($garage)->paid()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/orders/{$order->id}/mark-paid")->assertForbidden();
    }

    public function test_a_garagiste_can_download_the_invoice(): void
    {
        $garage = $this->approvedGarage();
        $order = Order::factory()->forGarage($garage)->paid()->create([
            'pdf_disk' => 'local',
            'pdf_path' => 'orders/test.pdf',
        ]);
        Storage::disk('local')->put('orders/test.pdf', '%PDF-1.7 fake');
        Sanctum::actingAs($garage->user);

        $this->get("/api/garage/orders/{$order->id}/pdf")->assertOk();
    }

    public function test_a_garagiste_cannot_manage_another_garages_order(): void
    {
        $order = Order::factory()->create();
        $otherGarage = $this->approvedGarage();
        Sanctum::actingAs($otherGarage->user);

        $this->getJson("/api/garage/orders/{$order->id}")->assertNotFound();
    }
}
