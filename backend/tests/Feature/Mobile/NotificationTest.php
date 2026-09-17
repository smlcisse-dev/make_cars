<?php

namespace Tests\Feature\Mobile;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_automobiliste_can_list_only_its_own_notifications(): void
    {
        $client = User::factory()->create();
        PushNotification::factory()->forUser($client)->create();
        PushNotification::factory()->create();
        Sanctum::actingAs($client);

        $this->getJson('/api/mobile/notifications')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_automobiliste_can_mark_its_own_notification_as_read(): void
    {
        $client = User::factory()->create();
        $notification = PushNotification::factory()->forUser($client)->create();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/mobile/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_an_automobiliste_cannot_mark_anothers_notification_as_read(): void
    {
        $notification = PushNotification::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/mobile/notifications/{$notification->id}/read")->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_an_automobiliste_can_register_a_device_token(): void
    {
        $client = User::factory()->create();
        Sanctum::actingAs($client);

        $this->putJson('/api/mobile/device-tokens', ['token' => 'flutter-device-token', 'platform' => 'android'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $client->id,
            'token' => 'flutter-device-token',
            'platform' => 'android',
        ]);
    }

    public function test_registering_the_same_token_again_updates_it_instead_of_duplicating(): void
    {
        $client = User::factory()->create();
        DeviceToken::factory()->forUser($client)->create(['token' => 'existing-token']);
        Sanctum::actingAs($client);

        $this->putJson('/api/mobile/device-tokens', ['token' => 'existing-token', 'platform' => 'ios'])
            ->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['token' => 'existing-token', 'platform' => 'ios']);
    }

    public function test_device_token_is_required(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/mobile/device-tokens', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');
    }
}
