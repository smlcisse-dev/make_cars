<?php

namespace Tests\Feature\Mobile;

use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recherche géolocalisée publique (pas de session requise — un automobiliste
 * en panne peut ne pas être connecté), CLAUDE.md §5, ajout v0.13.
 */
class NearbySearchTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(array $overrides = []): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create($overrides);
    }

    private function approvedMarketSpaceAccount(array $overrides = []): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->marketSpace()->approved()->create();

        return MarketSpaceAccount::factory()->for($registration->user)->create($overrides);
    }

    public function test_nearby_search_requires_no_authentication(): void
    {
        $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01]);

        $this->getJson('/api/mobile/search/nearby?latitude=0&longitude=0')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_results_are_sorted_by_proximity_and_expose_the_expected_fields(): void
    {
        $far = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.1, 'name' => 'Garage Loin']);
        $near = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01, 'name' => 'Garage Proche']);

        $response = $this->getJson('/api/mobile/search/nearby?latitude=0&longitude=0&radius_km=50');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$near->id, $far->id], $ids);

        $response->assertJsonPath('data.0.type', 'garage')
            ->assertJsonPath('data.0.name', 'Garage Proche')
            ->assertJsonStructure(['data' => [['type', 'id', 'name', 'address', 'photo_url', 'distance_km', 'average_rating', 'reviews_count', 'is_open_now']]]);
    }

    public function test_radius_filter_excludes_distant_results(): void
    {
        $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01]);
        $this->approvedGarage(['latitude' => 0, 'longitude' => 1]);

        $this->getJson('/api/mobile/search/nearby?latitude=0&longitude=0&radius_km=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_type_filter_restricts_results_to_garages_or_market_spaces(): void
    {
        $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01]);
        $this->approvedMarketSpaceAccount(['latitude' => 0, 'longitude' => 0.01]);

        $this->getJson('/api/mobile/search/nearby?latitude=0&longitude=0&type=garage')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'garage');

        $this->getJson('/api/mobile/search/nearby?latitude=0&longitude=0&type=market_space')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'market_space');

        $this->getJson('/api/mobile/search/nearby?latitude=0&longitude=0')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_unapproved_garage_is_excluded_from_results(): void
    {
        Garage::factory()->create(['latitude' => 0, 'longitude' => 0.01]);

        $this->getJson('/api/mobile/search/nearby?latitude=0&longitude=0')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_latitude_and_longitude_are_required(): void
    {
        $this->getJson('/api/mobile/search/nearby')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_latitude_must_be_within_valid_bounds(): void
    {
        $this->getJson('/api/mobile/search/nearby?latitude=120&longitude=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');
    }

    public function test_type_must_be_a_known_value(): void
    {
        $this->getJson('/api/mobile/search/nearby?latitude=0&longitude=0&type=boutique')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }
}
