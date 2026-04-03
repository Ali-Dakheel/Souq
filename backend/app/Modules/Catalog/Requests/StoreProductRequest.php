<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:products,slug', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['sometimes', 'nullable', 'array'],
            'description.ar' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'base_price_fils' => ['required', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'product_type' => ['sometimes', 'string', Rule::in(['simple', 'configurable', 'bundle', 'downloadable', 'virtual'])],
            'images' => ['sometimes', 'nullable', 'array'],
            'images.*' => ['string', 'max:1000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:product_tags,id'],

            'variants' => ['sometimes', 'array', 'min:1'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:100', 'distinct', 'unique:variants,sku'],
            'variants.*.attributes' => ['required_with:variants', 'array'],
            'variants.*.price_fils' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'variants.*.is_available' => ['sometimes', 'boolean'],
            'variants.*.quantity_available' => ['sometimes', 'integer', 'min:0'],
            'variants.*.low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
