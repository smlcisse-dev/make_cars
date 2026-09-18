<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Logique produit mutualisée entre mini-boutique Garage et Market Space : un
 * produit appartient à l'un ou l'autre via une relation polymorphe, mais le
 * cycle de vie (création, validation admin, stock) est identique dans les
 * deux cas (CLAUDE.md §5, ajout v0.4).
 */
class ProductService
{
    public function __construct(private readonly PushNotificationService $notificationService) {}

    private function disk(): string
    {
        return config('filesystems.public_media_disk', 'public');
    }

    /**
     * @param  array{name: string, description: ?string, sku: ?string, price: float, stock_quantity: int}  $data
     */
    public function create(Garage|MarketSpaceAccount $sellable, array $data, ?UploadedFile $image = null): Product
    {
        $product = $sellable->products()->create([
            ...$data,
            'status' => ProductStatus::Pending,
        ]);

        if ($image) {
            $this->storeImage($product, $image);
        }

        return $product;
    }

    /**
     * Toute modification d'un produit repasse son statut en attente de
     * validation admin (CLAUDE.md §5, règle 5).
     *
     * @param  array{name: string, description: ?string, sku: ?string, price: float}  $data
     */
    public function update(Product $product, array $data, ?UploadedFile $image = null): Product
    {
        $product->update([
            ...$data,
            'status' => ProductStatus::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        if ($image) {
            $this->storeImage($product, $image);
        }

        return $product;
    }

    private function storeImage(Product $product, UploadedFile $image): void
    {
        if ($product->image_path) {
            Storage::disk($product->image_disk)->delete($product->image_path);
        }

        $disk = $this->disk();
        $path = $image->store('products/'.class_basename($product->sellable_type).'-'.$product->sellable_id, $disk);

        $product->update(['image_disk' => $disk, 'image_path' => $path]);
    }

    /**
     * Correction de quantité en stock : ne remet pas en cause la validation
     * admin déjà accordée (ce n'est pas la légitimité du produit qui change).
     * Le seuil d'alerte voyage avec cet endpoint plutôt qu'avec update() pour
     * la même raison (CLAUDE.md §5, ajout v0.12). Un réapprovisionnement qui
     * remonte au-dessus du seuil réarme l'alerte pour la prochaine descente
     * — la vente reste le seul mécanisme qui peut la déclencher.
     *
     * @param  array{low_stock_threshold?: ?int}  $data
     */
    public function updateStock(Product $product, int $quantity, array $data = []): Product
    {
        $updates = ['stock_quantity' => $quantity];

        if (array_key_exists('low_stock_threshold', $data)) {
            $updates['low_stock_threshold'] = $data['low_stock_threshold'];
        }

        $product->update($updates);

        if (! $product->isAtOrBelowLowStockThreshold() && $product->low_stock_alert_sent_at !== null) {
            $product->update(['low_stock_alert_sent_at' => null]);
        }

        return $product;
    }

    /**
     * Unique point de décrément du stock (CLAUDE.md §5 ajout v0.4, règle
     * "un seul mécanisme de décrément de stock") : appelé par le module
     * Devis à l'acceptation d'un devis contenant des lignes de pièces
     * (CLAUDE.md §5, ajout v0.8), que la vente soit isolée ou intégrée à
     * une prestation. Déclenche l'alerte de stock bas au vendeur, une seule
     * fois jusqu'à ce que le stock remonte au-dessus du seuil (CLAUDE.md §5,
     * ajout v0.12).
     */
    public function decrementStock(Product $product, int $quantity): Product
    {
        $product->decrement('stock_quantity', $quantity);
        $product->refresh();

        if ($product->isAtOrBelowLowStockThreshold() && $product->low_stock_alert_sent_at === null) {
            $product->update(['low_stock_alert_sent_at' => now()]);
            $this->notificationService->notifyLowStock($product);
        }

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    /**
     * La notification "nouveau produit" part d'ici (validation admin), pas
     * de la création : c'est le moment où le produit devient réellement
     * visible côté app mobile (CLAUDE.md §5, règle 5 et ajout v0.12).
     */
    public function approve(Product $product, User $admin): Product
    {
        $product->update([
            'status' => ProductStatus::Approved,
            'rejection_reason' => null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->notificationService->notifyNewProductToPastClients($product);

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
