<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:255'],
            'locale' => ['sometimes', 'string', Rule::in(['ar', 'en'])],
            'category' => ['sometimes', 'integer', 'exists:categories,id'],
            'min_price' => ['sometimes', 'integer', 'min:0'],
            'max_price' => ['sometimes', 'integer', 'min:0', 'gte:min_price'],
            'sort' => ['sometimes', 'string', Rule::in(['price_asc', 'price_desc'])],
            'in_stock' => ['sometimes', 'boolean'],
            'product_type' => ['sometimes', 'string', Rule::in(['simple', 'configurable', 'bundle', 'downloadable', 'virtual'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
