<?php

declare(strict_types=1);

namespace App\Modules\Cart\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'min:1', 'exists:variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
