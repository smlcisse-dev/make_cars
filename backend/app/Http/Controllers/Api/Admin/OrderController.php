<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Supervision admin en lecture seule (CLAUDE.md §5 règle 8).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->with(['sellable', 'user', 'lines'])
            ->latest()
            ->paginate();

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['sellable', 'user', 'lines']));
    }
}
