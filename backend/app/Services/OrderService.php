<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Achat isolé de pièces/produits, sans aucune prestation associée
 * (CLAUDE.md §5, règle 11 et ajout v0.9) : commande → paiement immédiat →
 * facture, jamais de devis ni de négociation. Toutes les lignes d'une même
 * commande doivent appartenir au même vendeur (Garage ou Market Space) —
 * pas de panier mixte, même principe qu'un devis limité à un seul garage.
 */
class OrderService
{
    public function __construct(
        private readonly OrderPdfService $pdfService,
        private readonly ChatService $chatService,
        private readonly ProductService $productService,
        private readonly PushNotificationService $notificationService,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $linesData
     */
    public function create(User $client, array $linesData): Order
    {
        return DB::transaction(function () use ($client, $linesData) {
            $firstProduct = $this->findApprovedProduct((int) $linesData[0]['product_id']);
            $sellable = $firstProduct->sellable;

            abort_unless($sellable->isPubliclyVisible(), 404, 'Boutique introuvable.');

            $order = Order::create([
                'sellable_type' => $sellable->getMorphClass(),
                'sellable_id' => $sellable->id,
                'user_id' => $client->id,
                'status' => OrderStatus::Pending,
            ]);

            foreach ($linesData as $lineData) {
                $product = $this->findApprovedProduct((int) $lineData['product_id']);

                abort_unless(
                    $product->sellable_type === $sellable->getMorphClass() && $product->sellable_id === $sellable->id,
                    422,
                    'Tous les produits d\'une commande doivent provenir de la même boutique.'
                );

                $quantity = (int) $lineData['quantity'];
                abort_if($product->stock_quantity < $quantity, 422, 'Stock insuffisant pour ce produit.');

                $order->lines()->create([
                    'product_id' => $product->id,
                    'label' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $quantity,
                    'line_total' => $product->price * $quantity,
                ]);
            }

            return $order->fresh('lines');
        });
    }

    private function findApprovedProduct(int $productId): Product
    {
        return Product::where('id', $productId)
            ->where('status', ProductStatus::Approved)
            ->firstOrFail();
    }

    /**
     * Sous verrou, comme markPaid() : une annulation concurrente d'un
     * paiement ne peut jamais repasser une commande payée en annulée.
     *
     * @throws ApiException
     */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lockPendingOrder($order);

            $order->update(['status' => OrderStatus::Cancelled]);

            $this->notificationService->notifyOrderStatusChanged($order);

            return $order;
        });
    }

    /**
     * Paiement manuel (V1 — pas encore d'agrégateur en ligne, CLAUDE.md §7) :
     * décrémente le stock (même mécanisme unique que le module Devis —
     * CLAUDE.md §5, ajout v0.4) puis génère la facture, postée dans le chat du
     * vendeur (Garage ou Market Space) avec l'automobiliste.
     */
    public function markPaid(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // Verrou sur la commande : deux paiements simultanés (double
            // clic) ne décrémentent le stock qu'une fois.
            $order = $this->lockPendingOrder($order);

            foreach ($order->lines as $line) {
                $this->productService->decrementStock($line->product, $line->quantity);
            }

            $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
            $this->pdfService->generate($order);

            $this->chatService->postOrderMessage($order->sellable, $order->user, $order, 'Facture disponible pour votre commande.');

            $this->notificationService->notifyOrderStatusChanged($order);

            return $order->fresh(['lines', 'sellable']);
        });
    }

    /**
     * Relit la commande sous verrou (`SELECT ... FOR UPDATE`) et vérifie
     * qu'elle est toujours en attente de paiement. À appeler dans une
     * transaction.
     *
     * @throws ApiException
     */
    private function lockPendingOrder(Order $order): Order
    {
        $order = Order::query()->lockForUpdate()->findOrFail($order->id);

        if ($order->status !== OrderStatus::Pending) {
            throw new ApiException('Cette commande n\'est plus en attente de paiement.', 409, 'order_not_pending');
        }

        return $order;
    }
}
