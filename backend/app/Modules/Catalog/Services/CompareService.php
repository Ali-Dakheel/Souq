<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Variant;

class CompareService
{
    public function compare(array $variantIds): array
    {
        // Load variants with their product relationships
        $variants = Variant::with('product')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        // Preserve input order
        $ordered = collect($variantIds)
            ->map(fn ($id) => $variants->get($id))
            ->filter();

        // Union all attribute keys across all variants
        $allKeys = $ordered
            ->flatMap(fn ($v) => array_keys($v->attributes ?? []))
            ->unique()
            ->values();

        // Build matrix: for each attribute key, map each variant's value (null if missing)
        $matrix = [];
        foreach ($allKeys as $key) {
            $matrix[$key] = $ordered
                ->map(fn ($v) => ($v->attributes ?? [])[$key] ?? null)
                ->values()
                ->all();
        }

        return [
            'products' => $ordered->map(fn ($v) => [
                'id' => $v->product->id,
                'name_en' => $v->product->name['en'] ?? null,
                'name_ar' => $v->product->name['ar'] ?? null,
                'variant' => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'price_fils' => $v->effective_price_fils,
                    'attributes' => $v->attributes ?? [],
                ],
            ])->values()->all(),
            'attributes' => $matrix,
        ];
    }
}
