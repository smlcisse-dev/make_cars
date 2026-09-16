<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Mobile\StoreOrderRequest;
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
    public function __construct(private readonly OrderService $orderService) {}

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()->with('lines')->latest()->paginate();

        return OrderResource::collection($orders);
    }

    /**
     * Achat isolé, sans devis ni négociation : le prix est déjà fixé au
     * catalogue (CLAUDE.md §5, règle 11 et ajout v0.9).
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create($request->user(), $request->array('lines'));

        return $this->success(new OrderResource($order), 'Commande créée, en attente de paiement.', 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        return $this->success(new OrderResource($order->load('lines')));
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->status === OrderStatus::Pending, 403, 'Cette commande ne peut plus être annulée.');

        $order = $this->orderService->cancel($order);

        return $this->success(new OrderResource($order), 'Commande annulée.');
    }

    public function downloadPdf(Request $request, Order $order): StreamedResponse
    {
        $this->authorizeOrder($request, $order);
        abort_if($order->pdf_path === null, 404, 'Facture pas encore générée pour cette commande.');

        return Storage::disk($order->pdf_disk)->download($order->pdf_path);
    }
}
