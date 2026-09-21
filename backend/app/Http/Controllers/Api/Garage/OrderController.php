<?php

namespace App\Http\Controllers\Api\Garage;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedGarage;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    use ResolvesAuthenticatedGarage;

    public function __construct(private readonly OrderService $orderService) {}

    private function authorizeOrder(Request $request, Order $order): void
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($order->sellable_type === $garage->getMorphClass() && $order->sellable_id === $garage->id, 404);
    }

    /**
     * Commandes isolées de la mini-boutique, sans prestation associée
     * (CLAUDE.md §5, ajout v0.9).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $this->authenticatedGarage($request)->orders()->with(['lines', 'user'])->latest()->paginate();

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        return $this->success(new OrderResource($order->load('lines', 'user')));
    }

    /**
     * Paiement manuel V1 (espèces ou autre, en attendant l'agrégateur en
     * ligne — CLAUDE.md §7) : décrémente le stock et génère la facture.
     */
    public function markPaid(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->status === OrderStatus::Pending, 403, 'Cette commande n\'est plus en attente de paiement.');

        $order = $this->orderService->markPaid($order);

        return $this->success(new OrderResource($order->load(['lines', 'user'])), 'Paiement enregistré, facture générée.');
    }

    public function downloadPdf(Request $request, Order $order): StreamedResponse
    {
        $this->authorizeOrder($request, $order);
        abort_if($order->pdf_path === null, 404, 'Facture pas encore générée pour cette commande.');

        return Storage::disk($order->pdf_disk)->download($order->pdf_path);
    }
}
