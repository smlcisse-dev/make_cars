<?php

namespace Tests\Feature\MarketSpace;

use App\Models\MarketSpaceAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_market_space_account_without_an_approved_registration_has_no_profile_yet(): void
    {
        Sanctum::actingAs(User::factory()->marketSpace()->create());

        $response = $this->getJson('/api/market-space/profile');

        $response->assertNotFound();
    }

    public function test_a_market_space_account_can_view_its_own_profile(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $response = $this->getJson('/api/market-space/profile');

        $response->assertOk()->assertJsonPath('data.id', $account->id);
    }

    public function test_a_market_space_account_can_update_its_profile(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $response = $this->putJson('/api/market-space/profile', [
            'name' => 'Pièces Auto Fidjrossè',
            'description' => 'Grand choix de pièces neuves et d\'occasion.',
            'address' => 'Fidjrossè, Cotonou',
            'latitude' => 6.3703,
            'longitude' => 2.3912,
            'phone' => '+22997000000',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Pièces Auto Fidjrossè');
        $this->assertDatabaseHas('market_space_accounts', [
            'id' => $account->id,
            'name' => 'Pièces Auto Fidjrossè',
            'address' => 'Fidjrossè, Cotonou',
        ]);
    }

    public function test_a_market_space_account_cannot_view_another_accounts_profile_via_the_endpoint(): void
    {
        MarketSpaceAccount::factory()->create();
        $otherAccountUser = User::factory()->marketSpace()->create();
        Sanctum::actingAs($otherAccountUser);

        $this->getJson('/api/market-space/profile')->assertNotFound();
    }

    public function test_a_non_market_space_account_cannot_access_the_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/market-space/profile')->assertForbidden();
    }
}
