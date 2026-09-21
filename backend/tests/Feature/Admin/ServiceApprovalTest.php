<?php

namespace Tests\Feature\Admin;

use App\Enums\RepairServiceStatus;
use App\Models\RepairService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_pending_services(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        RepairService::factory()->count(2)->create();
        RepairService::factory()->approved()->create();

        $response = $this->getJson('/api/admin/services?status=pending');

        $response->assertOk()->assertJsonCount(2, 'data');
        // Le garage doit être exposé (pas juste garage_id) pour que le
        // dashboard admin affiche un nom sans requête supplémentaire.
        $response->assertJsonStructure(['data' => ['*' => ['garage' => ['id', 'name']]]]);
    }

    public function test_a_non_admin_cannot_list_services(): void
    {
        Sanctum::actingAs(User::factory()->garagiste()->create());

        $this->getJson('/api/admin/services')->assertForbidden();
    }

    public function test_an_admin_can_approve_a_pending_service(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $service = RepairService::factory()->create();

        $response = $this->postJson("/api/admin/services/{$service->id}/approve");

        $response->assertOk()->assertJsonPath('data.status', RepairServiceStatus::Approved->value);
        $response->assertJsonPath('data.garage.id', $service->garage_id);
        $this->assertDatabaseHas('repair_services', [
            'id' => $service->id,
            'status' => RepairServiceStatus::Approved->value,
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_an_admin_can_reject_a_pending_service_with_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $service = RepairService::factory()->create();

        $response = $this->postJson("/api/admin/services/{$service->id}/reject", [
            'reason' => 'Prix incohérent avec la prestation décrite.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', RepairServiceStatus::Rejected->value);
        $response->assertJsonPath('data.garage.id', $service->garage_id);
        $this->assertDatabaseHas('repair_services', [
            'id' => $service->id,
            'status' => RepairServiceStatus::Rejected->value,
            'rejection_reason' => 'Prix incohérent avec la prestation décrite.',
        ]);
    }

    public function test_rejecting_a_service_requires_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $service = RepairService::factory()->create();

        $this->postJson("/api/admin/services/{$service->id}/reject")->assertUnprocessable();
    }

    public function test_an_already_approved_service_cannot_be_reviewed_again(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $service = RepairService::factory()->approved()->create();

        $this->postJson("/api/admin/services/{$service->id}/approve")->assertForbidden();
    }
}
