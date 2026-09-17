<?php

namespace Tests\Feature\Mobile;

use App\Models\MarketSpaceAccount;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketSpaceListingTest extends TestCase
{
    use RefreshDatabase;

    private function approvedAccount(): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->marketSpace()->approved()->create();

        return MarketSpaceAccount::factory()->for($registration->user)->create();
    }

    private function suspendedAccount(): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->marketSpace()->suspended()->create();

        return MarketSpaceAccount::factory()->for($registration->user)->create();
    }

    public function test_the_mobile_app_only_lists_market_space_accounts_with_an_approved_registration(): void
    {
        $this->approvedAccount();
        MarketSpaceAccount::factory()->for(User::factory()->marketSpace())->create();

        $response = $this->getJson('/api/mobile/market-space-accounts');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_unapproved_account_is_not_publicly_viewable(): void
    {
        $account = MarketSpaceAccount::factory()->for(User::factory()->marketSpace())->create();

        $this->getJson("/api/mobile/market-space-accounts/{$account->id}")->assertNotFound();
    }

    /**
     * Numéro de contact exposé pour permettre à l'automobiliste d'appeler
     * directement (CLAUDE.md §5, ajout v0.13).
     */
    public function test_the_market_space_profile_exposes_a_contact_phone_number(): void
    {
        $account = $this->approvedAccount();

        $this->getJson("/api/mobile/market-space-accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('data.phone', $account->phone);
    }

    public function test_only_approved_products_appear_in_a_market_space_accounts_public_catalog(): void
    {
        $account = $this->approvedAccount();
        $approved = Product::factory()->forMarketSpace($account)->approved()->create();
        Product::factory()->forMarketSpace($account)->create();

        $response = $this->getJson("/api/mobile/market-space-accounts/{$account->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.id', $approved->id);
    }

    public function test_a_suspended_market_space_account_disappears_from_the_public_listing(): void
    {
        $this->approvedAccount();
        $this->suspendedAccount();

        $response = $this->getJson('/api/mobile/market-space-accounts');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_suspended_market_space_account_is_not_publicly_viewable(): void
    {
        $account = $this->suspendedAccount();

        $this->getJson("/api/mobile/market-space-accounts/{$account->id}")->assertNotFound();
    }
}
