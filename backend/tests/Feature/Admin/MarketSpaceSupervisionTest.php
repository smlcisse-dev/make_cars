<?php

namespace Tests\Feature\Admin;

use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketSpaceSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_lists_approved_market_space_accounts_including_suspended_ones(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        MarketSpaceAccount::factory()->count(2)->withApprovedRegistration()->create();
        $suspended = ProfessionalRegistration::factory()->marketSpace()->suspended()->create();
        MarketSpaceAccount::factory()->for($suspended->user)->create();

        $response = $this->getJson('/api/admin/market-space-accounts');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_an_admin_can_view_an_approved_market_space_account(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $account = MarketSpaceAccount::factory()->withApprovedRegistration()->create();

        $response = $this->getJson("/api/admin/market-space-accounts/{$account->id}");

        $response->assertOk()->assertJsonPath('data.id', $account->id);
    }

    public function test_market_space_accounts_whose_dossier_is_not_approved_are_excluded_from_the_list_and_the_detail(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $approved = MarketSpaceAccount::factory()->withApprovedRegistration()->create();

        foreach ([
            ProfessionalRegistration::factory()->marketSpace()->profileIncomplete()->create(),
            ProfessionalRegistration::factory()->marketSpace()->pending()->create(),
        ] as $registration) {
            $account = MarketSpaceAccount::factory()->for($registration->user)->create();
            $this->getJson("/api/admin/market-space-accounts/{$account->id}")->assertNotFound();
        }

        $this->getJson('/api/admin/market-space-accounts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);
    }

    public function test_a_non_admin_cannot_list_market_space_accounts_via_the_admin_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->marketSpace()->create());

        $this->getJson('/api/admin/market-space-accounts')->assertForbidden();
    }
}
