<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'description' => $this->description,
            'category_id' => $this->category_id,
            'base_price_fils' => $this->base_price_fils,
            'lowest_price_fils' => $this->when(
                $this->relationLoaded('variants'),
                $this->lowest_price_fils
            ),
            'is_available' => $this->is_available,
            'images' => $this->images ?? [],
            'sort_order' => $this->sort_order,
            'product_type' => $this->product_type,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'variants' => VariantResource::collection($this->whenLoaded('variants')),
            'bundle_options' => BundleOptionResource::collection($this->whenLoaded('bundleOptions')),
            'downloadable_links' => DownloadableLinkResource::collection($this->whenLoaded('downloadableLinks')),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])),
            'reviews_count' => $this->when(
                isset($this->reviews_count),
                $this->reviews_count
            ),
            'average_rating' => $this->when(
                isset($this->average_rating),
                $this->average_rating
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
