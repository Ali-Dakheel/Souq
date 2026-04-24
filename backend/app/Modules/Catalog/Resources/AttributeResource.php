<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Resources;

use App\Modules\Catalog\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attribute */
class AttributeResource extends JsonResource
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
            'attribute_type' => $this->attribute_type,
            'input_type' => $this->input_type,
            'is_filterable' => $this->is_filterable,
            'sort_order' => $this->sort_order,
            'values' => $this->whenLoaded('values', fn () => $this->values->map(fn ($value) => [
                'id' => $value->id,
                'name' => $value->name,
                'value_key' => $value->value_key,
                'display_value' => $value->display_value,
                'sort_order' => $value->sort_order,
            ])),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
