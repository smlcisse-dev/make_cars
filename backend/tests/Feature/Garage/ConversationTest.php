<?php

namespace Tests\Feature\Garage;

use App\Models\Conversation;
use App\Models\Garage;
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
        $garage = Garage::factory()->create();
        Conversation::factory()->between($garage, User::factory()->create())->create();
        Conversation::factory()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->getJson('/api/garage/conversations');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_garagiste_can_reply_in_a_conversation(): void
    {
        $garage = Garage::factory()->create();
        $conversation = Conversation::factory()->between($garage, User::factory()->create())->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson("/api/garage/conversations/{$conversation->id}/messages", [
            'body' => 'Bonjour, pouvez-vous passer demain matin ?',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'sender_id' => $garage->user_id]);
    }

    public function test_a_garagiste_cannot_access_another_garages_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $otherGarage = Garage::factory()->create();
        Sanctum::actingAs($otherGarage->user);

        $this->getJson("/api/garage/conversations/{$conversation->id}/messages")->assertNotFound();
    }

    public function test_a_non_garagiste_cannot_access_the_garage_chat_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/garage/conversations')->assertForbidden();
    }
}
