<?php

namespace Tests\Feature\Garage;

use App\Models\Garage;
use App\Models\GarageImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GarageImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_garagiste_can_upload_photos_of_its_garage(): void
    {
        Storage::fake('public');
        $garage = Garage::factory()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson('/api/garage/profile/images', [
            'images' => [
                UploadedFile::fake()->image('devanture.jpg'),
                UploadedFile::fake()->image('atelier.jpg'),
            ],
        ]);

        $response->assertCreated()->assertJsonCount(2, 'data');
        $this->assertDatabaseCount('garage_images', 2);
    }

    public function test_uploading_a_non_image_file_is_rejected(): void
    {
        Storage::fake('public');
        $garage = Garage::factory()->create();
        Sanctum::actingAs($garage->user);

        $response = $this->postJson('/api/garage/profile/images', [
            'images' => [UploadedFile::fake()->create('devis.pdf', 100, 'application/pdf')],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('images.0');
    }

    public function test_a_garagiste_can_delete_its_own_image(): void
    {
        Storage::fake('public');
        $garage = Garage::factory()->create();
        GarageImage::factory()->for($garage)->create();
        $image = GarageImage::factory()->for($garage)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->deleteJson("/api/garage/profile/images/{$image->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('garage_images', ['id' => $image->id]);
    }

    public function test_the_last_remaining_image_cannot_be_deleted(): void
    {
        Storage::fake('public');
        $garage = Garage::factory()->create();
        $image = GarageImage::factory()->for($garage)->create();
        Sanctum::actingAs($garage->user);

        $response = $this->deleteJson("/api/garage/profile/images/{$image->id}");

        $response->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertDatabaseHas('garage_images', ['id' => $image->id]);
    }

    public function test_a_garagiste_cannot_delete_another_garages_image(): void
    {
        Storage::fake('public');
        $image = GarageImage::factory()->create();
        $otherGarage = Garage::factory()->create();
        Sanctum::actingAs($otherGarage->user);

        $response = $this->deleteJson("/api/garage/profile/images/{$image->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('garage_images', ['id' => $image->id]);
    }
}
