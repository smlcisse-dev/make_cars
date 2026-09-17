<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\GarageOpeningHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Statut "ouvert maintenant" calculé à partir des horaires déjà enregistrés,
 * comparés à l'heure actuelle dans le fuseau métier (CLAUDE.md §5, ajout
 * v0.13). 2026-09-17 est un jeudi (ISO day 4), fixé pour des tests
 * déterministes.
 */
class HasOpeningHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_is_open_now_is_true_within_business_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 17, 10, 0, 0, config('geo.business_timezone')));
        $garage = Garage::factory()->create();
        GarageOpeningHour::factory()->for($garage)->create(['day_of_week' => 4, 'opens_at' => '08:00', 'closes_at' => '18:00']);

        $this->assertTrue($garage->load('openingHours')->isOpenNow());
    }

    public function test_is_open_now_is_false_outside_business_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 17, 20, 0, 0, config('geo.business_timezone')));
        $garage = Garage::factory()->create();
        GarageOpeningHour::factory()->for($garage)->create(['day_of_week' => 4, 'opens_at' => '08:00', 'closes_at' => '18:00']);

        $this->assertFalse($garage->load('openingHours')->isOpenNow());
    }

    public function test_is_open_now_is_false_when_the_day_is_marked_closed(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 17, 10, 0, 0, config('geo.business_timezone')));
        $garage = Garage::factory()->create();
        GarageOpeningHour::factory()->closed()->for($garage)->create(['day_of_week' => 4]);

        $this->assertFalse($garage->load('openingHours')->isOpenNow());
    }

    public function test_is_open_now_is_null_when_no_hours_are_configured_for_today(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 17, 10, 0, 0, config('geo.business_timezone')));
        $garage = Garage::factory()->create();
        GarageOpeningHour::factory()->for($garage)->create(['day_of_week' => 3, 'opens_at' => '08:00', 'closes_at' => '18:00']);

        $this->assertNull($garage->load('openingHours')->isOpenNow());
    }

    public function test_is_open_now_tolerates_a_time_with_seconds_from_the_database_driver(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 17, 10, 0, 0, config('geo.business_timezone')));
        $garage = Garage::factory()->create();
        GarageOpeningHour::factory()->for($garage)->create(['day_of_week' => 4, 'opens_at' => '08:00:00', 'closes_at' => '18:00:00']);

        $this->assertTrue($garage->load('openingHours')->isOpenNow());
    }
}
