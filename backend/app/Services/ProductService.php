<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Product;
use App\Models\User;

/**
 * Logique produit mutualisée entre mini-boutique Garage et Market Space : un
 * produit appartient à l'un ou l'autre via une relation polymorphe, mais le
 * cycle de vie (création, validation admin, stock) est identique dans les
 * deux cas (CLAUDE.md §5, ajout v0.4).
 */
class ProductService
{
    /**
     * @param  array{name: string, description: ?string, sku: ?string, price: float, stock_quantity: int}  $data
     */
    public function create(Garage|MarketSpaceAccount $sellable, array $data): Product
    {
        return $sellable->products()->create([
            ...$data,
            'status' => ProductStatus::Pending,
        ]);
    }

    /**
     * Toute modification d'un produit repasse son statut en attente de
     * validation admin (CLAUDE.md §5, règle 5).
     *
     * @param  array{name: string, description: ?string, sku: ?string, price: float}  $data
     */
    public function update(Product $product, array $data): Product
    {
        $product->update([
            ...$data,
            'status' => ProductStatus::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return $product;
    }

    /**
     * Correction de quantité en stock : ne remet pas en cause la validation
     * admin déjà accordée (ce n'est pas la légitimité du produit qui change).
     */
    public function updateStock(Product $product, int $quantity): Product
    {
        $product->update(['stock_quantity' => $quantity]);

        return $product;
    }

    /**
     * Unique point de décrément du stock (CLAUDE.md §5 ajout v0.4, règle
     * "un seul mécanisme de décrément de stock") : appelé par le module
     * Devis à l'acceptation d'un devis contenant des lignes de pièces
     * (CLAUDE.md §5, ajout v0.8), que la vente soit isolée ou intégrée à
     * une prestation.
     */
    public function decrementStock(Product $product, int $quantity): Product
    {
        $product->decrement('stock_quantity', $quantity);

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function approve(Product $product, User $admin): Product
    {
        $product->update([
            'status' => ProductStatus::Approved,
            'rejection_reason' => null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $product;
    }

    public function reject(Product $product, User $admin, string $reason): Product
    {
        $product->update([
            'status' => ProductStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $product;
    }
}
