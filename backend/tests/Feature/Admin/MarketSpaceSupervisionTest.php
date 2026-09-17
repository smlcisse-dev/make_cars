<?php

namespace Tests\Feature\Admin;

use App\Models\MarketSpaceAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketSpaceSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_every_market_space_account_regardless_of_validation_status(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        MarketSpaceAccount::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/market-space-accounts');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_an_admin_can_view_a_market_space_account(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $account = MarketSpaceAccount::factory()->create();

        $response = $this->getJson("/api/admin/market-space-accounts/{$account->id}");

        $response->assertOk()->assertJsonPath('data.id', $account->id);
    }

    public function test_a_non_admin_cannot_list_market_space_accounts_via_the_admin_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->marketSpace()->create());

        $this->getJson('/api/admin/market-space-accounts')->assertForbidden();
    }
}
