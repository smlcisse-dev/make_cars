<?php

namespace Tests\Feature;

use App\Models\Arrondissement;
use App\Models\Commune;
use App\Models\Department;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reference_data_matches_the_official_administrative_counts(): void
    {
        $this->assertSame(12, Department::count());
        $this->assertSame(77, Commune::count());
        $this->assertSame(546, Arrondissement::count());
    }

    public function test_the_seeder_is_idempotent(): void
    {
        (new LocationSeeder)->run();

        $this->assertSame(12, Department::count());
        $this->assertSame(77, Commune::count());
        $this->assertSame(546, Arrondissement::count());
    }

    public function test_the_cascade_endpoints_are_public_and_filter_each_level_by_its_parent(): void
    {
        $this->getJson('/api/locations/departments')->assertOk()->assertJsonCount(12, 'data');

        $littoral = Department::where('slug', 'littoral')->firstOrFail();
        $this->getJson("/api/locations/departments/{$littoral->id}/communes")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cotonou');

        $cotonou = $littoral->communes()->firstOrFail();
        $this->getJson("/api/locations/communes/{$cotonou->id}/arrondissements")
            ->assertOk()
            ->assertJsonCount(13, 'data');
    }

    public function test_a_commune_must_belong_to_the_chosen_department(): void
    {
        $garage = Garage::factory()->create();
        Sanctum::actingAs($garage->user);
        $borgou = Department::where('slug', 'borgou')->firstOrFail();
        $cotonou = Commune::where('slug', 'cotonou')->firstOrFail();

        $this->putJson('/api/garage/profile', $this->profilePayload([
            'department_id' => $borgou->id,
            'commune_id' => $cotonou->id,
            'arrondissement_id' => $cotonou->arrondissements()->firstOrFail()->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('commune_id');
    }

    public function test_an_arrondissement_must_belong_to_the_chosen_commune(): void
    {
        $garage = Garage::factory()->create();
        Sanctum::actingAs($garage->user);
        $cotonou = Commune::where('slug', 'cotonou')->firstOrFail();
        $foreign = Arrondissement::where('commune_id', '!=', $cotonou->id)->firstOrFail();

        $this->putJson('/api/garage/profile', $this->profilePayload([
            'arrondissement_id' => $foreign->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('arrondissement_id');
    }

    public function test_every_location_level_and_the_neighborhood_are_required(): void
    {
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $this->putJson('/api/market-space/profile', ['name' => 'Boutique', 'address' => 'Quelque part'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id', 'commune_id', 'arrondissement_id', 'neighborhood']);
    }

    /**
     * Payload valide (Cotonou, 1er arrondissement), écrasable niveau par niveau.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profilePayload(array $overrides = []): array
    {
        $cotonou = Commune::where('slug', 'cotonou')->firstOrFail();

        return array_merge([
            'name' => 'Garage Test',
            'address' => 'Quelque part',
            'phone' => '+2290197000000',
            'latitude' => 6.37,
            'longitude' => 2.39,
            'department_id' => $cotonou->department_id,
            'commune_id' => $cotonou->id,
            'arrondissement_id' => $cotonou->arrondissements()->firstOrFail()->id,
            'neighborhood' => 'Akpakpa',
        ], $overrides);
    }
}
