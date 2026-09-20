<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use App\Services\ProfessionalRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bare_garage_profile_lists_every_missing_element(): void
    {
        $garage = Garage::factory()->create(['phone' => null, 'latitude' => null, 'longitude' => null]);

        $this->assertFalse($garage->isProfileComplete());
        $this->assertEqualsCanonicalizing(
            ['phone', 'latitude', 'longitude', 'neighborhood', 'department_id', 'commune_id', 'arrondissement_id', 'opening_hours', 'images'],
            $garage->missingProfileFields(),
        );
    }

    public function test_a_blank_name_or_address_counts_as_missing(): void
    {
        $garage = Garage::factory()->complete()->create(['name' => '  ', 'address' => '']);

        $this->assertEqualsCanonicalizing(['name', 'address'], $garage->missingProfileFields());
    }

    public function test_fewer_than_seven_opening_days_is_incomplete(): void
    {
        $garage = Garage::factory()->complete()->create();
        $garage->openingHours()->first()->delete();

        $this->assertSame(['opening_hours'], $garage->missingProfileFields());
    }

    public function test_a_complete_garage_profile_has_no_missing_element(): void
    {
        $garage = Garage::factory()->complete()->create();

        $this->assertTrue($garage->isProfileComplete());
    }

    public function test_an_incomplete_garage_is_blocked_everywhere_except_the_profile_routes(): void
    {
        $garage = Garage::factory()->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/products')
            ->assertForbidden()
            ->assertJsonPath('code', 'profile_incomplete')
            ->assertJsonPath('missing_fields.0', 'neighborhood');
        $this->getJson('/api/garage/appointments')->assertForbidden();
        $this->getJson('/api/garage/profile')->assertOk()->assertJsonPath('meta.profile_status.is_complete', false);
        $this->postJson('/api/auth/logout')->assertOk();
    }

    public function test_a_complete_garage_can_use_its_space(): void
    {
        $garage = Garage::factory()->complete()->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/products')->assertOk();
        $this->getJson('/api/garage/profile')->assertOk()->assertJsonPath('meta.profile_status.is_complete', true);
    }

    public function test_an_incomplete_market_space_account_is_blocked_too(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $this->getJson('/api/market-space/products')->assertForbidden()->assertJsonPath('code', 'profile_incomplete');
        $this->getJson('/api/market-space/profile')->assertOk();
    }

    public function test_a_complete_market_space_account_can_use_its_space(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        Sanctum::actingAs($account->user);

        $this->getJson('/api/market-space/products')->assertOk();
    }

    public function test_the_session_exposes_the_profile_status(): void
    {
        $garage = Garage::factory()->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.profile_status.is_complete', false)
            ->assertJsonPath('data.profile_status.missing_fields.0', 'neighborhood');
    }

    public function test_the_profile_status_is_null_for_a_user_without_professional_profile(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.profile_status', null);
    }

    public function test_the_profile_update_requires_phone_and_position(): void
    {
        $garage = Garage::factory()->create();
        Sanctum::actingAs($garage->user);

        $this->putJson('/api/garage/profile', ['name' => 'Garage', 'address' => 'Adresse'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'latitude', 'longitude']);
    }

    public function test_the_phone_is_prefilled_from_the_registration_on_approval(): void
    {
        $registration = ProfessionalRegistration::factory()->create();
        $registration->user->update(['phone' => '+2290196000000']);

        app(ProfessionalRegistrationService::class)->approve($registration, User::factory()->admin()->create());

        $this->assertSame('+2290196000000', $registration->user->fresh()->garage->phone);
    }
}
