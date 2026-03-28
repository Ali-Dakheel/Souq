<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CategoryImage;
use App\Modules\Catalog\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Requests\UpdateCategoryRequest;
use App\Modules\Catalog\Resources\CategoryResource;
use App\Modules\Catalog\Resources\CategoryTreeResource;
use App\Modules\Catalog\Resources\ProductCollection;
use App\Modules\Catalog\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $categories = $this->categoryService->getTree();

        return CategoryTreeResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->createCategory($request->safe()->except(['image_url', 'image_alt']));

        if ($request->filled('image_url')) {
            CategoryImage::create([
                'category_id' => $category->id,
                'image_url' => $request->input('image_url'),
                'alt_text' => $request->input('image_alt'),
            ]);
        }

        return (new CategoryResource($category->load('image')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        $category->load(['image', 'children.image', 'parent']);

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $updated = $this->categoryService->updateCategory(
            $category,
            $request->safe()->except(['image_url', 'image_alt'])
        );

        if ($request->has('image_url')) {
            $imageUrl = $request->input('image_url');

            if ($imageUrl === null) {
                $updated->image()?->delete();
            } else {
                CategoryImage::updateOrCreate(
                    ['category_id' => $updated->id],
                    ['image_url' => $imageUrl, 'alt_text' => $request->input('image_alt')]
                );
            }
        }

        return new CategoryResource($updated->load('image'));
    }

    public function destroy(Category $category): JsonResponse
    {
        try {
            $this->categoryService->deleteCategory($category);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(null, 204);
    }

    public function products(Category $category): ProductCollection
    {
        $products = $category->products()
            ->with(['variants.inventory', 'tags'])
            ->available()
            ->orderBy('sort_order')
            ->paginate(20);

        return new ProductCollection($products);
    }
}
