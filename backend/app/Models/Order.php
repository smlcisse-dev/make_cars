<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Achat isolé de pièces/produits, sans aucune prestation associée
 * (CLAUDE.md §5, règle 11 et ajout v0.9) : commande → paiement immédiat →
 * facture, jamais de devis ni de négociation puisque le prix est déjà fixé
 * au catalogue. La relation polymorphe "sellable" mutualise Garage
 * (mini-boutique) et Market Space — même principe que Product (CLAUDE.md §5,
 * ajout v0.4).
 */
#[Fillable(['sellable_type', 'sellable_id', 'user_id', 'status', 'paid_at', 'pdf_disk', 'pdf_path'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'paid_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function total(): string
    {
        return (string) $this->lines->sum(fn (OrderLine $line) => $line->line_total);
    }
}
