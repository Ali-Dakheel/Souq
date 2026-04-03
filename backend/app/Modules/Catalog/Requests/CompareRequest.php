<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'variant_ids' => ['required', 'array', 'min:1', 'max:4'],
            'variant_ids.*' => [
                'integer',
                Rule::exists('variants', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'variant_ids.required' => 'Variant IDs are required.',
            'variant_ids.array' => 'Variant IDs must be an array.',
            'variant_ids.min' => 'At least 1 variant is required.',
            'variant_ids.max' => 'Maximum 4 variants allowed.',
            'variant_ids.*.integer' => 'Each variant ID must be an integer.',
            'variant_ids.*.exists' => 'One or more variant IDs do not exist.',
        ];
    }
}
