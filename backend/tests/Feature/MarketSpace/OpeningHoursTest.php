<?php

namespace Tests\Feature\MarketSpace;

use App\Models\MarketSpaceAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpeningHoursTest extends TestCase
{
    use RefreshDatabase;

    private function fullWeekPayload(): array
    {
        $hours = [];
        for ($day = 1; $day <= 7; $day++) {
            $hours[] = [
                'day_of_week' => $day,
                'is_closed' => $day === 7,
                'opens_at' => $day === 7 ? null : '08:00',
                'closes_at' => $day === 7 ? null : '18:00',
            ];
        }

        return ['hours' => $hours];
    }

    public function test_a_market_space_account_can_set_its_weekly_opening_hours(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $response = $this->putJson('/api/market-space/profile/opening-hours', $this->fullWeekPayload());

        $response->assertOk()->assertJsonCount(7, 'data.opening_hours');
        $this->assertDatabaseHas('market_space_opening_hours', [
            'market_space_account_id' => $account->id,
            'day_of_week' => 7,
            'is_closed' => true,
        ]);
    }

    public function test_opening_hours_requires_exactly_seven_days(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $payload = $this->fullWeekPayload();
        array_pop($payload['hours']);

        $response = $this->putJson('/api/market-space/profile/opening-hours', $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors('hours');
    }

    public function test_an_open_day_requires_opening_and_closing_times(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $payload = $this->fullWeekPayload();
        $payload['hours'][0]['opens_at'] = null;

        $response = $this->putJson('/api/market-space/profile/opening-hours', $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors('hours.0.opens_at');
    }

    public function test_closing_time_must_be_after_opening_time(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $payload = $this->fullWeekPayload();
        $payload['hours'][0]['opens_at'] = '18:00';
        $payload['hours'][0]['closes_at'] = '08:00';

        $response = $this->putJson('/api/market-space/profile/opening-hours', $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors('hours.0.closes_at');
    }

    public function test_updating_opening_hours_twice_replaces_them_instead_of_duplicating(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $this->putJson('/api/market-space/profile/opening-hours', $this->fullWeekPayload())->assertOk();
        $this->putJson('/api/market-space/profile/opening-hours', $this->fullWeekPayload())->assertOk();

        $this->assertDatabaseCount('market_space_opening_hours', 7);
    }
}
