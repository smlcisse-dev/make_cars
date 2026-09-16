<?php

namespace App\Http\Controllers\Api\Garage;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedGarage;
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
    use ResolvesAuthenticatedGarage;

    public function __construct(private readonly ProductService $productService) {}

    /**
     * Catalogue de la mini-boutique : un seul stock, partagé entre pièces
     * d'atelier et consommables courants (CLAUDE.md §5, ajout v0.4).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = $this->authenticatedGarage($request)->products()->latest()->paginate();

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($this->authenticatedGarage($request), $request->validated());

        return $this->success(new ProductResource($product), 'Produit ajouté, en attente de validation admin.', 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($product->sellable_id === $garage->id && $product->sellable_type === $garage->getMorphClass(), 404);

        $product = $this->productService->update($product, $request->validated());

        return $this->success(new ProductResource($product), 'Produit mis à jour, en attente de validation admin.');
    }

    public function updateStock(UpdateProductStockRequest $request, Product $product): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($product->sellable_id === $garage->id && $product->sellable_type === $garage->getMorphClass(), 404);

        $product = $this->productService->updateStock($product, $request->integer('stock_quantity'));

        return $this->success(new ProductResource($product), 'Stock mis à jour.');
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($product->sellable_id === $garage->id && $product->sellable_type === $garage->getMorphClass(), 404);

        $this->productService->delete($product);

        return $this->success(message: 'Produit supprimé.');
    }
}
