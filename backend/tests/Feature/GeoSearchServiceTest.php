<?php

namespace Tests\Feature;

use App\Enums\ServiceCategory;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\RepairService;
use App\Models\Review;
use App\Models\User;
use App\Services\GeoSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recherche de garages/Market Space indépendante de tout fournisseur de
 * cartographie externe — uniquement la formule de Haversine sur les
 * coordonnées déjà stockées pour la proximité (CLAUDE.md §5, ajout v0.13),
 * complétée par un filtre nom/service, un choix de tri (ajout v0.14) et un
 * filtre par nom de produit (ajout v0.15).
 */
class GeoSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(array $overrides = []): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create($overrides);
    }

    private function suspendedGarage(array $overrides = []): Garage
    {
        $registration = ProfessionalRegistration::factory()->suspended()->create();

        return Garage::factory()->for($registration->user)->create($overrides);
    }

    private function approvedMarketSpaceAccount(array $overrides = []): MarketSpaceAccount
    {
        $registration = ProfessionalRegistration::factory()->marketSpace()->approved()->create();

        return MarketSpaceAccount::factory()->for($registration->user)->create($overrides);
    }

    public function test_distance_between_two_points_one_degree_of_latitude_apart_is_about_111_km(): void
    {
        $distance = app(GeoSearchService::class)->distanceKm(0, 0, 1, 0);

        $this->assertEqualsWithDelta(111.19, $distance, 0.5);
    }

    public function test_distance_between_identical_points_is_zero(): void
    {
        $distance = app(GeoSearchService::class)->distanceKm(6.37, 2.39, 6.37, 2.39);

        $this->assertEqualsWithDelta(0.0, $distance, 0.001);
    }

    public function test_search_returns_results_sorted_by_proximity(): void
    {
        $far = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.5]); // ~55 km
        $near = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01]); // ~1.1 km
        $middle = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.1]); // ~11 km

        $results = app(GeoSearchService::class)->search(0, 0, radiusKm: 100, type: 'both', page: 1);

        $this->assertSame([$near->id, $middle->id, $far->id], $results->pluck('id')->all());
    }

    public function test_search_excludes_results_beyond_the_requested_radius(): void
    {
        $near = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01]); // ~1.1 km
        $this->approvedGarage(['latitude' => 0, 'longitude' => 1]); // ~111 km

        $results = app(GeoSearchService::class)->search(0, 0, radiusKm: 10, type: 'both', page: 1);

        $this->assertSame([$near->id], $results->pluck('id')->all());
    }

    public function test_search_caps_the_radius_at_the_configured_maximum(): void
    {
        config(['geo.max_search_radius_km' => 20]);
        $withinCap = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.1]); // ~11 km
        $beyondCap = $this->approvedGarage(['latitude' => 0, 'longitude' => 1]); // ~111 km

        $results = app(GeoSearchService::class)->search(0, 0, radiusKm: 500, type: 'both', page: 1);

        $this->assertSame([$withinCap->id], $results->pluck('id')->all());
        $this->assertFalse($results->pluck('id')->contains($beyondCap->id));
    }

    public function test_search_uses_the_default_radius_when_none_is_given(): void
    {
        config(['geo.default_search_radius_km' => 5]);
        $withinDefault = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01]); // ~1.1 km
        $beyondDefault = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.5]); // ~55 km

        $results = app(GeoSearchService::class)->search(0, 0, radiusKm: null, type: 'both', page: 1);

        $this->assertSame([$withinDefault->id], $results->pluck('id')->all());
        $this->assertFalse($results->pluck('id')->contains($beyondDefault->id));
    }

    public function test_search_can_be_restricted_to_a_single_type(): void
    {
        $garage = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01]);
        $account = $this->approvedMarketSpaceAccount(['latitude' => 0, 'longitude' => 0.01]);

        $garagesOnly = app(GeoSearchService::class)->search(0, 0, radiusKm: 50, type: 'garage', page: 1);
        $marketSpacesOnly = app(GeoSearchService::class)->search(0, 0, radiusKm: 50, type: 'market_space', page: 1);
        $both = app(GeoSearchService::class)->search(0, 0, radiusKm: 50, type: 'both', page: 1);

        $this->assertSame(['garage'], $garagesOnly->pluck('type')->unique()->all());
        $this->assertSame([$garage->id], $garagesOnly->pluck('id')->all());
        $this->assertSame(['market_space'], $marketSpacesOnly->pluck('type')->unique()->all());
        $this->assertSame([$account->id], $marketSpacesOnly->pluck('id')->all());
        $this->assertCount(2, $both);
    }

    public function test_search_excludes_unapproved_and_suspended_professionals(): void
    {
        $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01]);
        Garage::factory()->for(User::factory()->garagiste())->create(['latitude' => 0, 'longitude' => 0.01]);
        $this->suspendedGarage(['latitude' => 0, 'longitude' => 0.01]);

        $results = app(GeoSearchService::class)->search(0, 0, radiusKm: 50, type: 'garage', page: 1);

        $this->assertCount(1, $results);
    }

    public function test_search_excludes_professionals_without_coordinates(): void
    {
        $this->approvedGarage(['latitude' => null, 'longitude' => null]);

        $results = app(GeoSearchService::class)->search(0, 0, radiusKm: 50, type: 'garage', page: 1);

        $this->assertCount(0, $results);
    }

    public function test_search_result_exposes_the_expected_fields(): void
    {
        $garage = $this->approvedGarage(['latitude' => 0, 'longitude' => 0.01, 'name' => 'Garage Test']);

        $result = app(GeoSearchService::class)->search(0, 0, radiusKm: 50, type: 'garage', page: 1)->first();

        $this->assertSame('garage', $result->type);
        $this->assertSame('Garage Test', $result->name);
        $this->assertSame($garage->address, $result->address);
        $this->assertNull($result->photoUrl);
        $this->assertGreaterThan(0, $result->distanceKm);
        $this->assertNull($result->averageRating);
        $this->assertSame(0, $result->reviewsCount);
    }

    public function test_search_without_a_position_returns_all_matches_with_a_null_distance(): void
    {
        $this->approvedGarage(['latitude' => null, 'longitude' => null]);

        $results = app(GeoSearchService::class)->search(null, null, radiusKm: null, type: 'both', page: 1);

        $this->assertCount(1, $results);
        $this->assertNull($results->first()->distanceKm);
    }

    public function test_search_filters_by_service_category_and_excludes_market_spaces(): void
    {
        $matching = $this->approvedGarage();
        RepairService::factory()->forGarage($matching)->approved()->create(['category' => ServiceCategory::Pneumatiques]);

        $nonMatching = $this->approvedGarage();
        RepairService::factory()->forGarage($nonMatching)->approved()->create(['category' => ServiceCategory::Carrosserie]);

        $this->approvedMarketSpaceAccount();

        $results = app(GeoSearchService::class)->search(
            null, null, radiusKm: null, type: 'both', page: 1,
            serviceCategory: ServiceCategory::Pneumatiques,
        );

        $this->assertSame([$matching->id], $results->pluck('id')->all());
        $this->assertSame(['garage'], $results->pluck('type')->unique()->all());
    }

    public function test_search_sorts_by_rating_when_requested(): void
    {
        $worst = $this->approvedGarage(['name' => 'Z Garage']);
        $this->reviewFor($worst, 2);

        $best = $this->approvedGarage(['name' => 'A Garage']);
        $this->reviewFor($best, 5);

        $unrated = $this->approvedGarage(['name' => 'M Garage']);

        $results = app(GeoSearchService::class)->search(null, null, radiusKm: null, type: 'both', page: 1, sort: 'rating');

        $this->assertSame([$best->id, $worst->id, $unrated->id], $results->pluck('id')->all());
    }

    public function test_search_filters_by_product_name_across_garages_and_market_spaces(): void
    {
        $garage = $this->approvedGarage();
        $product = Product::factory()->forGarage($garage)->approved()->create(['name' => 'Huile moteur 5W30', 'price' => 12000]);

        $marketSpace = $this->approvedMarketSpaceAccount();
        Product::factory()->forMarketSpace($marketSpace)->approved()->create(['name' => 'Huile de boîte']);

        $noMatch = $this->approvedGarage();
        Product::factory()->forGarage($noMatch)->approved()->create(['name' => 'Pneu 4x4']);

        $results = app(GeoSearchService::class)->search(null, null, radiusKm: null, type: 'both', page: 1, productName: 'huile');

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains(fn ($result) => $result->type === 'garage' && $result->id === $garage->id));
        $this->assertTrue($results->contains(fn ($result) => $result->type === 'market_space' && $result->id === $marketSpace->id));

        $garageResult = $results->first(fn ($result) => $result->type === 'garage' && $result->id === $garage->id);
        $this->assertCount(1, $garageResult->matchedProducts);
        $this->assertSame($product->id, $garageResult->matchedProducts[0]->id);
        $this->assertSame('Huile moteur 5W30', $garageResult->matchedProducts[0]->name);
        $this->assertSame(12000.0, $garageResult->matchedProducts[0]->price);
    }

    public function test_search_excludes_vendors_without_an_approved_matching_product(): void
    {
        $garage = $this->approvedGarage();
        Product::factory()->forGarage($garage)->create(['name' => 'Huile moteur']); // pending

        $results = app(GeoSearchService::class)->search(null, null, radiusKm: null, type: 'both', page: 1, productName: 'huile');

        $this->assertCount(0, $results);
    }

    private function reviewFor(Garage $garage, int $rating): void
    {
        Review::factory()->forGarage($garage)->create(['rating' => $rating]);
    }
}
