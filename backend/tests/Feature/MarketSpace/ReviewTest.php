<?php

namespace Tests\Feature\MarketSpace;

use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Le Market Space consulte ses avis reçus en lecture seule (CLAUDE.md §5,
 * ajout v0.10).
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function approvedMarketSpaceAccount(): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->approved()->create([
            'user_id' => User::factory()->marketSpace(),
        ]);

        return MarketSpaceAccount::factory()->for($registration->user)->create();
    }

    public function test_a_market_space_account_can_list_its_received_reviews(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        Review::factory()->forMarketSpace($account)->create();
        Review::factory()->create();
        Sanctum::actingAs($account->user);

        $this->getJson('/api/market-space/reviews')->assertOk()->assertJsonCount(1, 'data');
    }
}
