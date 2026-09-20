<?php

namespace Tests\Feature\MarketSpace;

use App\Models\MarketSpaceAccount;
use App\Models\MarketSpaceImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_market_space_account_can_upload_photos_of_its_shop(): void
    {
        Storage::fake('public');
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $response = $this->postJson('/api/market-space/profile/images', [
            'images' => [
                UploadedFile::fake()->image('devanture.jpg'),
                UploadedFile::fake()->image('rayonnage.jpg'),
            ],
        ]);

        $response->assertCreated()->assertJsonCount(2, 'data');
        $this->assertDatabaseCount('market_space_images', 2);
    }

    public function test_uploading_a_non_image_file_is_rejected(): void
    {
        Storage::fake('public');
        $account = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($account->user);

        $response = $this->postJson('/api/market-space/profile/images', [
            'images' => [UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('images.0');
    }

    public function test_a_market_space_account_can_delete_its_own_image(): void
    {
        Storage::fake('public');
        $account = MarketSpaceAccount::factory()->create();
        MarketSpaceImage::factory()->for($account)->create();
        $image = MarketSpaceImage::factory()->for($account)->create();
        Sanctum::actingAs($account->user);

        $response = $this->deleteJson("/api/market-space/profile/images/{$image->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('market_space_images', ['id' => $image->id]);
    }

    public function test_the_last_remaining_image_cannot_be_deleted(): void
    {
        Storage::fake('public');
        $account = MarketSpaceAccount::factory()->create();
        $image = MarketSpaceImage::factory()->for($account)->create();
        Sanctum::actingAs($account->user);

        $response = $this->deleteJson("/api/market-space/profile/images/{$image->id}");

        $response->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertDatabaseHas('market_space_images', ['id' => $image->id]);
    }

    public function test_a_market_space_account_cannot_delete_another_accounts_image(): void
    {
        Storage::fake('public');
        $image = MarketSpaceImage::factory()->create();
        $otherAccount = MarketSpaceAccount::factory()->create();
        Sanctum::actingAs($otherAccount->user);

        $response = $this->deleteJson("/api/market-space/profile/images/{$image->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('market_space_images', ['id' => $image->id]);
    }
}
