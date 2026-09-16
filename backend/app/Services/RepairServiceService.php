<?php

namespace App\Services;

use App\Enums\RepairServiceStatus;
use App\Models\Garage;
use App\Models\RepairService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RepairServiceService
{
    private function disk(): string
    {
        return config('filesystems.public_media_disk', 'public');
    }

    /**
     * @param  array{name: string, description: string, category: string, price: float, duration_minutes: int}  $data
     */
    public function create(Garage $garage, array $data, ?UploadedFile $image): RepairService
    {
        $service = $garage->services()->create([
            ...$data,
            'is_active' => true,
            'status' => RepairServiceStatus::Pending,
        ]);

        if ($image) {
            $this->storeImage($service, $image);
        }

        return $service;
    }

    /**
     * Toute modification du contenu du service (hors disponibilité) repasse
     * son statut en attente de validation admin (CLAUDE.md §5, règle 5).
     *
     * @param  array{name: string, description: string, category: string, price: float, duration_minutes: int}  $data
     */
    public function update(RepairService $service, array $data, ?UploadedFile $image): RepairService
    {
        $service->update([
            ...$data,
            'status' => RepairServiceStatus::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        if ($image) {
            $this->storeImage($service, $image);
        }

        return $service;
    }

    private function storeImage(RepairService $service, UploadedFile $image): void
    {
        if ($service->image_path) {
            Storage::disk($service->image_disk)->delete($service->image_path);
        }

        $disk = $this->disk();
        $path = $image->store('repair-services/'.$service->garage_id, $disk);

        $service->update(['image_disk' => $disk, 'image_path' => $path]);
    }

    /**
     * Rend le service temporairement indisponible (ou le réactive) sans le
     * supprimer ni toucher à sa validation admin — interrupteur distinct du
     * statut pending/approved/rejected (CLAUDE.md §5, ajout v0.6).
     */
    public function updateAvailability(RepairService $service, bool $isActive): RepairService
    {
        $service->update(['is_active' => $isActive]);

        return $service;
    }

    public function delete(RepairService $service): void
    {
        if ($service->image_path) {
            Storage::disk($service->image_disk)->delete($service->image_path);
        }

        $service->delete();
    }

    public function approve(RepairService $service, User $admin): RepairService
    {
        $service->update([
            'status' => RepairServiceStatus::Approved,
            'rejection_reason' => null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $service;
    }

    public function reject(RepairService $service, User $admin, string $reason): RepairService
    {
        $service->update([
            'status' => RepairServiceStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $service;
    }
}
