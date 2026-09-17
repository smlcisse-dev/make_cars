<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\QuoteStatus;
use App\Enums\ReviewStatus;
use App\Models\Garage;
use App\Models\Order;
use App\Models\Quote;
use App\Models\Review;
use App\Models\User;

/**
 * Un avis n'est possible qu'après une transaction terminée — un devis
 * facturé ou une commande payée, jamais avant (CLAUDE.md §5, règle 7 et
 * ajout v0.10). Un seul avis par transaction : la contrainte unique en base
 * sert de filet, le contrôle explicite ici évite une erreur SQL brute.
 */
class ReviewService
{
    /**
     * @param  array{rating: int, comment?: string|null}  $data
     */
    public function createForQuote(Quote $quote, User $client, array $data): Review
    {
        abort_unless($quote->user_id === $client->id, 404);
        abort_unless($quote->status === QuoteStatus::Invoiced, 403, 'Seul un devis facturé peut être noté.');

        return $this->store(Garage::class, $quote->garage_id, Quote::class, $quote->id, $client, $data);
    }

    /**
     * @param  array{rating: int, comment?: string|null}  $data
     */
    public function createForOrder(Order $order, User $client, array $data): Review
    {
        abort_unless($order->user_id === $client->id, 404);
        abort_unless($order->status === OrderStatus::Paid, 403, 'Seule une commande payée peut être notée.');

        return $this->store($order->sellable_type, $order->sellable_id, Order::class, $order->id, $client, $data);
    }

    /**
     * @param  array{rating: int, comment?: string|null}  $data
     */
    private function store(string $reviewableType, int $reviewableId, string $transactionType, int $transactionId, User $client, array $data): Review
    {
        abort_if(
            Review::where('transaction_type', $transactionType)->where('transaction_id', $transactionId)->exists(),
            422,
            'Cette transaction a déjà été notée.'
        );

        return Review::create([
            'reviewable_type' => $reviewableType,
            'reviewable_id' => $reviewableId,
            'transaction_type' => $transactionType,
            'transaction_id' => $transactionId,
            'user_id' => $client->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'status' => ReviewStatus::Visible,
        ]);
    }

    /**
     * Masquage logique tracé (motif, auteur, date) — jamais une suppression
     * SQL, pour garder la preuve de toute action de modération et éviter les
     * abus de ce pouvoir lui-même (CLAUDE.md §5, ajout v0.10).
     */
    public function moderate(Review $review, User $admin, string $reason): Review
    {
        $review->update([
            'status' => ReviewStatus::Hidden,
            'moderation_reason' => $reason,
            'moderated_by' => $admin->id,
            'moderated_at' => now(),
        ]);

        return $review;
    }
}
