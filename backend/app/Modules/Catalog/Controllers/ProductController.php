<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\BundleOption;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Requests\CompareRequest;
use App\Modules\Catalog\Requests\StoreProductRequest;
use App\Modules\Catalog\Requests\UpdateProductRequest;
use App\Modules\Catalog\Resources\BundleOptionProductResource;
use App\Modules\Catalog\Resources\BundleOptionResource;
use App\Modules\Catalog\Resources\DownloadableLinkResource;
use App\Modules\Catalog\Resources\ProductCollection;
use App\Modules\Catalog\Resources\ProductResource;
use App\Modules\Catalog\Resources\VariantResource;
use App\Modules\Catalog\Services\CompareService;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CompareService $compareService
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
        $product->load(['category.image', 'variants.inventory', 'tags', 'bundleOptions', 'downloadableLinks']);

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

    public function storeBundleOption(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'required' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        try {
            $bundleOption = $this->productService->createBundleOption($product, $validated);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['product_type' => [$e->getMessage()]]);
        }

        return (new BundleOptionResource($bundleOption))
            ->response()
            ->setStatusCode(201);
    }

    public function addBundleOptionProduct(Request $request, Product $product, BundleOption $option): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'default_quantity' => ['integer', 'min:1'],
            'min_quantity' => ['integer', 'min:1'],
            'max_quantity' => ['integer', 'min:1'],
            'price_override_fils' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        try {
            $bundleOptionProduct = $this->productService->addProductToBundleOption($option, $validated);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['product_id' => ['This product is already in the bundle option.']]);
        }

        return (new BundleOptionProductResource($bundleOptionProduct))
            ->response()
            ->setStatusCode(201);
    }

    public function storeDownloadableLink(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'file_path' => ['required', 'string', 'max:500'],
            'downloads_allowed' => ['integer', 'min:0'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        try {
            $downloadableLink = $this->productService->createDownloadableLink($product, $validated);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['product_type' => [$e->getMessage()]]);
        }

        return (new DownloadableLinkResource($downloadableLink))
            ->response()
            ->setStatusCode(201);
    }

    public function compare(CompareRequest $request): JsonResponse
    {
        $result = $this->compareService->compare($request->validated('variant_ids'));

        return response()->json(['data' => $result]);
    }
}
