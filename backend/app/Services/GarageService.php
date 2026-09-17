<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Models\Garage;
use App\Models\GarageImage;
use App\Models\ProfessionalRegistration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GarageService
{
    /**
     * Pré-remplit le profil Garage à partir du dossier KYC validé (nom,
     * adresse) ; le garagiste complète ensuite géoloc/horaires/photos depuis
     * son dashboard.
     */
    public function createFromRegistration(ProfessionalRegistration $registration): Garage
    {
        return $registration->user->garage()->create([
            'name' => $registration->structure_name,
            'address' => $registration->address,
        ]);
    }

    /**
     * @param  array{name?: string, description?: ?string, address?: string, city?: ?string, region?: ?string, latitude?: ?float, longitude?: ?float, phone?: ?string}  $data
     */
    public function update(Garage $garage, array $data): Garage
    {
        $garage->update($data);

        return $garage;
    }

    /**
     * Remplace intégralement les horaires du garage.
     *
     * @param  array<int, array{day_of_week: int, is_closed: bool, opens_at: ?string, closes_at: ?string}>  $hours
     */
    public function setOpeningHours(Garage $garage, array $hours): Garage
    {
        foreach ($hours as $hour) {
            $garage->openingHours()->updateOrCreate(
                ['day_of_week' => DayOfWeek::from($hour['day_of_week'])],
                [
                    'is_closed' => $hour['is_closed'],
                    'opens_at' => $hour['is_closed'] ? null : $hour['opens_at'],
                    'closes_at' => $hour['is_closed'] ? null : $hour['closes_at'],
                ],
            );
        }

        return $garage->load('openingHours');
    }

    private function disk(): string
    {
        return config('filesystems.public_media_disk', 'public');
    }

    public function addImage(Garage $garage, UploadedFile $file): GarageImage
    {
        $disk = $this->disk();
        $path = $file->store('garages/'.$garage->id, $disk);
        $position = $garage->images()->max('position') + 1;

        return $garage->images()->create([
            'disk' => $disk,
            'path' => $path,
            'position' => $position,
        ]);
    }

    public function removeImage(GarageImage $image): void
    {
        Storage::disk($image->disk)->delete($image->path);
        $image->delete();
    }
}
