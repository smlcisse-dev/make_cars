<?php

namespace Tests\Feature\Admin;

use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_all_quotes(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Quote::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/quotes');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_an_admin_can_view_a_quote(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $quote = Quote::factory()->create();

        $response = $this->getJson("/api/admin/quotes/{$quote->id}");

        $response->assertOk()->assertJsonPath('data.id', $quote->id);
    }

    public function test_the_admin_sees_who_decided_a_version(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $quote = Quote::factory()->create();
        QuoteVersion::factory()->forQuote($quote)->accepted()->create(['decided_by' => $quote->user_id]);

        $this->getJson("/api/admin/quotes/{$quote->id}")
            ->assertOk()
            ->assertJsonPath('data.versions.0.decision', 'accepted')
            ->assertJsonPath('data.versions.0.decided_by', $quote->user_id);
    }

    public function test_a_non_admin_cannot_list_quotes(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/admin/quotes')->assertForbidden();
    }
}
