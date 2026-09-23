<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Models\Garage;
use App\Models\GarageImage;
use App\Models\ProfessionalRegistration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
            'phone' => $registration->user->phone,
        ]);
    }

    /**
     * Profil Garage vide, créé dès la vérification de l'email (CLAUDE.md §5,
     * ajout v0.26) : seul le téléphone saisi à l'inscription est repris ; le
     * professionnel complète tout le reste depuis sa page profil avant de
     * soumettre son dossier.
     */
    public function createEmpty(User $user): Garage
    {
        return $user->garage()->create(['phone' => $user->phone]);
    }

    /**
     * @param  array{name?: string, description?: ?string, address?: string, department_id?: ?int, commune_id?: ?int, arrondissement_id?: ?int, neighborhood?: ?string, latitude?: ?float, longitude?: ?float, phone?: ?string}  $data
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
        if ($image->garage->images()->count() <= 1) {
            throw ValidationException::withMessages(['image' => 'Un profil doit garder au moins une photo : ajoutez-en une autre avant de supprimer celle-ci.']);
        }

        Storage::disk($image->disk)->delete($image->path);
        $image->delete();
    }
}
