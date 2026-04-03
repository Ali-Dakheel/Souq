<?php

declare(strict_types=1);

namespace App\Modules\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddWishlistItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', Rule::exists('variants', 'id')],
        ];
    }
}
