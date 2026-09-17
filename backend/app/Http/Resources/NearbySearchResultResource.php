<?php

namespace App\Http\Resources;

use App\Support\MatchedProductResult;
use App\Support\NearbySearchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NearbySearchResult
 */
class NearbySearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'photo_url' => $this->photoUrl,
            'distance_km' => $this->distanceKm !== null ? round($this->distanceKm, 2) : null,
            'average_rating' => $this->averageRating !== null ? round($this->averageRating, 1) : null,
            'reviews_count' => $this->reviewsCount,
            'is_open_now' => $this->isOpenNow,
            // Vide hors filtre `product_name` (CLAUDE.md §5, ajout v0.15).
            'matched_products' => collect($this->matchedProducts)->map(fn (MatchedProductResult $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
            ])->all(),
        ];
    }
}
