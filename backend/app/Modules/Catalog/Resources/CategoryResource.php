<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'image' => $this->whenLoaded('image', fn () => [
                'url' => $this->image?->image_url,
                'alt' => $this->image?->alt_text,
            ]),
            'parent' => $this->whenLoaded('parent', fn () => new CategoryResource($this->parent)),
            'products_count' => $this->when(
                isset($this->products_count),
                $this->products_count
            ),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
