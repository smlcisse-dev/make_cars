<?php

namespace Tests\Feature\Mobile;

use App\Models\Conversation;
use App\Models\Garage;
use App\Models\Message;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create();
    }

    public function test_an_automobiliste_can_start_a_conversation_with_a_garage_without_any_appointment(): void
    {
        $garage = $this->approvedGarage();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/mobile/conversations', ['garage_id' => $garage->id]);

        $response->assertCreated();
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_starting_a_conversation_twice_returns_the_same_one(): void
    {
        $garage = $this->approvedGarage();
        $client = User::factory()->create();
        Sanctum::actingAs($client);

        $first = $this->postJson('/api/mobile/conversations', ['garage_id' => $garage->id])->json('data.id');
        $second = $this->postJson('/api/mobile/conversations', ['garage_id' => $garage->id])->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_starting_a_conversation_with_an_unapproved_garage_is_rejected(): void
    {
        $garage = Garage::factory()->for(User::factory()->garagiste())->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/mobile/conversations', ['garage_id' => $garage->id])->assertNotFound();
    }

    public function test_an_automobiliste_can_send_a_text_message(): void
    {
        $client = User::factory()->create();
        $conversation = Conversation::factory()->between($this->approvedGarage(), $client)->create();
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/mobile/conversations/{$conversation->id}/messages", [
            'body' => 'Bonjour, ma voiture fait un bruit bizarre.',
        ]);

        $response->assertCreated()->assertJsonPath('data.body', 'Bonjour, ma voiture fait un bruit bizarre.');
        $this->assertNotNull($conversation->fresh()->last_message_at);
    }

    public function test_an_automobiliste_can_send_an_image_message(): void
    {
        $client = User::factory()->create();
        $conversation = Conversation::factory()->between($this->approvedGarage(), $client)->create();
        Sanctum::actingAs($client);

        $response = $this->post("/api/mobile/conversations/{$conversation->id}/messages", [
            'image' => UploadedFile::fake()->image('tableau-de-bord.jpg'),
        ]);

        $response->assertCreated()->assertJsonPath('data.has_image', true);
    }

    public function test_sending_an_empty_message_is_rejected(): void
    {
        $client = User::factory()->create();
        $conversation = Conversation::factory()->between($this->approvedGarage(), $client)->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/mobile/conversations/{$conversation->id}/messages", [])
            ->assertUnprocessable();
    }

    public function test_an_automobiliste_can_list_its_conversations(): void
    {
        $client = User::factory()->create();
        Conversation::factory()->between($this->approvedGarage(), $client)->create();
        Conversation::factory()->create();
        Sanctum::actingAs($client);

        $response = $this->getJson('/api/mobile/conversations');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_automobiliste_cannot_access_anothers_conversation(): void
    {
        $conversation = Conversation::factory()->between($this->approvedGarage(), User::factory()->create())->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/mobile/conversations/{$conversation->id}/messages")->assertNotFound();
    }

    public function test_an_automobiliste_can_download_an_image_attachment_it_sent(): void
    {
        $client = User::factory()->create();
        $conversation = Conversation::factory()->between($this->approvedGarage(), $client)->create();
        $message = Message::factory()->inConversation($conversation)->from($client)->withImage()->create();
        Storage::disk('local')->put($message->image_path, 'fake-bytes');
        Sanctum::actingAs($client);

        $this->get("/api/mobile/conversations/{$conversation->id}/messages/{$message->id}/image")->assertOk();
    }

    public function test_a_non_automobiliste_cannot_access_the_mobile_chat_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/mobile/conversations')->assertForbidden();
    }
}
