<?php

namespace Tests\Feature\Garage;

use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use App\Models\PushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create();
    }

    public function test_a_garagiste_can_list_only_its_own_notifications(): void
    {
        $garage = $this->approvedGarage();
        PushNotification::factory()->forUser($garage->user)->create();
        PushNotification::factory()->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/notifications')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_garagiste_can_mark_its_own_notification_as_read(): void
    {
        $garage = $this->approvedGarage();
        $notification = PushNotification::factory()->forUser($garage->user)->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/notifications/{$notification->id}/read")->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_garagiste_can_register_a_device_token(): void
    {
        $garage = $this->approvedGarage();
        Sanctum::actingAs($garage->user);

        $this->putJson('/api/garage/device-tokens', ['token' => 'garage-dashboard-token'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', ['user_id' => $garage->user_id, 'token' => 'garage-dashboard-token']);
    }
}
