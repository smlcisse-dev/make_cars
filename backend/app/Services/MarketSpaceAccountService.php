<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Models\MarketSpaceAccount;
use App\Models\MarketSpaceImage;
use App\Models\ProfessionalRegistration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Même structure que GarageService, adaptée à MarketSpaceAccount (module
 * Profil Market Space, ajout v0.10).
 */
class MarketSpaceAccountService
{
    /**
     * Pré-remplit le profil Market Space à partir du dossier KYC validé
     * (nom, adresse), pendant public du compte pour le catalogue de pièces ;
     * le compte complète ensuite géoloc/horaires/photos depuis son dashboard.
     */
    public function createFromRegistration(ProfessionalRegistration $registration): MarketSpaceAccount
    {
        return $registration->user->marketSpaceAccount()->create([
            'name' => $registration->structure_name,
            'address' => $registration->address,
        ]);
    }

    /**
     * @param  array{name?: string, description?: ?string, address?: string, city?: ?string, region?: ?string, latitude?: ?float, longitude?: ?float, phone?: ?string}  $data
     */
    public function update(MarketSpaceAccount $account, array $data): MarketSpaceAccount
    {
        $account->update($data);

        return $account;
    }

    /**
     * Remplace intégralement les horaires du compte Market Space.
     *
     * @param  array<int, array{day_of_week: int, is_closed: bool, opens_at: ?string, closes_at: ?string}>  $hours
     */
    public function setOpeningHours(MarketSpaceAccount $account, array $hours): MarketSpaceAccount
    {
        foreach ($hours as $hour) {
            $account->openingHours()->updateOrCreate(
                ['day_of_week' => DayOfWeek::from($hour['day_of_week'])],
                [
                    'is_closed' => $hour['is_closed'],
                    'opens_at' => $hour['is_closed'] ? null : $hour['opens_at'],
                    'closes_at' => $hour['is_closed'] ? null : $hour['closes_at'],
                ],
            );
        }

        return $account->load('openingHours');
    }

    private function disk(): string
    {
        return config('filesystems.public_media_disk', 'public');
    }

    public function addImage(MarketSpaceAccount $account, UploadedFile $file): MarketSpaceImage
    {
        $disk = $this->disk();
        $path = $file->store('market-space/'.$account->id, $disk);
        $position = $account->images()->max('position') + 1;

        return $account->images()->create([
            'disk' => $disk,
            'path' => $path,
            'position' => $position,
        ]);
    }

    public function removeImage(MarketSpaceImage $image): void
    {
        Storage::disk($image->disk)->delete($image->path);
        $image->delete();
    }
}
