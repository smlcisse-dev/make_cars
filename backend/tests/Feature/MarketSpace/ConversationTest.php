<?php

namespace Tests\Feature\MarketSpace;

use App\Models\Conversation;
use App\Models\MarketSpaceAccount;
use App\Models\Message;
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

    public function test_a_market_space_account_can_list_its_conversations(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        Conversation::factory()->between($account, User::factory()->create())->create();
        Conversation::factory()->forMarketSpace()->create();
        Conversation::factory()->create();
        Sanctum::actingAs($account->user);

        $this->getJson('/api/market-space/conversations')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_market_space_account_can_reply_and_the_sender_is_present(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        $conversation = Conversation::factory()->between($account, User::factory()->create())->create();
        Sanctum::actingAs($account->user);

        $response = $this->postJson("/api/market-space/conversations/{$conversation->id}/messages", [
            'body' => 'Cette pièce est disponible en boutique.',
        ]);

        $response->assertCreated()->assertJsonPath('data.sender.id', $account->user_id);
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'sender_id' => $account->user_id]);
    }

    public function test_a_market_space_account_can_send_an_image_and_download_it(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        $conversation = Conversation::factory()->between($account, User::factory()->create())->create();
        Sanctum::actingAs($account->user);

        $response = $this->postJson("/api/market-space/conversations/{$conversation->id}/messages", [
            'image' => UploadedFile::fake()->image('piece.jpg'),
        ]);

        $response->assertCreated()->assertJsonPath('data.has_image', true);
        $message = Message::findOrFail($response->json('data.id'));
        Storage::disk('local')->assertExists($message->image_path);

        $this->get("/api/market-space/conversations/{$conversation->id}/messages/{$message->id}/image")->assertOk();
    }

    public function test_a_message_without_body_nor_image_is_rejected(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        $conversation = Conversation::factory()->between($account, User::factory()->create())->create();
        Sanctum::actingAs($account->user);

        $this->postJson("/api/market-space/conversations/{$conversation->id}/messages", [])->assertUnprocessable();
    }

    public function test_a_market_space_account_cannot_access_another_accounts_conversation(): void
    {
        $conversation = Conversation::factory()->forMarketSpace()->create();
        $message = Message::factory()->inConversation($conversation)->create();
        Sanctum::actingAs(MarketSpaceAccount::factory()->complete()->create()->user);

        $this->getJson("/api/market-space/conversations/{$conversation->id}/messages")->assertNotFound();
        $this->postJson("/api/market-space/conversations/{$conversation->id}/messages", ['body' => 'x'])->assertNotFound();
        $this->get("/api/market-space/conversations/{$conversation->id}/messages/{$message->id}/image")->assertNotFound();
    }

    public function test_a_garage_conversation_is_not_accessible_from_a_market_space_account_with_the_same_id(): void
    {
        // Même identifiant numérique, vendeur de type différent : le type
        // morphique fait partie de l'appartenance.
        $account = MarketSpaceAccount::factory()->complete()->create();
        $conversation = Conversation::factory()->create(['sellable_id' => $account->id]);
        Sanctum::actingAs($account->user);

        $this->getJson("/api/market-space/conversations/{$conversation->id}/messages")->assertNotFound();
    }

    public function test_a_garagiste_cannot_access_the_market_space_chat_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/market-space/conversations')->assertForbidden();
    }

    public function test_an_incomplete_profile_is_blocked_from_the_market_space_chat(): void
    {
        Sanctum::actingAs(MarketSpaceAccount::factory()->create()->user);

        $this->getJson('/api/market-space/conversations')->assertForbidden()->assertJsonPath('code', 'profile_incomplete');
    }
}
