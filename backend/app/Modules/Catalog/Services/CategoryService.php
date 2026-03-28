<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class CategoryService
{
    /**
     * Return root categories with recursively loaded active children.
     *
     * @return Collection<int, Category>
     */
    public function getTree(): Collection
    {
        return Category::with(['image', 'children' => fn ($q) => $q->active()->with('image')])
            ->active()
            ->root()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, Category>
     */
    public function getRootCategories(): Collection
    {
        return Category::with('image')
            ->active()
            ->root()
            ->orderBy('sort_order')
            ->get();
    }

    public function getCategory(int|string $idOrSlug): Category
    {
        $query = Category::with(['image', 'children.image', 'parent']);

        $category = is_numeric($idOrSlug)
            ? $query->findOrFail((int) $idOrSlug)
            : $query->where('slug', $idOrSlug)->firstOrFail();

        return $category;
    }

    public function createCategory(array $data): Category
    {
        $data['slug'] ??= $this->generateSlug($data['name']);

        return Category::create($data);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name'], $category->id);
        }

        $category->update($data);

        return $category->fresh(['image', 'parent']);
    }

    /**
     * @throws RuntimeException when the category has products or sub-categories.
     */
    public function deleteCategory(Category $category): void
    {
        if ($category->products()->exists()) {
            throw new RuntimeException('Cannot delete a category that has products.');
        }

        if ($category->children()->exists()) {
            throw new RuntimeException('Cannot delete a category that has sub-categories.');
        }

        $category->image()?->delete();
        $category->delete();
    }

    private function generateSlug(array|string $name, ?int $excludeId = null): string
    {
        $base = Str::slug(is_array($name) ? ($name['en'] ?? $name['ar'] ?? '') : $name);
        $slug = $base;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId): bool
    {
        return Category::where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }
}
