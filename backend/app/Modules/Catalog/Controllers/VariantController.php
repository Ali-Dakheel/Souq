<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Catalog\Requests\StoreVariantRequest;
use App\Modules\Catalog\Requests\UpdateVariantRequest;
use App\Modules\Catalog\Resources\VariantResource;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Http\JsonResponse;

class VariantController extends Controller
{
    public function __construct(
        private readonly ProductService $productService
    ) {}

    public function store(StoreVariantRequest $request, Product $product): JsonResponse
    {
        $variant = $this->productService->createVariantWithInventory(
            $product,
            $request->validated()
        );

        return (new VariantResource($variant))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product, Variant $variant): VariantResource
    {
        $this->authorizeVariantBelongsToProduct($product, $variant);

        $variant->load('inventory');

        return new VariantResource($variant);
    }

    public function update(UpdateVariantRequest $request, Product $product, Variant $variant): VariantResource
    {
        $this->authorizeVariantBelongsToProduct($product, $variant);

        $variant->update($request->validated());

        return new VariantResource($variant->fresh('inventory'));
    }

    public function destroy(Product $product, Variant $variant): JsonResponse
    {
        $this->authorizeVariantBelongsToProduct($product, $variant);

        $variant->inventory?->delete();
        $variant->delete();

        return response()->json(null, 204);
    }

    private function authorizeVariantBelongsToProduct(Product $product, Variant $variant): void
    {
        abort_if($variant->product_id !== $product->id, 404);
    }
}
