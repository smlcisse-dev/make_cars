<?php

namespace Tests\Feature\Garage;

use App\Models\Conversation;
use App\Models\Garage;
use App\Models\Message;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_a_garagiste_can_list_its_conversations(): void
    {
        $garage = Garage::factory()->complete()->create();
        Conversation::factory()->between($garage, User::factory()->create())->create();
        Conversation::factory()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->getJson('/api/garage/conversations');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_garagiste_can_reply_in_a_conversation(): void
    {
        $garage = Garage::factory()->complete()->create();
        $conversation = Conversation::factory()->between($garage, User::factory()->create())->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/conversations/{$conversation->id}/messages", [
            'body' => 'Bonjour, pouvez-vous passer demain matin ?',
        ]);

        $response->assertCreated()->assertJsonPath('data.sender.id', $garage->user_id);
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'sender_id' => $garage->user_id]);
    }

    public function test_system_message_exposes_quote_id_and_normal_message_does_not(): void
    {
        $garage = Garage::factory()->complete()->create();
        $client = User::factory()->create();
        $conversation = Conversation::factory()->between($garage, $client)->create();
        $quote = Quote::factory()->create(['garage_id' => $garage->id, 'user_id' => $client->id]);
        $version = QuoteVersion::factory()->forQuote($quote)->sent()->create();
        $system = Message::factory()->inConversation($conversation)->system()->create(['quote_version_id' => $version->id]);
        $normal = Message::factory()->inConversation($conversation)->from($client)->create();
        Sanctum::actingAs($garage->user);

        $data = collect($this->getJson("/api/garage/conversations/{$conversation->id}/messages")->assertOk()->json('data'))->keyBy('id');

        $this->assertSame($quote->id, $data[$system->id]['quote_id']);
        $this->assertArrayNotHasKey('quote_id', $data[$normal->id]);
    }

    public function test_a_garagiste_cannot_access_another_garages_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $otherGarage = Garage::factory()->complete()->create();
        Sanctum::actingAs($otherGarage->user);

        $this->getJson("/api/garage/conversations/{$conversation->id}/messages")->assertNotFound();
    }

    public function test_a_non_garagiste_cannot_access_the_garage_chat_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/garage/conversations')->assertForbidden();
    }
}
