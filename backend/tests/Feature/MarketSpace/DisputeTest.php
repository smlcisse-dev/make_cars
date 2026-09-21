<?php

namespace Tests\Feature\MarketSpace;

use App\Models\Dispute;
use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Le Market Space consulte les réclamations le concernant et peut y
 * répondre (CLAUDE.md §5, ajout v0.11).
 */
class DisputeTest extends TestCase
{
    use RefreshDatabase;

    private function approvedMarketSpaceAccount(): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->approved()->create([
            'user_id' => User::factory()->marketSpace(),
        ]);

        return MarketSpaceAccount::factory()->complete()->for($registration->user)->create();
    }

    public function test_a_market_space_account_can_list_disputes_concerning_it(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        Dispute::factory()->forMarketSpace($account)->create();
        Dispute::factory()->create();
        Sanctum::actingAs($account->user);

        $this->getJson('/api/market-space/disputes')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_disputes_list_exposes_the_client(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        $dispute = Dispute::factory()->forMarketSpace($account)->create();
        Sanctum::actingAs($account->user);

        $this->getJson('/api/market-space/disputes')
            ->assertOk()
            ->assertJsonPath('data.0.client.id', $dispute->user_id);
    }

    public function test_a_market_space_account_can_filter_its_disputes_by_status(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        $submitted = Dispute::factory()->forMarketSpace($account)->create();
        Dispute::factory()->forMarketSpace($account)->resolvedRejected()->create();
        Sanctum::actingAs($account->user);

        $this->getJson('/api/market-space/disputes?status=submitted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $submitted->id);
        $this->getJson('/api/market-space/disputes')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_market_space_account_can_respond_to_a_dispute(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        $dispute = Dispute::factory()->forMarketSpace($account)->create();
        Sanctum::actingAs($account->user);

        $this->postJson("/api/market-space/disputes/{$dispute->id}/respond", [
            'body' => 'Le produit expédié correspond bien à la commande.',
        ])->assertCreated();
    }
}
