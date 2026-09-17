<?php

namespace Tests\Feature\Mobile;

use App\Enums\ServiceCategory;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;
use App\Models\RepairService;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recherche publique de garages/Market Space (pas de session requise — un
 * automobiliste en panne peut ne pas être connecté), CLAUDE.md §5, ajouts
 * v0.13 (proximité) et v0.14 (nom, service, tri).
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

    public function test_position_is_optional_when_searching_by_name_or_service(): void
    {
        $this->approvedGarage(['latitude' => null, 'longitude' => null, 'name' => 'Garage Sans Position']);

        $this->getJson('/api/mobile/search/nearby')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.distance_km', null);
    }

    public function test_radius_km_requires_a_position(): void
    {
        $this->getJson('/api/mobile/search/nearby?radius_km=10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_sort_by_distance_requires_a_position(): void
    {
        $this->getJson('/api/mobile/search/nearby?sort=distance')
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

    public function test_name_filter_matches_partially_and_ignores_case(): void
    {
        $this->approvedGarage(['name' => 'Garage Étoile du Bénin']);
        $this->approvedGarage(['name' => 'Garage Confiance']);
        $this->approvedMarketSpaceAccount(['name' => 'Pièces Étoile']);

        $this->getJson('/api/mobile/search/nearby?name=étoile')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/mobile/search/nearby?name=GARAGE+CONF')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Garage Confiance');
    }

    public function test_name_filter_combines_with_the_geographic_filter(): void
    {
        $this->approvedGarage(['name' => 'Garage Rapide', 'latitude' => 0, 'longitude' => 0.01]);
        $this->approvedGarage(['name' => 'Garage Rapide', 'latitude' => 0, 'longitude' => 1]);

        $this->getJson('/api/mobile/search/nearby?latitude=0&longitude=0&radius_km=10&name=Rapide')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_service_category_filter_restricts_to_garages_offering_that_category(): void
    {
        $withCategory = $this->approvedGarage();
        RepairService::factory()->forGarage($withCategory)->approved()->create(['category' => ServiceCategory::Pneumatiques]);

        $otherCategory = $this->approvedGarage();
        RepairService::factory()->forGarage($otherCategory)->approved()->create(['category' => ServiceCategory::Carrosserie]);

        $this->approvedMarketSpaceAccount();

        $this->getJson('/api/mobile/search/nearby?service_category=pneumatiques')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withCategory->id)
            ->assertJsonPath('data.0.type', 'garage');
    }

    public function test_service_category_filter_ignores_services_not_approved_or_inactive(): void
    {
        $garage = $this->approvedGarage();
        RepairService::factory()->forGarage($garage)->create(['category' => ServiceCategory::Pneumatiques]); // pending
        RepairService::factory()->forGarage($garage)->approved()->inactive()->create(['category' => ServiceCategory::Pneumatiques]);

        $this->getJson('/api/mobile/search/nearby?service_category=pneumatiques')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_service_id_filter_restricts_to_the_garage_offering_that_precise_service(): void
    {
        $garage = $this->approvedGarage();
        $service = RepairService::factory()->forGarage($garage)->approved()->create();
        $this->approvedGarage();

        $this->getJson('/api/mobile/search/nearby?service_id='.$service->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $garage->id);
    }

    public function test_service_filter_excludes_market_spaces_even_when_type_is_both(): void
    {
        $garage = $this->approvedGarage();
        RepairService::factory()->forGarage($garage)->approved()->create(['category' => ServiceCategory::Pneumatiques]);
        $this->approvedMarketSpaceAccount();

        $this->getJson('/api/mobile/search/nearby?service_category=pneumatiques&type=both')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'garage');
    }

    public function test_service_category_must_be_a_known_value(): void
    {
        $this->getJson('/api/mobile/search/nearby?service_category=inconnu')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_category');
    }

    public function test_service_id_must_reference_an_existing_service(): void
    {
        $this->getJson('/api/mobile/search/nearby?service_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_id');
    }

    public function test_sort_by_rating_orders_results_from_best_to_worst_with_unrated_last(): void
    {
        $best = $this->approvedGarage(['name' => 'Garage A']);
        Review::factory()->forGarage($best)->create(['rating' => 5]);

        $worst = $this->approvedGarage(['name' => 'Garage B']);
        Review::factory()->forGarage($worst)->create(['rating' => 2]);

        $unrated = $this->approvedGarage(['name' => 'Garage C']);

        $response = $this->getJson('/api/mobile/search/nearby?sort=rating');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$best->id, $worst->id, $unrated->id], $ids);
    }

    public function test_sort_defaults_to_rating_when_no_position_is_given(): void
    {
        $best = $this->approvedGarage(['name' => 'Garage A']);
        Review::factory()->forGarage($best)->create(['rating' => 5]);

        $worst = $this->approvedGarage(['name' => 'Garage B']);
        Review::factory()->forGarage($worst)->create(['rating' => 1]);

        $response = $this->getJson('/api/mobile/search/nearby');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$best->id, $worst->id], $ids);
    }

    public function test_sort_can_be_switched_to_rating_even_when_a_position_is_given(): void
    {
        $far = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.1, 'name' => 'Garage Loin']); // ~11 km, within the default 15 km radius
        Review::factory()->forGarage($far)->create(['rating' => 5]);

        $near = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01, 'name' => 'Garage Proche']);
        Review::factory()->forGarage($near)->create(['rating' => 2]);

        $response = $this->getJson('/api/mobile/search/nearby?latitude=0&longitude=0&sort=rating');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$far->id, $near->id], $ids);
    }
}
