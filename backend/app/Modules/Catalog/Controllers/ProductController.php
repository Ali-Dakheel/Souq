<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Requests\StoreProductRequest;
use App\Modules\Catalog\Requests\UpdateProductRequest;
use App\Modules\Catalog\Resources\ProductCollection;
use App\Modules\Catalog\Resources\ProductResource;
use App\Modules\Catalog\Resources\VariantResource;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService
    ) {}

    public function index(Request $request): ProductCollection
    {
        $filters = $request->only([
            'category_id',
            'tag_id',
            'available',
            'min_price',
            'max_price',
            'search',
        ]);

        $products = $this->productService->listProducts(
            $filters,
            (int) $request->integer('per_page', 20)
        );

        return new ProductCollection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->createProduct($request->validated());

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['category.image', 'variants.inventory', 'tags']);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $updated = $this->productService->updateProduct($product, $request->validated());

        return new ProductResource($updated);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->deleteProduct($product);

        return response()->json(null, 204);
    }

    public function variants(Product $product): AnonymousResourceCollection
    {
        $product->load('variants.inventory');

        return VariantResource::collection($product->variants);
    }
}
