<?php

namespace Tests\Feature;

use App\Enums\PushNotificationType;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\Quote;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\ChatService;
use App\Services\DisputeService;
use App\Services\OrderService;
use App\Services\ProfessionalRegistrationService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Vérifie que chaque événement métier déjà en place (RDV, devis, chat,
 * inscription professionnelle, réclamation, commande) déclenche bien la
 * notification dédiée sur le bon destinataire (CLAUDE.md §5, ajout v0.12).
 */
class PushNotificationTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create();
    }

    public function test_appointment_request_notifies_the_garage_owner(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();

        app(AppointmentService::class)->create($client, $garage, null, [
            'description' => 'Fuite d\'huile',
            'requested_at' => now()->addDay()->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $garage->user_id,
            'type' => PushNotificationType::AppointmentRequested->value,
        ]);
    }

    public function test_appointment_confirmation_rejection_and_reschedule_notify_the_client(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $service = app(AppointmentService::class);

        $confirmed = Appointment::factory()->forGarage($garage)->create(['user_id' => $client->id]);
        $service->confirm($confirmed);
        $this->assertDatabaseHas('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::AppointmentConfirmed->value]);

        $rejected = Appointment::factory()->forGarage($garage)->create(['user_id' => $client->id]);
        $service->reject($rejected, 'Complet ce jour-là.');
        $this->assertDatabaseHas('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::AppointmentRejected->value]);

        $rescheduled = Appointment::factory()->forGarage($garage)->create(['user_id' => $client->id]);
        $service->reschedule($rescheduled, now()->addDays(3));
        $this->assertDatabaseHas('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::AppointmentRescheduled->value]);
    }

    public function test_quote_lifecycle_notifies_the_right_side_at_each_step(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $service = app(QuoteService::class);

        $quote = $service->createDraft($garage, $client, null, [
            ['type' => 'diagnosis_fee', 'unit_price' => 5000],
        ]);
        $version = $quote->currentVersion()->first();

        $service->send($version);
        $this->assertDatabaseHas('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::QuoteSent->value]);

        $service->accept($quote, $version, $client);
        $this->assertDatabaseHas('push_notifications', ['user_id' => $garage->user_id, 'type' => PushNotificationType::QuoteAccepted->value]);

        $service->start($quote);
        $service->markPaid($quote->fresh());
        $this->assertDatabaseHas('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::QuoteInvoiced->value]);
    }

    public function test_quote_rejection_notifies_the_garage_owner(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $service = app(QuoteService::class);

        $quote = $service->createDraft($garage, $client, null, [
            ['type' => 'diagnosis_fee', 'unit_price' => 5000],
        ]);
        $version = $quote->currentVersion()->first();
        $service->send($version);
        $service->reject($quote, $version, $client);

        $this->assertDatabaseHas('push_notifications', ['user_id' => $garage->user_id, 'type' => PushNotificationType::QuoteRejected->value]);
    }

    public function test_a_human_chat_message_notifies_the_other_participant_only(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $chatService = app(ChatService::class);

        $conversation = $chatService->findOrCreateConversation($garage, $client);
        $chatService->sendMessage($conversation, $client, ['body' => 'Bonjour, un souci de démarrage.'], null);

        $this->assertDatabaseHas('push_notifications', ['user_id' => $garage->user_id, 'type' => PushNotificationType::MessageReceived->value]);
        $this->assertDatabaseMissing('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::MessageReceived->value]);
    }

    /**
     * Un message système (génération de devis) est déjà couvert par sa
     * propre notification dédiée : pas de doublon "message_received" pour
     * le même événement (CLAUDE.md §5, ajout v0.12).
     */
    public function test_a_system_quote_message_does_not_also_trigger_a_generic_message_notification(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $service = app(QuoteService::class);

        $quote = $service->createDraft($garage, $client, null, [
            ['type' => 'diagnosis_fee', 'unit_price' => 5000],
        ]);
        $service->send($quote->currentVersion()->first());

        $this->assertDatabaseMissing('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::MessageReceived->value]);
        $this->assertDatabaseHas('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::QuoteSent->value]);
    }

    public function test_registration_decisions_notify_the_professional_account(): void
    {
        $service = app(ProfessionalRegistrationService::class);

        $approved = ProfessionalRegistration::factory()->create();
        $service->approve($approved, User::factory()->admin()->create());
        $this->assertDatabaseHas('push_notifications', ['user_id' => $approved->user_id, 'type' => PushNotificationType::RegistrationApproved->value]);

        $rejected = ProfessionalRegistration::factory()->create();
        $service->reject($rejected, User::factory()->admin()->create(), 'Justificatifs illisibles.');
        $this->assertDatabaseHas('push_notifications', ['user_id' => $rejected->user_id, 'type' => PushNotificationType::RegistrationRejected->value]);

        $approvedRegistration = ProfessionalRegistration::factory()->approved()->create();
        $service->suspend($approvedRegistration, User::factory()->admin()->create(), 'Plaintes répétées.');
        $this->assertDatabaseHas('push_notifications', ['user_id' => $approvedRegistration->user_id, 'type' => PushNotificationType::AccountSuspended->value]);

        $service->reactivate($approvedRegistration->fresh(), User::factory()->admin()->create());
        $this->assertDatabaseHas('push_notifications', ['user_id' => $approvedRegistration->user_id, 'type' => PushNotificationType::AccountReactivated->value]);
    }

    public function test_dispute_submission_and_decision_notify_the_right_sides(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $quote = Quote::factory()->forGarage($garage)->forClient($client)->invoiced()->create();

        $disputeService = app(DisputeService::class);
        $dispute = $disputeService->createForQuote($quote, $client, ['reason' => 'Pièce défectueuse.'], []);

        $this->assertDatabaseHas('push_notifications', ['user_id' => $garage->user_id, 'type' => PushNotificationType::DisputeSubmitted->value]);

        $disputeService->resolveRejected($dispute, User::factory()->admin()->create(), 'Aucune preuve suffisante.');

        $this->assertDatabaseHas('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::DisputeDecided->value]);
    }

    public function test_order_status_changes_notify_the_buyer(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        $orderService = app(OrderService::class);

        $paidOrder = Order::factory()->forGarage($garage)->forClient($client)->create();
        $paidOrder->lines()->create([
            'product_id' => Product::factory()->forGarage($garage)->approved()->create(['stock_quantity' => 5])->id,
            'label' => 'Filtre à huile',
            'unit_price' => 3000,
            'quantity' => 1,
            'line_total' => 3000,
        ]);
        $orderService->markPaid($paidOrder);
        $this->assertDatabaseHas('push_notifications', ['user_id' => $client->id, 'type' => PushNotificationType::OrderStatusChanged->value]);

        $cancelledOrder = Order::factory()->forGarage($garage)->forClient($client)->create();
        $orderService->cancel($cancelledOrder);
        $this->assertDatabaseCount('push_notifications', 2);
    }
}
