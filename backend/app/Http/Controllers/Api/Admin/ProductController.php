<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Admin\RejectProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    /**
     * Liste paginée, tous vendeurs confondus (garage ou Market Space) —
     * supervision admin (CLAUDE.md §5 règle 8).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        $products = Product::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->with('sellable')
            ->latest()
            ->paginate();

        return ProductResource::collection($products);
    }

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return $this->success(new ProductResource($product->load('sellable')));
    }

    public function approve(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('review', $product);

        $product = $this->productService->approve($product, $request->user());

        return $this->success(new ProductResource($product), 'Produit validé.');
    }

    public function reject(RejectProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->reject($product, $request->user(), $request->string('reason')->toString());

        return $this->success(new ProductResource($product), 'Produit rejeté.');
    }
}
