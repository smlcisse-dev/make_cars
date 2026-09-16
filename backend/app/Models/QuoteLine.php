<?php

namespace App\Models;

use App\Enums\QuoteLineType;
use Database\Factories\QuoteLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'une version de devis. `label` et `unit_price` sont figés au moment
 * de l'ajout (snapshot) : un devis déjà envoyé ne doit jamais changer de
 * contenu si le catalogue Produits/Services évolue ensuite — c'est un
 * document financier, pas une vue dynamique du catalogue (CLAUDE.md §5,
 * ajout v0.8).
 */
#[Fillable(['quote_version_id', 'type', 'repair_service_id', 'product_id', 'label', 'unit_price', 'quantity', 'line_total'])]
class QuoteLine extends Model
{
    /** @use HasFactory<QuoteLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuoteLineType::class,
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<QuoteVersion, $this>
     */
    public function quoteVersion(): BelongsTo
    {
        return $this->belongsTo(QuoteVersion::class);
    }

    /**
     * @return BelongsTo<RepairService, $this>
     */
    public function repairService(): BelongsTo
    {
        return $this->belongsTo(RepairService::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
