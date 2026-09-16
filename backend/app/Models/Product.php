<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un produit appartient soit à un profil Garage (mini-boutique), soit à un
 * compte Market Space — jamais aux deux. La relation polymorphe "sellable"
 * mutualise catalogue/commande/paiement entre les deux cas plutôt que de
 * dupliquer la logique (CLAUDE.md §5, ajout v0.4).
 */
#[Fillable(['name', 'description', 'sku', 'price', 'stock_quantity', 'status', 'rejection_reason', 'reviewed_by', 'reviewed_at'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'status' => ProductStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sellable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Propriétaire (garagiste ou compte Market Space) autorisé à gérer ce
     * produit — utilisé par ProductPolicy pour cloisonner l'accès.
     */
    public function ownerUserId(): int
    {
        return $this->sellable->user_id;
    }

    /**
     * Visible côté app mobile seulement si le produit est validé ET que le
     * compte vendeur (garage ou Market Space) l'est aussi (CLAUDE.md §5, règle 5).
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status === ProductStatus::Approved && $this->sellable->isPubliclyVisible();
    }
}
