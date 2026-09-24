<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Exceptions\ApiException;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\User;
use App\Services\OrderService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Appels simultanés sur un même devis/une même commande (double clic, lien
 * email + application). SQLite ignore `lockForUpdate`, mais chaque appel
 * relit l'état sous verrou avant d'agir : on reproduit ici le second appel
 * d'une course, qui travaille sur des objets lus AVANT la première décision.
 */
class ConcurrentStockDecisionTest extends TestCase
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

        return Garage::factory()->complete()->for($registration->user)->create();
    }

    public function test_two_decisions_on_a_version_read_before_the_lock_decrement_stock_only_once(): void
    {
        $client = User::factory()->create();
        $garage = $this->approvedGarage();
        $appointment = Appointment::factory()->forGarage($garage)->for($client)->confirmed()->create();
        $product = Product::factory()->forGarage($garage)->approved()->create(['stock_quantity' => 10]);
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        $version->lines()->create([
            'type' => 'product', 'product_id' => $product->id, 'label' => $product->name,
            'unit_price' => $product->price, 'quantity' => 3, 'line_total' => $product->price * 3,
        ]);

        // Les deux « requêtes » ont lu le devis et la version avant toute décision.
        $staleQuote = Quote::find($quote->id);
        $staleVersion = QuoteVersion::find($version->id);

        $service = app(QuoteService::class);
        $service->accept($quote, $version, $client);

        try {
            $service->accept($staleQuote, $staleVersion, $client);
            $this->fail('La seconde décision aurait dû être refusée.');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('quote_version_not_decidable', $e->errorCode);
        }

        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
    }

    public function test_a_rejection_racing_an_acceptance_is_refused(): void
    {
        $client = User::factory()->create();
        $garage = $this->approvedGarage();
        $appointment = Appointment::factory()->forGarage($garage)->for($client)->confirmed()->create();
        $quote = Quote::factory()->forAppointment($appointment)->sent()->create();
        $version = QuoteVersion::factory()->forQuote($quote, 1)->sent()->create();
        $staleQuote = Quote::find($quote->id);
        $staleVersion = QuoteVersion::find($version->id);

        $service = app(QuoteService::class);
        $service->accept($quote, $version, $client);

        $this->expectException(ApiException::class);
        $service->reject($staleQuote, $staleVersion, $client);
    }

    public function test_marking_an_order_paid_twice_decrements_stock_only_once(): void
    {
        $garage = $this->approvedGarage();
        $product = Product::factory()->forGarage($garage)->approved()->create(['stock_quantity' => 10]);
        $order = Order::factory()->forGarage($garage)->create();
        OrderLine::factory()->forOrder($order)->forProduct($product, 3)->create();
        $staleOrder = Order::find($order->id);

        $service = app(OrderService::class);
        $service->markPaid($order);

        try {
            $service->markPaid($staleOrder);
            $this->fail('Le second paiement aurait dû être refusé.');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('order_not_pending', $e->errorCode);
        }

        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    public function test_cancelling_an_order_read_before_its_payment_is_refused(): void
    {
        $garage = $this->approvedGarage();
        $order = Order::factory()->forGarage($garage)->create();
        OrderLine::factory()->forOrder($order)->create();
        $staleOrder = Order::find($order->id);

        $service = app(OrderService::class);
        $service->markPaid($order);

        $this->expectException(ApiException::class);
        $service->cancel($staleOrder);
    }

    public function test_marking_a_quote_paid_twice_creates_a_single_invoice(): void
    {
        $client = User::factory()->create();
        $garage = $this->approvedGarage();
        $appointment = Appointment::factory()->forGarage($garage)->for($client)->confirmed()->create();
        $quote = Quote::factory()->forAppointment($appointment)->create(['status' => QuoteStatus::InProgress]);
        QuoteVersion::factory()->forQuote($quote, 1)->sent()->create(['decision' => 'accepted', 'decided_at' => now()]);
        $staleQuote = Quote::find($quote->id);

        $service = app(QuoteService::class);
        $service->markPaid($quote);

        try {
            $service->markPaid($staleQuote);
            $this->fail('Le second paiement aurait dû être refusé.');
        } catch (ApiException $e) {
            $this->assertSame('quote_not_in_progress', $e->errorCode);
        }

        $this->assertSame(1, $quote->versions()->where('document_type', 'invoice')->count());
    }
}
