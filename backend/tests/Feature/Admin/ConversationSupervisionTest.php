<?php

namespace Tests\Feature\Admin;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_all_conversations(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Conversation::factory()->count(2)->create();

        $response = $this->getJson('/api/admin/conversations');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_an_admin_can_view_a_conversation_with_its_messages(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $conversation = Conversation::factory()->create();
        Message::factory()->inConversation($conversation)->count(2)->create();

        $response = $this->getJson("/api/admin/conversations/{$conversation->id}");

        $response->assertOk()->assertJsonCount(2, 'data.messages');
    }

    public function test_a_non_admin_cannot_list_conversations(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/admin/conversations')->assertForbidden();
    }
}
