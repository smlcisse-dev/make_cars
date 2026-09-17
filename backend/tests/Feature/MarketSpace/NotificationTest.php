<?php

namespace Tests\Feature\MarketSpace;

use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function approvedMarketSpaceAccount(): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->approved()->create([
            'user_id' => User::factory()->marketSpace(),
        ]);

        return MarketSpaceAccount::factory()->for($registration->user)->create();
    }

    public function test_a_market_space_account_can_list_only_its_own_notifications(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        PushNotification::factory()->forUser($account->user)->create();
        PushNotification::factory()->create();
        Sanctum::actingAs($account->user);

        $this->getJson('/api/market-space/notifications')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_market_space_account_can_register_a_device_token(): void
    {
        $account = $this->approvedMarketSpaceAccount();
        Sanctum::actingAs($account->user);

        $this->putJson('/api/market-space/device-tokens', ['token' => 'market-space-dashboard-token'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', ['user_id' => $account->user_id, 'token' => 'market-space-dashboard-token']);
    }
}
