<?php

namespace Tests\Feature\Mobile;

use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\ProfessionalRegistration;
use App\Models\Quote;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Un avis n'est possible qu'après une transaction terminée — un devis
 * facturé ou une commande payée, jamais avant (CLAUDE.md §5, règle 7 et
 * ajout v0.10).
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_an_automobiliste_can_review_an_invoiced_quote(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->invoiced()->create();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/mobile/quotes/{$quote->id}/review", [
            'rating' => 5,
            'comment' => 'Excellent travail, garage très sérieux.',
        ]);

        $response->assertCreated()->assertJsonPath('data.reviewable_type', 'garage');
        $this->assertDatabaseHas('reviews', [
            'reviewable_type' => Garage::class,
            'reviewable_id' => $garage->id,
            'transaction_type' => Quote::class,
            'transaction_id' => $quote->id,
            'user_id' => $client->id,
            'rating' => 5,
        ]);
    }

    public function test_reviewing_a_quote_not_yet_invoiced_is_forbidden(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->accepted()->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/quotes/{$quote->id}/review", ['rating' => 4])
            ->assertForbidden();
    }

    public function test_reviewing_another_clients_quote_is_rejected(): void
    {
        $garage = $this->approvedGarage();
        $quote = Quote::factory()->forGarage($garage)->invoiced()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/mobile/quotes/{$quote->id}/review", ['rating' => 4])
            ->assertNotFound();
    }

    public function test_a_quote_can_only_be_reviewed_once(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->invoiced()->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/quotes/{$quote->id}/review", ['rating' => 5])->assertCreated();
        $this->postJson("/api/mobile/quotes/{$quote->id}/review", ['rating' => 3])->assertUnprocessable();
    }

    public function test_an_automobiliste_can_review_a_paid_order_from_a_garage_mini_boutique(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $order = Order::factory()->forGarage($garage)->forClient($client)->paid()->create();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/mobile/orders/{$order->id}/review", ['rating' => 4]);

        $response->assertCreated()->assertJsonPath('data.reviewable_type', 'garage');
        $this->assertDatabaseHas('reviews', [
            'reviewable_type' => Garage::class,
            'reviewable_id' => $garage->id,
            'transaction_type' => Order::class,
            'transaction_id' => $order->id,
        ]);
    }

    public function test_an_automobiliste_can_review_a_paid_order_from_a_market_space(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        $client = User::factory()->create();
        $order = Order::factory()->forMarketSpace($account)->forClient($client)->paid()->create();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/mobile/orders/{$order->id}/review", ['rating' => 3]);

        $response->assertCreated()->assertJsonPath('data.reviewable_type', 'market_space');
    }

    public function test_reviewing_an_unpaid_order_is_forbidden(): void
    {
        $client = User::factory()->create();
        $order = Order::factory()->forClient($client)->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/orders/{$order->id}/review", ['rating' => 4])
            ->assertForbidden();
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->invoiced()->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/quotes/{$quote->id}/review", ['rating' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');
    }

    public function test_comment_is_optional(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->invoiced()->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/quotes/{$quote->id}/review", ['rating' => 5])
            ->assertCreated()
            ->assertJsonPath('data.comment', null);
    }

    public function test_an_automobiliste_can_list_its_own_reviews(): void
    {
        $client = User::factory()->create();
        Review::factory()->forClient($client)->create();
        Review::factory()->create();
        Sanctum::actingAs($client);

        $this->getJson('/api/mobile/reviews')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_public_garage_reviews_exclude_hidden_ones(): void
    {
        $garage = $this->approvedGarage();
        Review::factory()->forGarage($garage)->create();
        Review::factory()->forGarage($garage)->hidden()->create();

        $this->getJson("/api/mobile/garages/{$garage->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_garage_listing_exposes_average_rating(): void
    {
        $garage = $this->approvedGarage();
        Review::factory()->forGarage($garage)->create(['rating' => 5]);
        Review::factory()->forGarage($garage)->create(['rating' => 2]);
        Review::factory()->forGarage($garage)->hidden()->create(['rating' => 1]);

        $response = $this->getJson('/api/mobile/garages');

        $response->assertOk()
            ->assertJsonPath('data.0.average_rating', 3.5)
            ->assertJsonPath('data.0.reviews_count', 2);
    }
}
