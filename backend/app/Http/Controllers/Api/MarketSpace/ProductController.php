<?php

namespace App\Http\Controllers\Api\MarketSpace;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedMarketSpaceAccount;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\UpdateProductStockRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    use ResolvesAuthenticatedMarketSpaceAccount;

    public function __construct(private readonly ProductService $productService) {}

    /**
     * Catalogue Market Space : stock propre à la boutique, distinct de celui
     * d'un éventuel garage détenu par la même structure (CLAUDE.md §5, ajout v0.4).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = $this->authenticatedMarketSpaceAccount($request)->products()->latest()->paginate();

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($this->authenticatedMarketSpaceAccount($request), $request->validated());

        return $this->success(new ProductResource($product), 'Produit ajouté, en attente de validation admin.', 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $account = $this->authenticatedMarketSpaceAccount($request);
        abort_unless($product->sellable_id === $account->id && $product->sellable_type === $account->getMorphClass(), 404);

        $product = $this->productService->update($product, $request->validated());

        return $this->success(new ProductResource($product), 'Produit mis à jour, en attente de validation admin.');
    }

    public function updateStock(UpdateProductStockRequest $request, Product $product): JsonResponse
    {
        $account = $this->authenticatedMarketSpaceAccount($request);
        abort_unless($product->sellable_id === $account->id && $product->sellable_type === $account->getMorphClass(), 404);

        $product = $this->productService->updateStock($product, $request->integer('stock_quantity'));

        return $this->success(new ProductResource($product), 'Stock mis à jour.');
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $account = $this->authenticatedMarketSpaceAccount($request);
        abort_unless($product->sellable_id === $account->id && $product->sellable_type === $account->getMorphClass(), 404);

        $this->productService->delete($product);

        return $this->success(message: 'Produit supprimé.');
    }
}
