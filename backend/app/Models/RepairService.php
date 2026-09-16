<?php

namespace App\Models;

use App\Enums\RepairServiceStatus;
use App\Enums\ServiceCategory;
use Database\Factories\RepairServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Service de réparation d'un garage, prix fixe unique (un garagiste qui veut
 * différencier par type de véhicule crée plusieurs services distincts —
 * CLAUDE.md §5, ajout v0.6). Contrairement au Product, jamais polymorphe :
 * un service appartient toujours à un Garage (le Market Space ne fait pas
 * de réparation).
 */
#[Fillable(['name', 'description', 'category', 'price', 'duration_minutes', 'image_disk', 'image_path', 'is_active', 'status', 'rejection_reason', 'reviewed_by', 'reviewed_at'])]
class RepairService extends Model
{
    /** @use HasFactory<RepairServiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
            'status' => RepairServiceStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Garage, $this>
     */
    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk($this->image_disk)->url($this->image_path) : null;
    }

    /**
     * Visible côté app mobile seulement si le service est validé, activé par
     * le garagiste (disponibilité, distincte de la validation admin) ET que
     * le compte garage est validé/non suspendu (CLAUDE.md §5, ajout v0.6).
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status === RepairServiceStatus::Approved
            && $this->is_active
            && $this->garage->isPubliclyVisible();
    }
}
