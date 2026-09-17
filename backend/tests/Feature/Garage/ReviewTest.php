<?php

namespace Tests\Feature\Garage;

use App\Models\Garage;
use App\Models\ProfessionalRegistration;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Le garage consulte ses avis reçus en lecture seule : aucune route de
 * modification/suppression ne lui est ouverte (CLAUDE.md §5, ajout v0.10).
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create();
    }

    public function test_a_garagiste_can_list_its_received_reviews(): void
    {
        $garage = $this->approvedGarage();
        Review::factory()->forGarage($garage)->create();
        Review::factory()->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/reviews')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_garagiste_sees_its_hidden_reviews_too(): void
    {
        $garage = $this->approvedGarage();
        Review::factory()->forGarage($garage)->hidden()->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'hidden');
    }

    public function test_garage_dashboard_has_no_review_moderation_route(): void
    {
        $garage = $this->approvedGarage();
        $review = Review::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $this->postJson("/api/garage/reviews/{$review->id}/moderate", ['reason' => 'Avis abusif'])
            ->assertStatus(404);
    }
}
