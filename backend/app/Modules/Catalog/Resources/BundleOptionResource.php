<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BundleOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'required' => $this->required,
            'sort_order' => $this->sort_order,
            'products' => BundleOptionProductResource::collection($this->whenLoaded('products')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
