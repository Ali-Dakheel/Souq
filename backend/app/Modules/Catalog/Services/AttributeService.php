<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AttributeService
{
    /**
     * @return Collection<int, Attribute>
     */
    public function listAttributes(): Collection
    {
        return Attribute::with('values')
            ->orderBy('sort_order')
            ->get();
    }

    public function getAttribute(int $id): Attribute
    {
        return Attribute::with('values')->findOrFail($id);
    }

    public function createAttribute(array $data): Attribute
    {
        $data['slug'] ??= $this->generateSlug($data['name']);

        return Attribute::create($data);
    }

    public function updateAttribute(Attribute $attribute, array $data): Attribute
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name'], $attribute->id);
        }

        $attribute->update($data);

        return $attribute->fresh('values');
    }

    public function addValue(Attribute $attribute, array $data): AttributeValue
    {
        return $attribute->values()->create($data);
    }

    public function updateValue(AttributeValue $value, array $data): AttributeValue
    {
        $value->update($data);

        return $value;
    }

    public function deleteValue(AttributeValue $value): void
    {
        $value->delete();
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
        return Attribute::where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }
}
