<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuoteController extends Controller
{
    /**
     * Supervision admin en lecture seule (CLAUDE.md §5 règle 8).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $quotes = Quote::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->with(['appointment.garage', 'appointment.user', 'versions.lines'])
            ->latest()
            ->paginate();

        return QuoteResource::collection($quotes);
    }

    public function show(Quote $quote): QuoteResource
    {
        return new QuoteResource($quote->load(['appointment.garage', 'appointment.user', 'versions.lines']));
    }
}
