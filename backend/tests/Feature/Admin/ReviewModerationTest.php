<?php

namespace Tests\Feature\Admin;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Modération admin d'un avis abusif/diffamatoire : masquage logique tracé,
 * jamais une suppression SQL, motif obligatoire comme pour un rejet/une
 * suspension de compte (CLAUDE.md §5, ajout v0.10).
 */
class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_all_reviews(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Review::factory()->count(3)->create();

        $this->getJson('/api/admin/reviews')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_an_admin_can_view_a_review(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $review = Review::factory()->create();

        $this->getJson("/api/admin/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $review->id)
            ->assertJsonPath('data.reviewable.name', $review->reviewable->name);
    }

    public function test_an_admin_can_hide_a_review_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $review = Review::factory()->create();

        $response = $this->postJson("/api/admin/reviews/{$review->id}/moderate", [
            'reason' => 'Propos diffamatoires envers le garage.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'hidden');
        $response->assertJsonPath('data.client.id', $review->user_id);
        $response->assertJsonPath('data.reviewable.id', $review->reviewable_id);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => 'hidden',
            'moderation_reason' => 'Propos diffamatoires envers le garage.',
            'moderated_by' => $admin->id,
        ]);
    }

    public function test_hiding_a_review_requires_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $review = Review::factory()->create();

        $this->postJson("/api/admin/reviews/{$review->id}/moderate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_an_already_hidden_review_cannot_be_hidden_again(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $review = Review::factory()->hidden()->create();

        $this->postJson("/api/admin/reviews/{$review->id}/moderate", ['reason' => 'Motif quelconque.'])
            ->assertForbidden();
    }

    public function test_a_non_admin_cannot_moderate_a_review(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());
        $review = Review::factory()->create();

        $this->postJson("/api/admin/reviews/{$review->id}/moderate", ['reason' => 'Motif quelconque.'])
            ->assertForbidden();
    }

    public function test_moderating_a_review_never_deletes_it(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $review = Review::factory()->create();

        $this->postJson("/api/admin/reviews/{$review->id}/moderate", ['reason' => 'Motif quelconque.'])
            ->assertOk();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }
}
