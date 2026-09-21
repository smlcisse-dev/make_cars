<?php

namespace Tests\Feature\Garage;

use App\Enums\RepairServiceStatus;
use App\Models\Garage;
use App\Models\RepairService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_garagiste_can_list_its_own_services(): void
    {
        $garage = Garage::factory()->complete()->create();
        RepairService::factory()->forGarage($garage)->count(2)->create();
        RepairService::factory()->count(1)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->getJson('/api/garage/services');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_the_services_list_honours_per_page_capped_at_100(): void
    {
        $garage = Garage::factory()->complete()->create();
        RepairService::factory()->forGarage($garage)->count(101)->create();
        Sanctum::actingAs($garage->user);

        $this->getJson('/api/garage/services')->assertOk()->assertJsonCount(15, 'data');
        $this->getJson('/api/garage/services?per_page=100')->assertOk()->assertJsonCount(100, 'data');
        $this->getJson('/api/garage/services?per_page=9999')->assertOk()->assertJsonCount(100, 'data');
    }

    public function test_a_garagiste_can_add_a_service_pending_validation(): void
    {
        $garage = Garage::factory()->complete()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson('/api/garage/services', [
            'name' => 'Vidange citadine',
            'description' => 'Vidange complète huile + filtre pour citadine.',
            'category' => 'entretien_courant',
            'price' => 15000,
            'duration_minutes' => 45,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', RepairServiceStatus::Pending->value)
            ->assertJsonPath('data.category', 'entretien_courant')
            ->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('repair_services', [
            'garage_id' => $garage->id,
            'name' => 'Vidange citadine',
            'status' => RepairServiceStatus::Pending->value,
        ]);
    }

    public function test_a_garagiste_can_add_a_service_with_an_illustrative_image(): void
    {
        Storage::fake('public');
        $garage = Garage::factory()->complete()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->post('/api/garage/services', [
            'name' => 'Vidange 4x4',
            'description' => 'Vidange complète huile + filtre pour 4x4.',
            'category' => 'entretien_courant',
            'price' => 25000,
            'duration_minutes' => 60,
            'image' => UploadedFile::fake()->image('vidange.jpg'),
        ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('data.image_url'));
    }

    public function test_creating_a_service_rejects_a_category_outside_the_fixed_list(): void
    {
        $garage = Garage::factory()->complete()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson('/api/garage/services', [
            'name' => 'Service exotique',
            'description' => 'Un service avec une catégorie inventée.',
            'category' => 'peinture_artistique',
            'price' => 10000,
            'duration_minutes' => 30,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('category');
    }

    public function test_updating_a_service_resets_its_validation_status(): void
    {
        $garage = Garage::factory()->complete()->create();
        $service = RepairService::factory()->forGarage($garage)->approved()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->putJson("/api/garage/services/{$service->id}", [
            'name' => 'Vidange citadine (huile synthétique)',
            'description' => $service->description,
            'category' => $service->category->value,
            'price' => 18000,
            'duration_minutes' => 45,
        ]);

        $response->assertOk()->assertJsonPath('data.status', RepairServiceStatus::Pending->value);
        $this->assertDatabaseHas('repair_services', [
            'id' => $service->id,
            'status' => RepairServiceStatus::Pending->value,
            'reviewed_by' => null,
        ]);
    }

    public function test_updating_availability_does_not_reset_validation_status(): void
    {
        $garage = Garage::factory()->complete()->create();
        $service = RepairService::factory()->forGarage($garage)->approved()->create(['is_active' => true]);
        Sanctum::actingAs($garage->user);

        $response = $this->putJson("/api/garage/services/{$service->id}/availability", [
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', RepairServiceStatus::Approved->value);
        $this->assertDatabaseHas('repair_services', [
            'id' => $service->id,
            'is_active' => false,
            'status' => RepairServiceStatus::Approved->value,
        ]);
    }

    public function test_a_garagiste_can_delete_its_own_service(): void
    {
        $garage = Garage::factory()->complete()->create();
        $service = RepairService::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $this->deleteJson("/api/garage/services/{$service->id}")->assertOk();

        $this->assertDatabaseMissing('repair_services', ['id' => $service->id]);
    }

    public function test_a_garagiste_cannot_modify_another_garages_service(): void
    {
        $service = RepairService::factory()->create();
        $otherGarage = Garage::factory()->complete()->create();
        Sanctum::actingAs($otherGarage->user);

        $this->putJson("/api/garage/services/{$service->id}", [
            'name' => 'Piratage',
            'description' => 'Tentative sur un service qui ne lui appartient pas.',
            'category' => 'autre_divers',
            'price' => 1,
            'duration_minutes' => 5,
        ])->assertNotFound();
    }

    public function test_the_catalog_never_embeds_the_garage_in_its_responses(): void
    {
        $garage = Garage::factory()->complete()->create();
        $service = RepairService::factory()->forGarage($garage)->create();
        Sanctum::actingAs($garage->user);

        $listed = $this->getJson('/api/garage/services')->assertOk();
        $this->assertArrayNotHasKey('garage', $listed->json('data.0'));
        $listed->assertJsonPath('data.0.id', $service->id)->assertJsonPath('data.0.garage_id', $garage->id);

        $availability = $this->putJson("/api/garage/services/{$service->id}/availability", ['is_active' => false])->assertOk();
        $this->assertArrayNotHasKey('garage', $availability->json('data'));
    }

    public function test_a_garagiste_can_update_a_service_with_a_new_image_through_method_spoofing(): void
    {
        Storage::fake('public');
        $garage = Garage::factory()->complete()->create();
        $service = RepairService::factory()->forGarage($garage)->approved()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->post("/api/garage/services/{$service->id}", [
            '_method' => 'PUT',
            'name' => 'Vidange premium',
            'description' => 'Vidange complète avec huile synthétique.',
            'category' => 'entretien_courant',
            'price' => 30000,
            'duration_minutes' => 50,
            'image' => UploadedFile::fake()->image('nouvelle.png'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.id', $service->id)
            ->assertJsonPath('data.name', 'Vidange premium')
            ->assertJsonPath('data.status', RepairServiceStatus::Pending->value);
        $this->assertNotNull($response->json('data.image_url'));
        $this->assertArrayNotHasKey('garage', $response->json('data'));
        $this->assertNotNull($service->fresh()->image_path);
    }

    public function test_a_garagiste_cannot_toggle_the_availability_of_another_garages_service(): void
    {
        $service = RepairService::factory()->create(['is_active' => true]);
        Sanctum::actingAs(Garage::factory()->complete()->create()->user);

        $this->putJson("/api/garage/services/{$service->id}/availability", ['is_active' => false])->assertNotFound();

        $this->assertTrue($service->fresh()->is_active);
    }

    public function test_a_garagiste_cannot_delete_another_garages_service(): void
    {
        $service = RepairService::factory()->create();
        Sanctum::actingAs(Garage::factory()->complete()->create()->user);

        $this->deleteJson("/api/garage/services/{$service->id}")->assertNotFound();

        $this->assertDatabaseHas('repair_services', ['id' => $service->id]);
    }

    public function test_a_non_garagiste_cannot_access_the_garage_service_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/garage/services')->assertForbidden();
    }
}
