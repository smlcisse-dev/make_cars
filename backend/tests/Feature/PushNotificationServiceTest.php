<?php

namespace Tests\Feature;

use App\Enums\PushNotificationStatus;
use App\Enums\PushNotificationType;
use App\Models\DeviceToken;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\PushNotification;
use App\Models\Quote;
use App\Models\User;
use App\Services\ProductService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tant que FCM_SERVER_KEY n'est pas configuré, le service fonctionne en
 * mode simulation : la notification reste "created" ("en attente d'envoi
 * réel"), jamais d'erreur bloquante (CLAUDE.md §5, ajout v0.12).
 */
class PushNotificationServiceTest extends TestCase
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

    public function test_notification_stays_created_when_fcm_is_not_configured(): void
    {
        config(['services.fcm.server_key' => null]);
        Http::fake();

        $user = User::factory()->create();
        $notification = app(PushNotificationService::class)->notify($user, PushNotificationType::AppointmentRequested, 'Titre', 'Corps');

        $this->assertSame(PushNotificationStatus::Created, $notification->status);
        $this->assertNull($notification->sent_at);
        Http::assertNothingSent();
    }

    public function test_notification_is_sent_when_fcm_is_configured_and_a_device_token_exists(): void
    {
        config(['services.fcm.server_key' => 'test-server-key']);
        Http::fake(['https://fcm.googleapis.com/*' => Http::response(['success' => 1])]);

        $user = User::factory()->create();
        DeviceToken::factory()->forUser($user)->create(['token' => 'device-token-1']);

        $notification = app(PushNotificationService::class)->notify($user, PushNotificationType::AppointmentRequested, 'Titre', 'Corps');

        $this->assertSame(PushNotificationStatus::Sent, $notification->status);
        $this->assertNotNull($notification->sent_at);
        Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/fcm/send'
            && $request->hasHeader('Authorization', 'key=test-server-key'));
    }

    public function test_notification_fails_without_blocking_when_fcm_call_errors(): void
    {
        config(['services.fcm.server_key' => 'test-server-key']);
        Http::fake(['https://fcm.googleapis.com/*' => Http::response(['error' => 'InvalidRegistration'], 400)]);

        $user = User::factory()->create();
        DeviceToken::factory()->forUser($user)->create();

        $notification = app(PushNotificationService::class)->notify($user, PushNotificationType::AppointmentRequested, 'Titre');

        $this->assertSame(PushNotificationStatus::Failed, $notification->status);
        $this->assertNotNull($notification->failed_reason);
    }

    public function test_notification_stays_created_when_fcm_is_configured_but_user_has_no_device_token(): void
    {
        config(['services.fcm.server_key' => 'test-server-key']);
        Http::fake();

        $user = User::factory()->create();
        $notification = app(PushNotificationService::class)->notify($user, PushNotificationType::AppointmentRequested, 'Titre');

        $this->assertSame(PushNotificationStatus::Created, $notification->status);
        Http::assertNothingSent();
    }

    public function test_mark_read_is_idempotent(): void
    {
        $notification = PushNotification::factory()->create();

        $service = app(PushNotificationService::class);
        $first = $service->markRead($notification);
        $readAt = $first->read_at;

        $second = $service->markRead($first->fresh());

        $this->assertNotNull($readAt);
        $this->assertTrue($readAt->equalTo($second->read_at));
    }

    public function test_registering_a_device_token_upserts_by_token_and_reassigns_ownership(): void
    {
        $service = app(PushNotificationService::class);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $token = $service->registerDeviceToken($firstUser, 'shared-device-token', 'android');
        $this->assertSame($firstUser->id, $token->user_id);
        $this->assertDatabaseCount('device_tokens', 1);

        // Même jeton, nouveau compte connecté sur le même appareil.
        $token = $service->registerDeviceToken($secondUser, 'shared-device-token', 'android');
        $this->assertSame($secondUser->id, $token->user_id);
        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_low_stock_alert_fires_once_until_restocked_above_threshold(): void
    {
        Http::fake();
        $garage = $this->approvedGarage();
        $product = Product::factory()->forGarage($garage)->approved()->create([
            'stock_quantity' => 5,
            'low_stock_threshold' => 3,
        ]);

        $productService = app(ProductService::class);

        $productService->decrementStock($product, 2); // 5 -> 3, au seuil : alerte
        $this->assertDatabaseCount('push_notifications', 1);
        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $garage->user_id,
            'type' => PushNotificationType::ProductLowStock->value,
        ]);

        $productService->decrementStock($product->fresh(), 1); // 3 -> 2, toujours sous le seuil : pas de nouvelle alerte
        $this->assertDatabaseCount('push_notifications', 1);

        $productService->updateStock($product->fresh(), 10); // Réapprovisionnement au-dessus du seuil : réarme l'alerte
        $productService->decrementStock($product->fresh(), 8); // 10 -> 2, sous le seuil à nouveau : nouvelle alerte
        $this->assertDatabaseCount('push_notifications', 2);
    }

    public function test_no_low_stock_alert_without_a_configured_threshold(): void
    {
        Http::fake();
        $garage = $this->approvedGarage();
        $product = Product::factory()->forGarage($garage)->approved()->create([
            'stock_quantity' => 5,
            'low_stock_threshold' => null,
        ]);

        app(ProductService::class)->decrementStock($product, 5);

        $this->assertDatabaseCount('push_notifications', 0);
    }

    public function test_new_product_notifies_only_past_clients_of_that_seller_not_all_automobilistes(): void
    {
        Http::fake();
        $garage = $this->approvedGarage();
        $unrelatedGarage = $this->approvedGarage();

        $pastQuoteClient = User::factory()->create();
        Quote::factory()->forGarage($garage)->forClient($pastQuoteClient)->invoiced()->create();

        $pastOrderClient = User::factory()->create();
        Order::factory()->forGarage($garage)->forClient($pastOrderClient)->paid()->create();

        $strangerWithUnrelatedGarageHistory = User::factory()->create();
        Order::factory()->forGarage($unrelatedGarage)->forClient($strangerWithUnrelatedGarageHistory)->paid()->create();

        $strangerWithNoHistory = User::factory()->create();

        $pendingOrderClient = User::factory()->create();
        Order::factory()->forGarage($garage)->forClient($pendingOrderClient)->create();

        $product = Product::factory()->forGarage($garage)->create();

        app(ProductService::class)->approve($product, User::factory()->admin()->create());

        $this->assertDatabaseHas('push_notifications', ['user_id' => $pastQuoteClient->id, 'type' => PushNotificationType::ProductPublished->value]);
        $this->assertDatabaseHas('push_notifications', ['user_id' => $pastOrderClient->id, 'type' => PushNotificationType::ProductPublished->value]);
        $this->assertDatabaseMissing('push_notifications', ['user_id' => $strangerWithUnrelatedGarageHistory->id, 'type' => PushNotificationType::ProductPublished->value]);
        $this->assertDatabaseMissing('push_notifications', ['user_id' => $strangerWithNoHistory->id, 'type' => PushNotificationType::ProductPublished->value]);
        $this->assertDatabaseMissing('push_notifications', ['user_id' => $pendingOrderClient->id, 'type' => PushNotificationType::ProductPublished->value]);
    }

    public function test_new_market_space_product_never_counts_quotes_as_past_transactions(): void
    {
        Http::fake();
        $account = $this->approvedMarketSpaceAccount();
        $client = User::factory()->create();
        Order::factory()->forMarketSpace($account)->forClient($client)->paid()->create();

        $product = Product::factory()->forMarketSpace($account)->create();
        app(ProductService::class)->approve($product, User::factory()->admin()->create());

        $this->assertDatabaseHas('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::ProductPublished->value]);
    }
}
