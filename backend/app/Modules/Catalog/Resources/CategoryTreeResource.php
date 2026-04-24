<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryTreeResource extends JsonResource
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
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'image' => $this->whenLoaded('image', fn () => [
                'url' => $this->image?->image_url,
                'alt' => $this->image?->alt_text,
            ]),
            'children' => CategoryTreeResource::collection($this->whenLoaded('children')),
        ];
    }
}
