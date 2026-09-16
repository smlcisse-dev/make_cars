<?php

namespace Tests\Feature\Mobile;

use App\Models\Garage;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageListingTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGarage(): Garage
    {
        $registration = ProfessionalRegistration::factory()->approved()->create();

        return Garage::factory()->for($registration->user)->create();
    }

    public function test_the_mobile_app_only_lists_garages_with_an_approved_account(): void
    {
        $this->approvedGarage();
        Garage::factory()->for(User::factory()->garagiste())->create();

        $response = $this->getJson('/api/mobile/garages');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_unapproved_garage_profile_is_not_publicly_viewable(): void
    {
        $garage = Garage::factory()->for(User::factory()->garagiste())->create();

        $this->getJson("/api/mobile/garages/{$garage->id}")->assertNotFound();
    }

    public function test_an_approved_garage_profile_is_publicly_viewable(): void
    {
        $garage = $this->approvedGarage();

        $response = $this->getJson("/api/mobile/garages/{$garage->id}");

        $response->assertOk()->assertJsonPath('data.id', $garage->id);
    }

    public function test_only_approved_mini_boutique_products_appear_on_the_garage_profile(): void
    {
        $garage = $this->approvedGarage();
        $approved = Product::factory()->forGarage($garage)->approved()->create();
        Product::factory()->forGarage($garage)->create();

        $response = $this->getJson("/api/mobile/garages/{$garage->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.id', $approved->id);
    }
}
